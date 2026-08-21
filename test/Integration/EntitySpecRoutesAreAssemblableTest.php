<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\Router\RouteStackInterface;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use SionModel\Entity\Entity;
use SionModel\Service\EntitiesService;
use Throwable;

use function array_keys;
use function count;
use function is_array;
use function is_string;
use function property_exists;
use function sprintf;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Every route an entity spec names must be assemblable with the parameters that same spec
 * supplies.
 *
 * ## The defect this pins, and why nothing else could see it
 *
 * An entity spec's `show_route`, `edit_route` and redirect routes are *strings*. Nothing
 * validates them, no route name is a class reference, and the code that reads them —
 * `SionModel\View\Helper\FormatEntity`, `App\Laminas\EntityFormatter`,
 * `App\Sion\EntityEdit`, `App\Sion\EntityDelete`, `SionModel\Controller\SionController` —
 * reads them on a path that only runs when a particular entity is being rendered or
 * written. So a wrong route name sits there until the day someone opens the page that
 * formats that entity.
 *
 * Three specs were wrong when this test was written (2026-08-21), six fields between them:
 *
 * | spec | field | route | what assembling it did |
 * | --- | --- | --- | --- |
 * | `user` | `show_route` | `juser/user` | `Part route may not terminate` |
 * | `user` | `edit_route` | `association-edit` | `Missing parameter "sw_id"` |
 * | `user` | `create_action_redirect_route` | `association` | `Missing parameter "sw_id"` |
 * | `dictionary-entry` | `show_route` | `dictionary/entry` | `Part route may not terminate` |
 * | `dictionary-entry` | `create_action_redirect_route` | `dictionary/entry` | same |
 * | `library-import` | `index_route` | `library-imports/library` | `Missing parameter "library_id"` |
 *
 * **A route that may not terminate does not assemble to a URL nothing serves — it throws.**
 * `Laminas\Router\Http\Part::assemble()` raises rather than returning a dead path, so the
 * two `may_terminate => false` entries above were 500s on any page that formatted one of
 * those entities as a link. `dictionary-entry` was live: `report_changes` is on, its
 * 2,711 change rows are formatted by `/sm/view-changes`, and only `changes_max_rows` = 500
 * kept them off the page — the newest was 878th by recency. One edit to any entry would
 * have taken that page down for every moderator.
 *
 * The precedent is older than the fix. `text`'s `delete_action_redirect_route` named
 * `text-delete`, the delete route itself, so every exit from a text deletion threw
 * `Missing parameter "sw_id"` *after* the row was gone — a moderator saw an error page and
 * concluded the delete had failed. That was found by hand in August 2026 and fixed the
 * same way. This test exists so the next one is found by a test run instead.
 *
 * ## Why assemble() and not a route-name lookup
 *
 * Asking the router whether a name exists would have caught none of the six. All six names
 * exist. What is wrong is the pairing of the name with the key the spec offers it, and
 * `assemble()` is the only thing that knows about that pairing — including the two rules
 * that surprise: a Part route refuses to terminate, and `Segment::assemble()` demands every
 * parameter its path declares while checking none of its own constraints (see
 * NavigationRouteParametersTest for the other half of that second rule).
 *
 * ## What is deliberately not asserted
 *
 * That the assembled path matches a route back. It usually does not, and for reasons that
 * are not defects: several routes here are reached only under a locale prefix the laminas
 * router does not model, and a top-level `/:sw_id` route assembles to a path whose match is
 * decided by priority against every other top-level route. Round-tripping would report a
 * dozen false positives and hide the six real ones, which is exactly what the first draft
 * of this test did.
 *
 * ## A neighbouring trap, found while choosing the list above and left alone
 *
 * `Entity::$actionRouteProperties` maps `create => 'createRoute'` and `touch =>
 * 'touchRoute'`, and **neither is a property of `Entity`**. `isActionAllowed()` reads them
 * dynamically, so those two actions ask about an undefined property; on 8.5 that is a
 * warning, which production's `error_reporting` excludes. Nothing calls
 * `isActionAllowed()` with either action today — `FormatEntity` and
 * `App\Laminas\EntityFormatter` only pass `show` and `edit` — so it is filed in
 * docs/BACKLOG.md rather than fixed here, and this list names `touchJsonRoute`, which
 * exists. `testTheListOfRoutePropertiesIsRealPropertiesOfEntity()` is what stops this test
 * from quietly measuring nothing if a name here goes the same way.
 *
 * No database: the router and the entity specs are both built from merged config, so this
 * runs on a bare CI runner.
 */
class EntitySpecRoutesAreAssemblableTest extends TestCase
{
    /**
     * Every route-name property, mapped to the property naming its single route parameter.
     *
     * `null` means the spec offers no key for that route, so it is assembled with no
     * parameters — which is the honest test: that is all its readers can do too.
     */
    private const ROUTE_KEYS = [
        'indexRoute'                => null,
        'showRoute'                 => 'showRouteKey',
        'editRoute'                 => 'editRouteKey',
        'createActionRedirectRoute' => 'createActionRedirectRouteKey',
        'deleteActionRedirectRoute' => null,
        'moderateRoute'             => 'moderateRouteEntityKey',
        'touchJsonRoute'            => 'touchJsonRouteKey',
    ];

    /**
     * The `routeParam => entityField` maps that stand in for a key, in the order the
     * readers try them.
     *
     * `text` and `composition` carry no `showRouteKey` at all — their link comes from
     * `defaultRouteParams`, which is the branch `FormatEntity::wrapAsLink()` falls to
     * third. A test that only knew about keys would report both as failures.
     */
    private const ROUTE_PARAM_MAPS = [
        'showRoute'                 => ['showRouteParams', 'defaultRouteParams'],
        'editRoute'                 => ['editRouteParams', 'defaultRouteParams'],
        'createActionRedirectRoute' => ['defaultRouteParams'],
    ];

    private static ?ServiceManager $services = null;

    /**
     * The list above has to name real properties, or this class passes by looking at
     * nothing. `Entity` has no `__get()` and its constructor writes only properties that
     * already exist, so a typo produces a null read and a silent skip — which is exactly
     * what `touchRoute` did in the first draft.
     */
    public function testTheListOfRoutePropertiesIsRealPropertiesOfEntity(): void
    {
        $missing = [];
        foreach (self::ROUTE_KEYS as $routeProperty => $keyProperty) {
            foreach ([$routeProperty, $keyProperty] as $property) {
                if (is_string($property) && ! property_exists(Entity::class, $property)) {
                    $missing[] = $property;
                }
            }
        }
        foreach (self::ROUTE_PARAM_MAPS as $maps) {
            foreach ($maps as $property) {
                if (! property_exists(Entity::class, $property)) {
                    $missing[] = $property;
                }
            }
        }

        self::assertSame([], $missing, 'SionModel\Entity\Entity has no such property, so it reads as null');
    }

    public function testEveryRouteAnEntitySpecNamesCanBeAssembled(): void
    {
        $entities = $this->entities();
        self::assertGreaterThan(20, count($entities), 'suspiciously few entity specs');

        $router   = $this->router();
        $failures = [];

        foreach ($entities as $name => $spec) {
            foreach (self::ROUTE_KEYS as $routeProperty => $keyProperty) {
                $route = self::stringProperty($spec, $routeProperty);
                if (null === $route) {
                    continue;
                }

                $params = $this->parametersFor($spec, $routeProperty, $keyProperty);

                try {
                    $router->assemble($params, ['name' => $route]);
                } catch (Throwable $e) {
                    $failures[] = sprintf(
                        '%s.%s => %s: %s',
                        (string) $name,
                        $routeProperty,
                        $route,
                        $e->getMessage()
                    );
                }
            }
        }

        self::assertSame(
            [],
            $failures,
            "an entity spec names a route it cannot assemble with the parameters it supplies.\n"
            . "Either the route name belongs to another entity (the `user` spec's three were\n"
            . "copy-pasted from `association`), or it is a `may_terminate => false` part route\n"
            . "with no page of its own — in which case drop the field rather than repointing it,\n"
            . "so the entity renders as text. Whatever reads this field raises the exception\n"
            . "above, mid-page, with no other symptom."
        );
    }

    /**
     * The parameters a reader of this field would have to hand: the declared key, else the
     * first `routeParam => entityField` map that is set, else none.
     *
     * The values are placeholders — `assemble()` substitutes them without checking a
     * single constraint, so what is being tested is whether the *names* satisfy the route,
     * never whether `1` is a plausible id.
     *
     * @return array<string, int>
     */
    private function parametersFor(Entity $spec, string $routeProperty, ?string $keyProperty): array
    {
        if (null !== $keyProperty) {
            $key = self::stringProperty($spec, $keyProperty);
            if (null !== $key) {
                return [$key => 1];
            }
        }

        foreach (self::ROUTE_PARAM_MAPS[$routeProperty] ?? [] as $mapProperty) {
            /** @var mixed $map */
            $map = $spec->$mapProperty;
            if (is_array($map) && [] !== $map) {
                $params = [];
                foreach (array_keys($map) as $routeParam) {
                    $params[(string) $routeParam] = 1;
                }

                return $params;
            }
        }

        return [];
    }

    /** Entity documents these as `string` and leaves them null when unset. */
    private static function stringProperty(Entity $spec, string $property): ?string
    {
        /** @var mixed $value */
        $value = $spec->$property;

        return is_string($value) && '' !== $value ? $value : null;
    }

    /** @return array<string, Entity> */
    private function entities(): array
    {
        /** @var EntitiesService $service */
        $service = self::services()->get(EntitiesService::class);

        return $service->getEntities();
    }

    private function router(): RouteStackInterface
    {
        /** @var RouteStackInterface $router */
        $router = self::services()->get('Router');

        return $router;
    }

    private static function services(): ServiceManager
    {
        if (null !== self::$services) {
            return self::$services;
        }

        /** @var array<string, mixed> $appConfig */
        $appConfig = require __DIR__ . '/../../config/application.config.php';
        //A cached merged config would answer for a tree built before the change under
        //test, and CI has no writable data/config to cache into
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        $services = new ServiceManager();
        (new ServiceManagerConfig($appConfig['service_manager'] ?? []))->configureServiceManager($services);
        $services->setService('ApplicationConfig', $appConfig);
        $services->get('ModuleManager')->loadModules();

        return self::$services = $services;
    }
}

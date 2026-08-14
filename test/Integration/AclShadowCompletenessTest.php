<?php

namespace SchoenstattTest\Integration;

use App\Routing\RegexSampler;
use Laminas\Http\Request;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Guards the completeness of the authorization oracle's shadow table.
 *
 * ## The bug this exists to prevent recurring
 *
 * When a Symfony route takes over a URL, the bjyauthorize guard on whatever
 * laminas route used to answer it **stops running entirely** — a Symfony-served
 * request never boots laminas-mvc. `docs/acl-rules.md` records those takeovers
 * so a reviewer can check that the ported route re-declares the same restriction.
 *
 * Until 2026-08-14 that record was silently incomplete. `tools/acl-table.php`
 * handed the laminas route *pattern* (`/books/:book_id/edit`) to the Symfony
 * `UrlMatcher`, which matches URLs and not patterns; the match threw and the
 * route was skipped. So **every laminas route with a parameter in it was
 * unshadowable by construction** — 0 of the 31 reported rows had one, and ~90
 * routes were never examined. That is the blind spot that let batch 5 make nine
 * guarded routes unreachable in production without the oracle saying a word
 * (see src/Sion/ReservedVerbs.php).
 *
 * ## Why this does not call the tool
 *
 * Asserting the tool against itself would prove nothing: a regression in its
 * shadow check would regenerate a smaller snapshot and a snapshot-comparison
 * test would happily agree with it. So this computes the takeover set from the
 * **real routers on both sides** — Symfony's own collection for the URLs it
 * owns, the real `TreeRouteStack` for which laminas route each one displaces —
 * and requires the committed baseline to account for every one. The tool can
 * only satisfy this by actually being right.
 *
 * Needs vendor/ to merge the module configuration, but never bootstraps the
 * application: no database, no running app, so this runs on a bare CI runner.
 */
class AclShadowCompletenessTest extends TestCase
{
    private const BASELINE = __DIR__ . '/../../docs/acl-baseline.json';

    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * The merged module configuration, obtained the way bin/console does it:
     * build the ServiceManager and load modules, but never call bootstrap().
     *
     * The config cache must be off, or the module listener tries to write
     * data/config/ — which fails outright on a bare CI runner and could leave a
     * cache file owned by the wrong user next to a real deployment.
     *
     * @return array<string, mixed>
     */
    private function config(): array
    {
        if (null !== self::$config) {
            return self::$config;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        $serviceManager = new ServiceManager();
        (new ServiceManagerConfig($appConfig['service_manager'] ?? []))
            ->configureServiceManager($serviceManager);
        $serviceManager->setService('ApplicationConfig', $appConfig);
        $serviceManager->get('ModuleManager')->loadModules();

        /** @var array<string, mixed> $config */
        $config = $serviceManager->get('config');

        return self::$config = $config;
    }

    /**
     * The real laminas router.
     *
     * Comes back with a null base URL, where the running application's router
     * has `/en` — SlmLocale sets the base URL to the negotiated locale at request
     * time. Matching unprefixed paths against it is therefore correct, and is the
     * same reason the Symfony side declares a bare twin for every route.
     */
    private function laminasRouter(): TreeRouteStack
    {
        /** @var array<string, mixed> $routerConfig */
        $routerConfig = $this->config()['router'] ?? [];

        return TreeRouteStack::factory($routerConfig);
    }

    /** The ported routes, minus the catch-all that claims every path by design. */
    private function portedRoutes(): RouteCollection
    {
        /** @var RouteCollection $collection */
        $collection = require __DIR__ . '/../../config/symfony/routes.php';

        $ported = new RouteCollection();
        foreach ($collection->all() as $name => $route) {
            $controller = $route->getDefault('_controller');
            $controller = is_array($controller)
                ? implode('::', array_map('strval', $controller))
                : (string) $controller;
            if (str_starts_with($controller, 'App\Http\LegacyBridge')) {
                continue;
            }
            $ported->add((string) $name, $route);
        }

        return $ported;
    }

    /**
     * A concrete URL the Symfony route answers, with its locale prefix removed.
     *
     * Stripping rather than instantiating the prefix collapses each `.locale`
     * twin onto its bare form, which is what we want — they are the same page.
     */
    private function probeUrl(Route $route): ?string
    {
        $path = $route->getPath();
        if (str_starts_with($path, '/{_locale}')) {
            $path = substr($path, strlen('/{_locale}'));
            if ('' === $path) {
                $path = '/';
            }
        }

        $failed = false;
        $url    = preg_replace_callback(
            '/\{(\w+)\}/',
            static function (array $m) use ($route, &$failed): string {
                $sample = RegexSampler::sample($route->getRequirement($m[1]) ?? '[^/]+');
                if (null === $sample) {
                    $failed = true;
                    return $m[0];
                }
                return $sample;
            },
            $path
        );

        if ($failed || null === $url || str_contains($url, '{')) {
            return null;
        }

        return $url;
    }

    /**
     * laminas route name => the Symfony route that took its URL, computed live.
     *
     * @return array<string, string>
     */
    private function takeovers(): array
    {
        $router    = $this->laminasRouter();
        $takenOver = [];

        foreach ($this->portedRoutes()->all() as $name => $route) {
            $url = $this->probeUrl($route);
            if (null === $url) {
                continue;
            }
            $request = new Request();
            $request->setUri('http://localhost' . $url);
            $match = $router->match($request);
            if (null === $match) {
                continue;
            }
            $takenOver[(string) $match->getMatchedRouteName()] ??= (string) $name;
        }

        return $takenOver;
    }

    /** @return array<string, mixed> */
    private function baseline(): array
    {
        self::assertFileExists(
            self::BASELINE,
            'docs/acl-baseline.json is the committed authorization snapshot; regenerate it with '
            . 'docker compose exec -T app php tools/acl-table.php --format=json'
        );

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) file_get_contents(self::BASELINE), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * Every laminas route a ported route actually displaces must appear in the
     * committed snapshot.
     *
     * A missing name is a guard that stopped running with nothing recording it,
     * which is the whole failure mode.
     */
    public function testTheBaselineAccountsForEveryRouteSymfonyTookOver(): void
    {
        $shadowed = [];
        /** @var list<array{laminas_route: string}> $rows */
        $rows = $this->baseline()['laminas_routes_shadowed_by_symfony'] ?? [];
        foreach ($rows as $row) {
            $shadowed[$row['laminas_route']] = true;
        }

        $missing = [];
        foreach ($this->takeovers() as $laminasRoute => $symfonyRoute) {
            if (! isset($shadowed[$laminasRoute])) {
                $missing[$laminasRoute] = $symfonyRoute;
            }
        }

        self::assertSame(
            [],
            $missing,
            "These laminas routes are served by a Symfony route, so their bjyauthorize guards no longer\n"
            . "run, and docs/acl-baseline.json does not record it. Regenerate the snapshot and review the\n"
            . "diff; if a row is genuinely new, check that the ported route re-declares the same ACL\n"
            . "resource before accepting it.\n"
        );
    }

    /**
     * The specific shape of the original bug: parameterized routes examined at all.
     *
     * A count assertion rather than a list, because the list is the snapshot's
     * job. Zero is the number the broken tool produced, and any refactor that
     * reintroduces pattern-matching-as-URL-matching lands back on it.
     */
    public function testParameterizedRoutesAreRepresentedInTheBaseline(): void
    {
        /** @var list<array{path: string}> $rows */
        $rows = $this->baseline()['laminas_routes_shadowed_by_symfony'] ?? [];

        $parameterized = array_filter($rows, static fn (array $row): bool => str_contains($row['path'], ':'));

        self::assertNotEmpty(
            $parameterized,
            'Not one shadowed row has a route parameter in its path. That is exactly what the baseline '
            . 'looked like while the shadow check was handing patterns to a URL matcher, and it means '
            . 'every /{id}/edit route is unexamined.'
        );
    }

    /**
     * No route may be left undecided.
     *
     * The tool reports a route it could neither confirm nor rule out as
     * uncomparable rather than skipping it, so that its silence about a route
     * means something. A non-empty list is a blind spot; if one is unavoidable
     * it belongs in docs/BACKLOG.md with a reason, and this assertion is where
     * that decision gets made deliberately.
     */
    public function testNoRouteIsLeftUncomparable(): void
    {
        $baseline = $this->baseline();

        self::assertArrayHasKey(
            'laminas_routes_uncomparable',
            $baseline,
            'The snapshot predates the uncomparable report; regenerate it.'
        );
        self::assertSame([], $baseline['laminas_routes_uncomparable']);
    }
}

<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use App\Sion\ReservedVerbs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

use function array_keys;
use function array_unique;
use function array_values;
use function is_array;
use function is_string;
use function preg_match;
use function sort;
use function str_contains;
use function str_starts_with;
use function substr;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The guard that makes `App\Sion\ReservedVerbs`' duplicated list affordable, and the
 * regression test for the bug it was written for.
 *
 * Batch 5 declared the four entity show pages as `/{sw_id}/{slug}` with the laminas
 * slug constraint `[a-z0-9-]{1,200}`, which matches `edit`. Symfony's `UrlMatcher`
 * takes the first matching route, so nine laminas verb routes became unreachable on
 * the Symfony front controller — production since 2026-08-11 — and their guards stopped
 * running. Nothing failed: the show page rendered, with a 200.
 *
 * Two independent assertions, because the bug needed both halves to happen:
 *
 * 1. **The list is complete.** Walk the laminas router configuration for every route
 *    whose path is `/:sw_id/<verb>` and fail if `ReservedVerbs::VERBS` does not name the
 *    verb. Adding a laminas verb route without adding it here is then a test failure
 *    rather than a page that silently answers the wrong thing. This is the same
 *    arrangement `test/Integration/SymfonyLocaleAliasTest` has for the locale aliases,
 *    and for the same reason: `config/symfony/routes.php` must not load laminas modules.
 * 2. **The collection actually routes them past the show pages**, asserted with a real
 *    `UrlMatcher` over the real `RouteCollection`. A correct list wired up wrongly would
 *    pass (1) and still serve the show page, and that is precisely the shape of the
 *    original defect — the hazard was noticed in a comment and the mitigation was not
 *    there.
 *
 * No database and no container: only the merged config and the route collection, so this
 * runs on a bare CI runner.
 */
class ReservedVerbsTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    // ------------------------------------------------------------ 1. the list is complete

    /**
     * Every `/:sw_id/<verb>` laminas route's verb is named in `ReservedVerbs::VERBS`.
     *
     * Compared as a set in both directions. A missing entry is the bug; a *stale* entry
     * is worth reporting too, because a verb nobody serves any more is a slug a visitor
     * is being needlessly refused — `/SL100319A/deleted-scenes` is fine, but a reserved
     * `delete` blocks the exact slug `delete`, and if no route wants it that block is
     * unpaid-for.
     */
    public function testEveryLaminasVerbRouteIsReserved(): void
    {
        $found = $this->laminasVerbs();

        $this->assertNotEmpty(
            $found,
            'no /:sw_id/<verb> routes were found in the laminas config at all, which means this '
            . 'test stopped looking where they live rather than that they stopped existing'
        );

        $reserved = ReservedVerbs::VERBS;
        sort($reserved);
        sort($found);

        $this->assertSame(
            $found,
            $reserved,
            'App\Sion\ReservedVerbs::VERBS has drifted from the laminas /:sw_id/<verb> routes. A verb '
            . 'missing from the list is swallowed by a ported /{sw_id}/{slug} show route and its guard '
            . 'never runs; a verb left in the list refuses a slug nothing needs reserved.'
        );
    }

    /** The list is sorted so that adding a verb is a one-line diff. */
    public function testTheVerbListIsSorted(): void
    {
        $sorted = ReservedVerbs::VERBS;
        sort($sorted);

        $this->assertSame($sorted, ReservedVerbs::VERBS);
        $this->assertSame(
            array_values(array_unique(ReservedVerbs::VERBS)),
            ReservedVerbs::VERBS,
            'a duplicate verb would make the generated pattern longer for no reason'
        );
    }

    // ------------------------------------------------------------ 2. the pattern behaves

    /**
     * The generated pattern refuses each reserved verb as a *whole* slug.
     *
     * Anchored the way Symfony anchors a requirement, because that is the only context
     * the pattern is ever used in.
     */
    #[DataProvider('reservedVerbs')]
    public function testTheSlugPatternRefusesAReservedVerb(string $verb): void
    {
        $this->assertSame(
            0,
            preg_match($this->anchored(), $verb),
            "'$verb' is a route verb and must not match the show route's slug"
        );
    }

    /**
     * **The lookahead is anchored, and this is the assertion that proves it.** Without
     * the `$` in `ReservedVerbs::slugPattern()`, every slug merely *starting* with a verb
     * is refused too — and `editorial` and `deleted-scenes` are both valid slugs of the
     * shape the laminas route allows. That failure mode is invisible from the routes
     * file and would show up as a 404 on a handful of records.
     *
     * @return list<array{string}>
     */
    public static function ordinarySlugs(): array
    {
        return [
            ['editorial'],
            ['deleted-scenes'],
            ['edits'],
            ['delete-me-not'],
            ['upload-cover-letter'],
            ['a'],
            ['nuestra-senora-de-schoenstatt'],
            ['150-preguntas'],
        ];
    }

    #[DataProvider('ordinarySlugs')]
    public function testTheSlugPatternStillAcceptsAnOrdinarySlug(string $slug): void
    {
        $this->assertSame(
            1,
            preg_match($this->anchored(), $slug),
            "'$slug' is an ordinary slug and must still reach its show page"
        );
    }

    // ------------------------------------- 3. the collection routes verbs to the bridge

    /**
     * The nine URLs the bug made unreachable, plus the two that were already correct.
     *
     * Each is matched against the real collection and must come out as `legacy`, i.e.
     * handed to laminas — except the ones that are genuinely ported, which must come out as
     * themselves. Identifiers are real rows in the capsule dump, and the shape is what
     * matters rather than the row.
     *
     * **An entry moves from `legacy` to a route name when its verb route is ported**, and
     * that is the whole reason this list names the expectation instead of just asserting
     * "not a show route". Batch 7 moved `text-edit` and `composition-edit`, each of which
     * failed this test on the way through and was meant to; the remaining `legacy` entries
     * are the routes still on laminas, and each becomes a one-line edit here as it moves.
     * What must *never* appear in this column is `composition`, `text`, `publication` or
     * `association` — a show route claiming a verb path is the bug this file exists for.
     *
     * @return array<string, array{string, string}>
     */
    public static function verbUrls(): array
    {
        return [
            //composition — `composition-edit` is ported (batch 7), `composition-delete` is not
            'composition edit is ported'  => ['/SL500001C/edit', 'composition-edit'],
            'composition delete'          => ['/SL500001C/delete', 'legacy'],
            //text — `text-edit` is ported (batch 7), `text-delete` is not
            'text edit is ported'         => ['/SL400003T/edit', 'text-edit'],
            'text delete'                 => ['/SL400003T/delete', 'legacy'],
            //publication, which has all five verbs
            'publication edit'            => ['/SL202186L/edit', 'legacy'],
            'publication delete'          => ['/SL202186L/delete', 'legacy'],
            'publication upload cover'    => ['/SL202186L/upload-cover', 'legacy'],
            'publication new edition'     => ['/SL202186L/create-new-edition', 'legacy'],
            'publication to main corpus'  => ['/SL202186L/copy-to-main-corpus', 'legacy'],
            //association: `edit` is ported and stays ported, `delete` bridges. Both were
            //already correct before the fix and are asserted so that a future reordering
            //of the routes file cannot quietly swap them.
            'association edit is ported'  => ['/SL100319A/edit', 'association-edit'],
            'association delete bridges'  => ['/SL100319A/delete', 'legacy'],
        ];
    }

    #[DataProvider('verbUrls')]
    public function testAVerbUrlIsNotSwallowedByAShowRoute(string $path, string $expected): void
    {
        $this->assertSame($expected, $this->matchedRoute($path));
    }

    /**
     * The same eleven under a locale prefix, which is the form every real caller uses —
     * and the form the bug was measured in. The prefixed twin is a separately declared
     * route, so it can be wrong on its own.
     */
    #[DataProvider('verbUrls')]
    public function testAPrefixedVerbUrlIsNotSwallowedEither(string $path, string $expected): void
    {
        $this->assertSame(
            'legacy' === $expected ? 'legacy' : $expected . '.locale',
            $this->matchedRoute('/en' . $path)
        );
    }

    /** A real slug still reaches the show page — the other half of not over-reserving. */
    public function testAnOrdinarySlugStillReachesTheShowRoute(): void
    {
        $this->assertSame('composition', $this->matchedRoute('/SL500001C/some-song-title'));
        $this->assertSame('composition.locale', $this->matchedRoute('/en/SL500001C/some-song-title'));
        $this->assertSame('publication.locale', $this->matchedRoute('/en/SL202186L/editorial-notes'));
    }

    // ---------------------------------------------------------------------------- helpers

    /** @return list<array{string}> */
    public static function reservedVerbs(): array
    {
        $cases = [];
        foreach (ReservedVerbs::VERBS as $verb) {
            $cases[] = [$verb];
        }

        return $cases;
    }

    private function anchored(): string
    {
        return '#^(?:' . ReservedVerbs::slugPattern() . ')$#';
    }

    private function matchedRoute(string $path): string
    {
        /** @var RouteCollection $collection */
        $collection = require __DIR__ . '/../../config/symfony/routes.php';

        $matched = (new UrlMatcher($collection, new RequestContext()))->match($path);

        return is_string($matched['_route'] ?? null) ? $matched['_route'] : '';
    }

    /**
     * Every distinct `<verb>` among the laminas routes whose path is `/:sw_id/<verb>`.
     *
     * Read off the merged `router.routes` config rather than the built router, so no
     * container and no database are involved. Nested child routes are walked too: none
     * of today's verb routes is a child, and a future one being a child is not a reason
     * for this test to stop seeing it.
     *
     * @return list<string>
     */
    private function laminasVerbs(): array
    {
        $verbs = [];

        $walk = static function (array $definitions) use (&$walk, &$verbs): void {
            foreach ($definitions as $definition) {
                if (! is_array($definition)) {
                    continue;
                }

                $route = $definition['options']['route'] ?? null;
                if (is_string($route) && str_starts_with($route, '/:sw_id/')) {
                    $verb = substr($route, 8);
                    //`/:sw_id/edit` yields `edit`. Anything with a further segment or a
                    //placeholder is not a verb route and is not this test's business.
                    if ('' !== $verb && ! str_contains($verb, '/') && ! str_contains($verb, ':')) {
                        $verbs[$verb] = true;
                    }
                }

                if (isset($definition['child_routes']) && is_array($definition['child_routes'])) {
                    $walk($definition['child_routes']);
                }
            }
        };

        /** @var array<string, mixed> $routes */
        $routes = $this->config()['router']['routes'] ?? [];
        $walk($routes);

        return array_keys($verbs);
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        if (null !== self::$config) {
            return self::$config;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        //Off, both of them: the module listener would otherwise write data/config/ — a
        //directory a bare CI runner has no business owning — and a later run would read
        //a stale merge. See docs/testing notes in test/Integration/AclGuardRouteDriftTest.
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        return self::$config = (new ServiceBridge($appConfig))->config();
    }
}

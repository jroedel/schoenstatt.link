<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ContainerFactory;
use Application\Navigation\PageBuilder;
use SionModel\Db\Connection;
use App\Services\Container;
use PHPUnit\Framework\TestCase;
use Throwable;

use function is_array;
use function is_readable;
use function is_scalar;
use function sprintf;
use function trim;

/**
 * No navigation page may declare an empty route parameter.
 *
 * ## The defect this pins
 *
 * `PageBuilder::publicationPages()` grouped publications by language and filed the ones with
 * no language under `null`, which PHP stores as the array key `''`. It then assembled
 * `publications/index` — `/literature/:inLanguage`, constrained to `[a-z]{2,2}` — with
 * `inLanguage => ''`, producing **`/en/literature/`, which answers 404** while
 * `/en/literature` answers 200.
 *
 * The reason a constrained route produced an unconstrained URL is the part worth knowing:
 * **`Laminas\Router\Http\Segment::assemble()` does not check the route's own constraints.**
 * They are enforced on `match()` only. So a parameter that could never have arrived in a
 * request can still be built into a link, and nothing anywhere reports it.
 *
 * ## Why this is asserted structurally, and not by fetching the URL
 *
 * Because for eleven months nothing rendered the bad node, and a test that only looks at
 * output would therefore have passed throughout. Measured against production 2026-08-14:
 * both navbars stop at depth 0 (`setMaxDepth(0)` in the laminas layout, `navigation_items()`
 * in the Twig one) and the group sits at depth 2; the Symfony breadcrumb omits it; the
 * laminas rendering of a publication page has no breadcrumb at all; and the sitemap dropped
 * it through the trailing-slash rule in `SitemapGenerator::isPublishable()`.
 *
 * That last one is the trap. The sitemap was the only thing standing between a 404 and
 * Google, and it held by a heuristic in a different component that was written for a
 * different reason. A malformed page is a defect where it is built, not where something else
 * happens to catch it.
 *
 * The invariant is deliberately broader than the one instance: *any* empty parameter in
 * *any* branch fails here, because the same `assemble()` behaviour applies to every route
 * with a segment, and the languageless group was only the first place a null key reached one.
 *
 * The children of that group — 25 real published publications — are re-parented rather than
 * dropped, and that half is covered end to end by
 * `SitemapSmokeTest::testMergedPublicationsAreExcluded()`, which compares the published set
 * against the database and fails if any publication goes missing.
 */
class NavigationRouteParametersTest extends TestCase
{
    private static ?Container $services = null;

    public function testNoNavigationPageDeclaresAnEmptyRouteParameter(): void
    {
        $branches = $this->branches();
        self::assertNotEmpty($branches, 'PageBuilder returned no branches, so this proves nothing');

        $offenders = [];
        foreach ($branches as $cacheKey => $branch) {
            $this->collectEmptyParameters(is_array($branch) ? $branch : [], (string) $cacheKey, $offenders);
        }

        self::assertSame(
            [],
            $offenders,
            "a navigation page declares an empty route parameter. Segment::assemble() does not\n"
            . "enforce the route's constraints, so this builds a URL with a hole in it — the\n"
            . "original was inLanguage => '' producing /en/literature/, which 404s."
        );
    }

    /**
     * Walk a branch, recording every page that would assemble with a hole in its URL.
     *
     * A page with no `params` is fine, and so is a page whose route takes none. What is not
     * fine is a declared parameter whose value is empty or whitespace: the router substitutes
     * it verbatim, so `:inLanguage` becomes nothing and two slashes collapse into one.
     *
     * @param array<mixed> $pages
     * @param list<string> $offenders
     */
    private function collectEmptyParameters(array $pages, string $path, array &$offenders): void
    {
        foreach ($pages as $key => $page) {
            if (! is_array($page)) {
                continue;
            }

            $here = $path . '/' . (is_scalar($key) ? (string) $key : '?');

            /** @var mixed $params */
            $params = $page['params'] ?? null;
            if (is_array($params)) {
                foreach ($params as $name => $value) {
                    //null is not an offender: laminas treats a missing optional parameter as
                    //absent and omits its segment, which is how `slug` works on publication
                    //routes. An empty *string* is the one that gets substituted.
                    if (is_scalar($value) && '' === trim((string) $value)) {
                        $offenders[] = sprintf(
                            '%s: route %s, parameter %s is empty (id %s, label %s)',
                            $here,
                            is_scalar($page['route'] ?? null) ? (string) $page['route'] : '(none)',
                            (string) $name,
                            is_scalar($page['id'] ?? null) ? (string) $page['id'] : '(none)',
                            is_scalar($page['label'] ?? null) ? (string) $page['label'] : '(none)'
                        );
                    }
                }
            }

            if (is_array($page['pages'] ?? null)) {
                $this->collectEmptyParameters($page['pages'], $here, $offenders);
            }
        }
    }

    /** @return array<string, mixed> */
    private function branches(): array
    {
        $this->requireDatabase();

        $services = self::services();

        return (new PageBuilder(
            static fn (string $id): mixed => $services->has($id) ? $services->get($id) : null
        ))->branches('en_US');
    }

    private static function services(): Container
    {
        if (null !== self::$services) {
            return self::$services;
        }

        /** @var array<string, mixed> $appConfig */
        $appConfig = require __DIR__ . '/../../config/application.config.php';
        //CI has no writable data/config, and a cached merged config would answer for a tree
        //built before the change under test

        $services = ContainerFactory::build($appConfig);

        return self::$services = $services;
    }

    /**
     * Skip rather than fail where there is no database.
     *
     * Every branch here is built from a table read — publications, associations, libraries,
     * compositions, dictionaries. CI has no `config/autoload/local.php` and no MariaDB, so
     * without this the class errors for a reason unrelated to what it asserts.
     */
    private function requireDatabase(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }

        try {
            /** @var Connection $adapter */
            $adapter = self::services()->get(Connection::class);
            $adapter->select('SELECT 1');
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }
    }
}

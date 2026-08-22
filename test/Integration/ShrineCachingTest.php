<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoenstatt\Model\SchoenstattTable;
use Throwable;

/**
 * The two shrine index pages share one cached read, and their order is per-locale.
 *
 * ## What was wrong
 *
 * `getShrines()` and `getWaysideShrines()` were verbatim copies of each other apart from
 * the kind — and apart from the cache, which only one of them had. Two separate defects
 * came out of that, and neither was visible in a rendered page:
 *
 * 1. **`/wayside-shrines` re-queried on every request.** No `fetchCachedEntityObjects()`,
 *    no `cacheEntityObjects()`. Measured on the capsule, a warm request was 0.15 s against
 *    0.034 s once cached, and the two queries it repeated were the entire population of
 *    non-key predicates against `sch_associations` in a 1,296-request sweep.
 * 2. **`getShrines()` cached a locale-sorted array under a locale-less key.** The rows
 *    themselves are locale-neutral — every translated value is a per-locale map — but the
 *    third sort key is the name *in the requested language*, so whichever language warmed
 *    the cache decided the order every other language saw.
 *
 * The fix is one shared private method that caches the linked rows unsorted and sorts on
 * the way out, so one item serves five languages and each gets its own order.
 *
 * ## What is asserted
 *
 * Not "it is fast" — that is a benchmark and it would rot. The two properties that make
 * the shared item legal, plus the one that says the cache exists at all.
 */
class ShrineCachingTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    /** Module loading the way bin/console does, config caches off — CI has no writable data/config. */
    private function bridge(): ServiceBridge
    {
        if (null !== self::$bridge) {
            return self::$bridge;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        return self::$bridge = new ServiceBridge($appConfig);
    }

    private function table(): SchoenstattTable
    {
        try {
            /** @var SchoenstattTable $table */
            $table = $this->bridge()->get(SchoenstattTable::class);
            //cheapest reachable statement: the suite must skip, not fail, with no database
            $table->getObjects('association');
        } catch (Throwable $e) {
            self::markTestSkipped('no reachable database: ' . $e->getMessage());
        }

        return $table;
    }

    /** @return array<string, array{0: string, 1: string}> method => the cache key it must register */
    public static function shrineMethods(): array
    {
        return [
            'shrines'         => ['getShrines', 'shrines-of-kind-sch-shrine'],
            'wayside shrines' => ['getWaysideShrines', 'shrines-of-kind-sch-wayside-shrine'],
        ];
    }

    /**
     * Both pages cache. This is the whole of defect 1: `getWaysideShrines()` registered
     * nothing, so every request repeated the `kind` query, the `Parent IN (...)` query
     * inside `linkAssociations()` and the row build.
     *
     */
    #[DataProvider('shrineMethods')]
    public function testTheReadIsCached(string $method, string $cacheKey): void
    {
        $table = $this->table();

        self::assertNotEmpty($table->$method(), 'sanity: there should be shrines to cache');
        self::assertNotNull(
            $table->fetchCachedEntityObjects($cacheKey),
            sprintf('%s() left no cache item under "%s", so the page re-queries on every '
                . 'request', $method, $cacheKey)
        );
    }

    /**
     * The one cached item serves every language, and each gets its own order.
     *
     * This is defect 2. Under the old code the second locale asked for here received the
     * first one's ordering, because the *sorted* array was what got cached and the key
     * did not name a language.
     *
     */
    #[DataProvider('shrineMethods')]
    public function testEachLocaleGetsItsOwnOrderFromTheSharedItem(string $method, string $cacheKey): void
    {
        $table = $this->table();

        $byLocale = [];
        foreach (['en_US', 'de_DE', 'es_ES'] as $locale) {
            $table->setLocale($locale);
            $byLocale[$locale] = $table->$method();
        }

        foreach ($byLocale as $locale => $objects) {
            self::assertNotEmpty($objects, "no shrines came back for $locale");
            $this->assertOrderedFor($objects, $locale, $method);
        }

        //Same rows in every language — only the order may differ. A shared cache item
        //that served different *content* per locale would be the opposite bug.
        $ids = [];
        foreach ($byLocale as $locale => $objects) {
            $these = array_column($objects, 'associationId');
            sort($these);
            $ids[$locale] = $these;
        }
        self::assertSame(
            array_values($ids)[0],
            $ids['de_DE'],
            'the languages disagree about which shrines exist, so the shared cache item is '
            . 'not locale-neutral after all'
        );
        self::assertSame(array_values($ids)[0], $ids['es_ES']);
    }

    /**
     * Every adjacent pair is non-decreasing on the triple the method sorts by.
     *
     * Written as the property rather than by rebuilding the expected list, so that it
     * keeps meaning something if the sort is reimplemented.
     *
     * @param array<int, array<string, mixed>> $objects
     */
    private function assertOrderedFor(array $objects, string $locale, string $method): void
    {
        $previous = null;
        foreach ($objects as $object) {
            $current = [
                $object['countryRegion'] ?? null,
                $object['country'] ?? null,
                $object['nameByLocale'][$locale] ?? null,
            ];
            if (null !== $previous) {
                self::assertLessThanOrEqual(
                    0,
                    $previous <=> $current,
                    sprintf(
                        '%s() is out of order for %s: %s came before %s',
                        $method,
                        $locale,
                        json_encode($previous, JSON_UNESCAPED_UNICODE),
                        json_encode($current, JSON_UNESCAPED_UNICODE)
                    )
                );
            }
            $previous = $current;
        }
    }

    /**
     * The role-title options are cached, and still come back in the database's order.
     *
     * The order is the point. Deriving these from the already-cached `getUnlinkedRoles()`
     * looks like the tidier fix and would change it: `utf8mb4_unicode_520_ci` is case- and
     * accent-insensitive, and no PHP sort reproduces it for free. A select box that
     * silently reorders is exactly the kind of change nothing else here would catch.
     */
    public function testRoleTitleOptionsAreCachedInTheDatabaseOrder(): void
    {
        $table = $this->table();

        $options = $table->getRoleTitleValueOptions();
        self::assertNotEmpty($options, 'sanity: there should be role titles');

        self::assertNotNull(
            $table->fetchCachedEntityObjects('role-title-value-options'),
            'getRoleTitleValueOptions() left no cache item, so it re-scans sch_roles on '
            . 'every render of the role form and the advanced search'
        );

        $collated = $table->fetchSome(
            null,
            "SELECT DISTINCT `RoleTitle` FROM `sch_roles` ORDER BY `RoleTitle`",
            null
        );
        self::assertSame(
            array_column($collated, 'RoleTitle'),
            array_values($options),
            'the role titles are no longer in the order the database collation gives, so '
            . 'the select options have silently reordered'
        );
    }
}

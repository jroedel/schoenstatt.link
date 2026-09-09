<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use App\Schoenstatt\ShrineIndex;
use Laminas\Db\Adapter\Adapter;
use Locale;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoenstatt\Model\SchoenstattTable;
use Throwable;

use function count;
use function is_readable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Every shrine lands in exactly one region of the /shrines index and is scored once.
 *
 * What is left of ShrineIndexParityTest once the laminas `SchoenstattController` it
 * compared against was deleted (laminas-exit.md, step 0). The parity half went with the
 * actions; this invariant never depended on them — it is what the grouping loop in
 * App\Schoenstatt\ShrineIndex is actually for, asserted over the real rows because a
 * handful of hand-made ones would exercise neither the regions nor the scoring.
 *
 * \Locale::setDefault() is set before anything is fetched because
 * SchoenstattTable::getShrines() indexes `nameByLocale` by it — under the CLI SAPI
 * the default is en_US_POSIX, and the sort would collapse on undefined keys. That is
 * the same reason App\Http\LocaleListener exists.
 */
class ShrineIndexInvariantsTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    public static function setUpBeforeClass(): void
    {
        Locale::setDefault('en_US');
    }

    /**
     * Everything here reads the shrines table, so a machine without a database
     * skips rather than fails. The local-config check comes *first* and on purpose:
     * without config/autoload/local.php, merely asking the container for the
     * adapter raises "Undefined array key db" — and `failOnWarning` is on, so a
     * warning is a failure no later catch can undo.
     */
    protected function setUp(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        try {
            /** @var Adapter $adapter */
            $adapter = $this->bridge()->get(Adapter::class);
            $adapter->getDriver()->getConnection()->connect();
            $this->bridge()->get(SchoenstattTable::class);
        } catch (Throwable $e) {
            self::markTestSkipped(
                'no reachable database: ' . $e->getMessage()
                . ' — this test needs the capsule up (docker compose up -d)'
            );
        }
    }

    /**
     * The two pages, each named by the table method that feeds it.
     *
     * @return array<string, array{0: string}>
     */
    public static function shrineTableProvider(): array
    {
        return [
            'shrines'         => ['getShrines'],
            'wayside shrines' => ['getWaysideShrines'],
        ];
    }

    #[DataProvider('shrineTableProvider')]
    public function testEveryShrineIsCountedInExactlyOneRegion(string $tableMethod): void
    {
        $shrines = $this->table()->$tableMethod();
        $this->assertNotEmpty($shrines, "no rows from $tableMethod — this test would prove nothing");

        $index = ShrineIndex::build($shrines);

        $grouped  = 0;
        $maxScore = 0;
        foreach ($index['regions'] as $region => $objects) {
            $grouped += count($objects);
            $this->assertArrayHasKey($region, $index['regionStats']);
            $this->assertSame(count($objects) * 10, $index['regionStats'][$region]['maxScore']);
            $maxScore += $index['regionStats'][$region]['maxScore'];
        }
        $this->assertSame(count($index['shrines']), $grouped);
        $this->assertSame(count($index['shrines']) * 10, $maxScore);
    }

    private function table(): SchoenstattTable
    {
        /** @var SchoenstattTable $table */
        $table = $this->bridge()->get(SchoenstattTable::class);

        return $table;
    }

    /**
     * The laminas services the way bin/console builds them: no bootstrap(), so no
     * MVC listeners and no dispatch. Config caches off for the reason
     * CacheStatusEndpointTest states — a test run must not write data/config/.
     */
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
}

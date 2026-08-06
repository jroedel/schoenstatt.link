<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use App\Schoenstatt\ShrineDatasets;
use App\Schoenstatt\ShrineIndex;
use Laminas\Db\Adapter\Adapter;
use JUser\Model\UserTable;
use Laminas\View\Model\ViewModel;
use Locale;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Schoenstatt\Controller\SchoenstattController;
use Schoenstatt\Model\SchoenstattTable;
use Throwable;

use function is_readable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * /shrines and /wayside-shrines are each answered by two front controllers, and this
 * pins that they assemble the same data.
 *
 * docs/strangler.md asks a route answering from two places to share the code that
 * builds the response. Here it deliberately does not: the laminas actions were left
 * byte-for-byte untouched so production — which still serves them, SYMFONY_KERNEL
 * being unset there — cannot be affected by the port at all. That leaves two copies
 * of the arithmetic, and this test is what makes the duplication safe: it drives
 * Schoenstatt\Controller\SchoenstattController::shrinesAction() and
 * waysideShrinesAction() against App\Schoenstatt\ShrineIndex over the same rows and
 * compares the results. When the laminas routes are finally deleted, the copies in
 * module/ go and this test goes with them.
 *
 * Both actions are driven because on the laminas side they are *duplicates* rather
 * than one shared implementation — the second is a verbatim copy of the first, down
 * to the commented-out `'form'` key. On the Symfony side there is only ShrineIndex,
 * so the pair of cases is also what would catch the copies drifting apart before the
 * port catches up with them.
 *
 * The laminas actions are reachable here because they are unusually self-contained:
 * they touch no controller plugin, no request and no route match, so they can be
 * constructed and called outside an MVC dispatch. getShrineDatasets() is protected
 * and is reached by reflection, which is the price of not editing it.
 *
 * \Locale::setDefault() is set before anything is fetched because
 * SchoenstattTable::getShrines() indexes `nameByLocale` by it — under the CLI SAPI
 * the default is en_US_POSIX, and the sort would collapse on undefined keys. That is
 * the same reason App\Http\LocaleListener exists.
 */
class ShrineIndexParityTest extends TestCase
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
     * The two pages, each named by the table method that feeds it and the laminas
     * action that answers it today.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function shrineIndexProvider(): array
    {
        return [
            'shrines'         => ['getShrines', 'shrinesAction'],
            'wayside shrines' => ['getWaysideShrines', 'waysideShrinesAction'],
        ];
    }

    /**
     * The same pages, for the assertions that need no laminas action to compare
     * against. Derived from the one above so the pair cannot drift.
     *
     * @return array<string, array{0: string}>
     */
    public static function shrineTableProvider(): array
    {
        return array_map(static fn (array $case): array => [$case[0]], self::shrineIndexProvider());
    }

    /**
     * The whole point: identical regions, identical per-region scores, identical
     * total. A change to either copy that alters any of it fails here.
     */
    #[DataProvider('shrineIndexProvider')]
    public function testTheTwoImplementationsAssembleTheSameShrineIndex(
        string $tableMethod,
        string $action
    ): void {
        $shrines = $this->table()->$tableMethod();
        $this->assertNotEmpty($shrines, "no rows from $tableMethod — this test would prove nothing");

        $ported = ShrineIndex::build($shrines);
        $legacy = $this->laminasViewModel($action)->getVariables();

        $this->assertSame(array_keys($legacy['regions']), array_keys($ported['regions']), 'region order');
        $this->assertEquals($legacy['regions'], $ported['regions'], 'shrines grouped by region');
        $this->assertEquals($legacy['regionStats'], $ported['regionStats'], 'per-region score, maxScore, percent');
        $this->assertSame($legacy['totalPercent'], $ported['totalPercent'], 'total percent');
        $this->assertEquals($legacy['shrines'], $ported['shrines'], 'the shrine list itself');
    }

    /**
     * Every shrine has to land in exactly one region and be counted once, which is
     * the invariant the grouping loop is actually for. Asserted independently of the
     * laminas copy, so a shared mistake could not hide behind the parity assertion.
     */
    #[DataProvider('shrineTableProvider')]
    public function testEveryShrineIsCountedInExactlyOneRegion(string $tableMethod): void
    {
        $index = ShrineIndex::build($this->table()->$tableMethod());

        $grouped = 0;
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

    /**
     * The schema.org payload is static content copied out of a protected method; pin
     * the copy. One payload, not two: waysideShrinesAction() publishes the *shrine*
     * datasets, because it calls the same getShrineDatasets(). Reproduced rather than
     * corrected — the assertion below is what says so.
     */
    public function testTheDatasetDescriptionsAreIdentical(): void
    {
        $method = new ReflectionMethod(SchoenstattController::class, 'getShrineDatasets');

        $this->assertSame($method->invoke($this->laminasController()), ShrineDatasets::build());
        $this->assertSame(
            ShrineDatasets::build(),
            $this->laminasViewModel('waysideShrinesAction')->getVariable('datasets'),
            'the wayside page publishes the shrine datasets, as its laminas action does'
        );
    }

    private function laminasViewModel(string $action): ViewModel
    {
        $view = $this->laminasController()->$action();
        $this->assertInstanceOf(ViewModel::class, $view);

        return $view;
    }

    private function laminasController(): SchoenstattController
    {
        /** @var array<string, mixed> $config */
        $config = $this->bridge()->get('config');
        /** @var UserTable $userTable */
        $userTable = $this->bridge()->get(UserTable::class);

        return new SchoenstattController($this->table(), $config, $userTable);
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
     * CacheStatusParityTest states — a test run must not write data/config/.
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

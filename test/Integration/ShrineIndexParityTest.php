<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use App\Schoenstatt\ShrineDatasets;
use App\Schoenstatt\ShrineIndex;
use JUser\Model\UserTable;
use Laminas\View\Model\ViewModel;
use Locale;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Schoenstatt\Controller\SchoenstattController;
use Schoenstatt\Model\SchoenstattTable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * /shrines is answered by two front controllers, and this pins that they assemble
 * the same data.
 *
 * docs/strangler.md asks a route answering from two places to share the code that
 * builds the response. Here it deliberately does not: the laminas action was left
 * byte-for-byte untouched so production — which still serves it, SYMFONY_KERNEL
 * being unset there — cannot be affected by the port at all. That leaves two copies
 * of the arithmetic, and this test is what makes the duplication safe: it drives
 * Schoenstatt\Controller\SchoenstattController::shrinesAction() and
 * App\Schoenstatt\ShrineIndex over the same rows and compares the results. When the
 * laminas route is finally deleted, the copy in module/ goes and this test goes with
 * it.
 *
 * The laminas action is reachable here because it is unusually self-contained: it
 * touches no controller plugin, no request and no route match, so it can be
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
     * The whole point: identical regions, identical per-region scores, identical
     * total. A change to either copy that alters any of it fails here.
     */
    public function testTheTwoImplementationsAssembleTheSameShrineIndex(): void
    {
        $shrines = $this->table()->getShrines();
        $this->assertNotEmpty($shrines, 'no shrines in the database — this test would prove nothing');

        $ported = ShrineIndex::build($shrines);
        $legacy = $this->laminasViewModel()->getVariables();

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
    public function testEveryShrineIsCountedInExactlyOneRegion(): void
    {
        $index = ShrineIndex::build($this->table()->getShrines());

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

    /** The schema.org payload is static content copied out of a protected method; pin the copy. */
    public function testTheDatasetDescriptionsAreIdentical(): void
    {
        $method = new ReflectionMethod(SchoenstattController::class, 'getShrineDatasets');

        $this->assertSame($method->invoke($this->laminasController()), ShrineDatasets::build());
    }

    private function laminasViewModel(): ViewModel
    {
        $view = $this->laminasController()->shrinesAction();
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

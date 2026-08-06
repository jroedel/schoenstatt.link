<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use App\Schoenstatt\AdminIndex;
use JTranslate\Model\TranslationsTable;
use Laminas\Db\Adapter\Adapter;
use Laminas\View\Model\ViewModel;
use Locale;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Schoenstatt\Controller\AdminController;
use SionModel\Service\ProblemService;
use Throwable;

use function is_readable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * /admin is answered by two front controllers, and this pins that they describe the
 * same page.
 *
 * The laminas action was left byte-for-byte untouched, because production still serves
 * it — `SYMFONY_KERNEL` is unset there — and editing it to share code would put a live
 * page at risk for the sake of the port. That leaves two copies of the link list, and
 * this test is what makes the duplication safe: it drives
 * `Schoenstatt\Controller\AdminController::indexAction()` and compares its ViewModel
 * against `App\Schoenstatt\AdminIndex`. Same pattern as ShrineIndexParityTest, same
 * disposal — when the laminas route goes, the action goes and this test goes with it.
 *
 * The laminas action is reachable here because it touches no controller plugin, no
 * request and no route match: `ControllerManager` builds it through
 * Schoenstatt\Controller\LazyControllerFactory, and `indexAction()` can then simply be
 * called. What it does need is the database, for the two counters.
 *
 * \Locale::setDefault() first, for the reason App\Http\LocaleListener exists: under
 * the CLI SAPI the default is en_US_POSIX, and anything reading a by-locale array
 * misses.
 *
 * `#[IgnoreDeprecations]` on all three tests, and it is not a shrug. Calling the
 * laminas action reaches Books\Model\LibraryOptions and SionModel\Db\Model\SionTable
 * through `ProblemService::getCurrentProblems()`, which between them trigger 14 further
 * PHP 8.5 deprecation reports — dynamic property creation, `DateTime(null)`. They are
 * pre-existing and have nothing to do with authorization: every web request that
 * renders this page emits the same ones, they are invisible to the smoke suite only
 * because they happen in the Apache process, and production is on 8.4 where some do not
 * exist yet. The integration suite already carries 9 of the same kind from
 * ShrineIndexParityTest and TranslationUpdateScopeTest; adding 14 more to a summary
 * nobody can act on is how that line stops being read. Cleaning them up belongs to the
 * legacy tree's own modernization, not to a parity test — and when it happens, deleting
 * these three attributes is how it gets noticed here.
 */
class AdminIndexParityTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    public static function setUpBeforeClass(): void
    {
        Locale::setDefault('en_US');
    }

    /**
     * Both counters read the database, so a machine without one skips rather than
     * fails. The local-config check comes first on purpose: without
     * config/autoload/local.php, merely asking for the adapter raises "Undefined array
     * key db", and `failOnWarning` makes a warning a failure no later catch can undo.
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
        } catch (Throwable $e) {
            self::markTestSkipped(
                'no reachable database: ' . $e->getMessage()
                . ' — this test needs the capsule up (docker compose up -d)'
            );
        }
    }

    /**
     * The list itself: same routes, same order, same labels. An assertSame on the whole
     * array rather than a count, because the order is what the page renders and a
     * reordered list is a changed page.
     */
    #[IgnoreDeprecations]
    public function testTheTwoCopiesListTheSamePagesInTheSameOrder(): void
    {
        $legacy = $this->laminasViewModel()->getVariable('pages');

        $this->assertSame($legacy, AdminIndex::PAGES);
    }

    /**
     * And the counters, values and types both. The types are the original's oddity —
     * the translation count is cast to string, the problem count left an int — and
     * they are kept rather than tidied precisely so this comparison stays honest about
     * what the laminas page produces.
     */
    #[IgnoreDeprecations]
    public function testTheTwoCopiesComputeTheSameBadges(): void
    {
        /** @var TranslationsTable $translations */
        $translations = $this->bridge()->get(TranslationsTable::class);
        /** @var ProblemService $problems */
        $problems = $this->bridge()->get(ProblemService::class);

        $this->assertSame(
            $this->laminasViewModel()->getVariable('badges'),
            AdminIndex::badges($translations, $problems)
        );
    }

    /**
     * Independent of the parity assertion, so a shared mistake could not hide behind
     * it: every badge key must be a route the page actually lists, or the badge renders
     * nowhere and the counter is dead weight.
     */
    #[IgnoreDeprecations]
    public function testEveryBadgeBelongsToAListedPage(): void
    {
        /** @var TranslationsTable $translations */
        $translations = $this->bridge()->get(TranslationsTable::class);
        /** @var ProblemService $problems */
        $problems = $this->bridge()->get(ProblemService::class);

        foreach (array_keys(AdminIndex::badges($translations, $problems)) as $route) {
            $this->assertArrayHasKey($route, AdminIndex::PAGES, "badge for unlisted route $route");
        }
    }

    private function laminasViewModel(): ViewModel
    {
        /** @var AdminController $controller */
        $controller = $this->bridge()->get('ControllerManager')->get(AdminController::class);
        $view       = $controller->indexAction();
        $this->assertInstanceOf(ViewModel::class, $view);

        return $view;
    }

    /** Config caches off, for the reason CacheStatusParityTest states: no test may write data/config/. */
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

<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use App\Schoenstatt\AdminIndex;
use JTranslate\Model\TranslationsTable;
use SionModel\Db\Connection;
use Locale;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use SionModel\Service\ProblemService;
use Throwable;

use function is_readable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The /admin page's badges name pages the page actually lists.
 *
 * What is left of AdminIndexParityTest once the laminas `AdminController` it compared
 * against was deleted (laminas-exit.md, step 0). The parity half went with the action;
 * this assertion never depended on it: a badge keyed by a route the list does not
 * contain renders nowhere, and the counter behind it is dead weight nobody sees.
 *
 * \Locale::setDefault() first, for the reason App\Http\LocaleListener exists: under
 * the CLI SAPI the default is en_US_POSIX, and anything reading a by-locale array
 * misses.
 *
 * `#[IgnoreDeprecations]`, and it is not a shrug: `ProblemService::getCurrentProblems()`
 * reaches Books\Model\LibraryOptions and SionModel\Db\Model\SionTable, which between
 * them emit PHP 8.5 deprecation reports (dynamic property creation, `DateTime(null)`)
 * that every web request rendering this page emits too. Cleaning them up belongs to the
 * legacy tree's own modernization; deleting the attribute is how it gets noticed here.
 */
class AdminIndexBadgesTest extends TestCase
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
            /** @var Connection $adapter */
            $adapter = $this->bridge()->get(Connection::class);
            $adapter->select('SELECT 1');
        } catch (Throwable $e) {
            self::markTestSkipped(
                'no reachable database: ' . $e->getMessage()
                . ' — this test needs the capsule up (docker compose up -d)'
            );
        }
    }

    #[IgnoreDeprecations]
    public function testEveryBadgeBelongsToAListedPage(): void
    {
        /** @var TranslationsTable $translations */
        $translations = $this->bridge()->get(TranslationsTable::class);
        /** @var ProblemService $problems */
        $problems = $this->bridge()->get(ProblemService::class);

        $badges = AdminIndex::badges($translations, $problems);
        $this->assertNotEmpty($badges, 'no badges at all; the assertion below would be vacuous');

        foreach (array_keys($badges) as $route) {
            $this->assertArrayHasKey($route, AdminIndex::PAGES, "badge for unlisted route $route");
        }
    }

    /** Config caches off, for the reason CacheStatusEndpointTest states: no test may write data/config/. */
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

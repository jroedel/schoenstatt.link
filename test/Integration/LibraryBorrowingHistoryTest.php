<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ContainerFactory;
use App\Laminas\ServiceBridge;
use App\Services\Container;
use Books\Model\LibraryTable;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * `LibraryTable::getBorrowingHistory()`, which decides who a `library-borrowers` library
 * may lend to (docs/libraries.md; the rule over it is LibraryBorrowersRuleTest).
 *
 * The method groups checkout/library pairs and folds them in PHP; this asks the same
 * question as one aggregate per person and requires the two to agree for every library.
 * A join on the wrong column, or a fold that let a later "elsewhere" overwrite an earlier
 * "here", would offer a father to a library he never borrowed from, or hide him from his
 * own.
 *
 * **Nothing is written.** Needs a database; skips without one. CI's seed has no checkouts,
 * so there it proves only that the query runs; the capsule's data is the real test.
 */
final class LibraryBorrowingHistoryTest extends TestCase
{
    use RequiresDatabase;

    private static ?Container $container = null;

    public function testTheHistoryAgreesWithAnAggregatePerPerson(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        $bridge  = ServiceBridge::around(self::container());
        $adapter = $this->requireDatabase($bridge);

        /** @var LibraryTable $table */
        $table = $bridge->get(LibraryTable::class);

        $libraryIds = [];
        foreach ($adapter->select('SELECT LibraryId FROM lib_libraries ORDER BY LibraryId') as $row) {
            $libraryIds[] = (int) $row['LibraryId'];
        }
        self::assertNotSame([], $libraryIds, 'no libraries to ask about');

        foreach ($libraryIds as $libraryId) {
            $expected = [];
            $rows     = $adapter->select(
                'SELECT c.PersonId, MAX(b.library_id = ?) AS here
                 FROM lib_checkouts c JOIN lib_books b ON b.book_id = c.BookId
                 WHERE c.PersonId IS NOT NULL AND c.PersonId <> 0
                 GROUP BY c.PersonId',
                [$libraryId]
            );
            foreach ($rows as $row) {
                $expected[(int) $row['PersonId']] = 1 === (int) $row['here'];
            }

            $actual = $table->getBorrowingHistory($libraryId);
            ksort($expected);
            ksort($actual);

            self::assertSame($expected, $actual, sprintf('library %d', $libraryId));
        }
    }

    private static function container(): Container
    {
        if (null === self::$container) {
            /** @var array<string, mixed> $appConfig */
            $appConfig = require __DIR__ . '/../../config/application.config.php';
            $appConfig['module_listener_options']['config_cache_enabled']     = false;
            $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

            self::$container = ContainerFactory::build($appConfig);
        }

        return self::$container;
    }
}

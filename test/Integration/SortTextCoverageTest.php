<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use Books\Model\LibraryTable;
use SionModel\Db\Connection;
use PHPUnit\Framework\TestCase;
use Throwable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * What the auto-fix page knows about books with no sort text, and what it admits to.
 *
 * `LibraryTable::autoFixProblems()` backfills `lib_books.sort_text` (the route that ran it,
 * `sion-model/auto-fix-data-problems`, was retired 2026-09-08 — `refresh-sort` recomputes
 * every book's sort text and is a strict superset of it). Measured on the
 * capsule 2026-08-21: **27,910 active books have no sort text**, the page offers to fix
 * 9,764 of them — every one in Colegio Mayor — and said nothing whatever about the other
 * 17,207, including the whole of PUC's 16,383-book catalogue. The number on screen read
 * as the size of the problem when it was 35% of it.
 *
 * Nothing here writes: the coverage query is read-only and `autoFixProblems(true)`
 * simulates, which is the page's own default. The counts are compared against SQL rather
 * than against constants, so the test keeps working as the catalogue changes and fails
 * only when the *relationship* between the numbers breaks.
 */
class SortTextCoverageTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    protected function setUp(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        try {
            /** @var Connection $adapter */
            $adapter = $this->bridge()->get(Connection::class);
            $adapter->select('SELECT 1');
            $this->bridge()->get(LibraryTable::class);
        } catch (Throwable $e) {
            self::markTestSkipped(
                'no reachable database: ' . $e->getMessage()
                . ' — this test needs the capsule up (docker compose up -d)'
            );
        }
    }

    /**
     * The two halves account for every book and nothing else. This is what makes the
     * split trustworthy: a bug that dropped a (library, collection) group would show up
     * here as a shortfall rather than as a quietly smaller number on a page.
     */
    public function testCoverageAccountsForEveryBookMissingSortText(): void
    {
        $coverage = $this->table()->getSortTextCoverage();
        $expected = $this->countMissingSortText();

        $this->assertSame(
            $expected,
            $coverage['withFormat'] + $coverage['withoutFormat'],
            'every active book with no sort text belongs to exactly one of the two buckets'
        );
        $this->assertSame(
            $expected,
            array_sum(array_map(
                static fn (array $c): int => $c['withFormat'] + $c['withoutFormat'],
                $coverage['byLibrary']
            )),
            'the per-library breakdown must add up to the same total'
        );
    }

    /**
     * `withFormat` is an upper bound on what the page will write, never an underestimate.
     * A format can decline an individual call number its regex does not match, so the
     * real figure is lower — 9,764 against 10,703 when this was written. The inequality
     * is the contract; the gap is data.
     */
    public function testWhatTheAutoFixWritesNeverExceedsWhatHasAFormat(): void
    {
        $table = $this->table();

        $this->assertLessThanOrEqual(
            $table->getSortTextCoverage()['withFormat'],
            count($table->autoFixProblems(true)),
            'the auto-fix cannot write a sort text for a book whose library has no format'
        );
    }

    /**
     * The reason this file exists. Every library holding books it cannot fix has to say
     * so, on both the global report and its own page — `getLibraryProblems()` is what
     * LibrariesController::getProblemCounts() calls, and it is the only one it calls.
     */
    public function testEveryLibraryWithUnfixableBooksReportsIt(): void
    {
        $table    = $this->table();
        $coverage = $table->getSortTextCoverage();
        $expected = [];
        foreach ($coverage['byLibrary'] as $libraryId => $counts) {
            if ($counts['withoutFormat'] > 0) {
                $expected[] = $libraryId;
            }
        }
        if ([] === $expected) {
            self::markTestSkipped('every book with no sort text has a format, so there is nothing to report');
        }

        $reported = [];
        foreach ($table->getObjects('library') as $libraryId => $libraryObject) {
            foreach ($table->getLibraryProblems($libraryObject) as $problem) {
                if (LibraryTable::PROBLEM_LIBRARY_MISSING_SORT_TEXT_FORMAT === $problem->getProblem()) {
                    $reported[] = $libraryId;
                }
            }
        }

        sort($expected);
        sort($reported);
        $this->assertSame(
            $expected,
            $reported,
            'a library with books it cannot give a sort text must appear on the data-problems report'
        );
    }

    /**
     * The auto-fix labels its rows as books missing sort text. It reported
     * `collection-invalid-call-number-format` until 2026-08-21 — the wrong entity and the
     * wrong fault at once, sending an administrator to look at a collection that is fine.
     */
    public function testTheAutoFixNamesTheProblemItIsActuallyFixing(): void
    {
        $problems = $this->table()->autoFixProblems(true);
        if ([] === $problems) {
            self::markTestSkipped('nothing to auto-fix in this dataset');
        }

        //On the distinct set, not per row: 9,764 identical assertions prove nothing the
        //first one did not, and they make the suite's assertion count meaningless.
        $codes = $entities = [];
        foreach ($problems as $problem) {
            $codes[$problem->getProblem()]  = true;
            $entities[$problem->getEntity()] = true;
        }

        $this->assertSame([LibraryTable::PROBLEM_BOOK_MISSING_SORT_TEXT], array_keys($codes));
        $this->assertSame(['book'], array_keys($entities));
    }

    private function countMissingSortText(): int
    {
        /** @var Connection $adapter */
        $adapter = $this->bridge()->get(Connection::class);
        $result  = $adapter->select(
            'SELECT COUNT(*) AS affected FROM lib_books WHERE is_active = 1 AND sort_text IS NULL'
        );
        /** @var array{affected: int|string} $row */
        $row = $result->current();

        return (int) $row['affected'];
    }

    private function table(): LibraryTable
    {
        /** @var LibraryTable $table */
        $table = $this->bridge()->get(LibraryTable::class);

        return $table;
    }

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

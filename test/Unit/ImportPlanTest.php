<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use App\Books\Import\ColumnMap;
use App\Books\Import\ImportPlan;
use App\Books\Import\ImportProblem;
use App\Books\Import\PlannedRow;
use PHPUnit\Framework\TestCase;

use function array_slice;
use function count;

require_once __DIR__ . '/../../src/Books/Import/ImportColumn.php';
require_once __DIR__ . '/../../src/Books/Import/ImportColumns.php';
require_once __DIR__ . '/../../src/Books/Import/ColumnMap.php';
require_once __DIR__ . '/../../src/Books/Import/ImportProblem.php';
require_once __DIR__ . '/../../src/Books/Import/PlannedRow.php';
require_once __DIR__ . '/../../src/Books/Import/ImportPlan.php';

/**
 * What a plan reports about itself, and what the confirmation is a confirmation *of*.
 *
 * The distinction this file exists for is `update` versus `update-unchanged`. The old
 * preview reported 10,091 "Records updated" for import 3, of which 8,494 were books the
 * spreadsheet changed nothing about — so the number a librarian read was four parts noise
 * to one part signal, and the page listed all 10,091 of them in a 5.27 MB table.
 */
class ImportPlanTest extends TestCase
{
    private function plan(bool $complete = false): ImportPlan
    {
        return new ImportPlan(
            new ColumnMap(['ID', 'Titulo'], ['withinLibraryId' => 0, 'title' => 1]),
            [
                new PlannedRow(action: PlannedRow::CREATE, rowNumber: 2, withinLibraryId: 10),
                new PlannedRow(
                    action: PlannedRow::UPDATE,
                    rowNumber: 3,
                    bookId: 500,
                    withinLibraryId: 11,
                    changes: ['title' => ['Old', 'New']]
                ),
                //Matched, and the spreadsheet says nothing new about it.
                new PlannedRow(action: PlannedRow::UPDATE, rowNumber: 4, bookId: 501, withinLibraryId: 12),
                new PlannedRow(
                    action: PlannedRow::ERROR,
                    rowNumber: 5,
                    reason: ImportProblem::TITLE_MISSING
                ),
                new PlannedRow(action: PlannedRow::INACTIVATE, bookId: 502, withinLibraryId: 13),
                new PlannedRow(action: PlannedRow::CREATE_COLLECTION, collection: 'Depósito'),
            ],
            [],
            $complete,
            4
        );
    }

    public function testMatchedAndChangedAreCountedSeparately(): void
    {
        $statistics = $this->plan()->statistics();

        self::assertSame(2, $statistics['update'], 'every row matched to a book');
        self::assertSame(1, $statistics['update-unchanged'], 'of which this many change nothing');
        self::assertSame(1, $statistics['create']);
        self::assertSame(1, $statistics['inactivate']);
        self::assertSame(1, $statistics['error']);
        self::assertSame(1, $statistics['create-collection']);
    }

    public function testOnlyRowsThatWouldWriteAreCountedAsWrites(): void
    {
        //An error writes nothing and an unchanged match writes nothing; the create, the
        //real change, the inactivation and the new collection do.
        self::assertSame(4, $this->plan()->writeCount());
    }

    public function testNotableLeavesOutTheRowsWithNothingToSay(): void
    {
        $notable = $this->plan()->notable();

        self::assertCount(5, $notable);
        foreach ($notable as $row) {
            self::assertFalse($row->isUnchanged());
        }
    }

    public function testErrorsAreReachableOnTheirOwn(): void
    {
        $errors = $this->plan()->errors();

        self::assertCount(1, $errors);
        self::assertSame(ImportProblem::TITLE_MISSING, $errors[0]->reason);
    }

    public function testAPlanWithBlockersRefusesToBeApplied(): void
    {
        $blocked = new ImportPlan(
            new ColumnMap([], []),
            [],
            [['reason' => ImportProblem::BARCODES_DUPLICATED, 'params' => ['35025']]]
        );

        self::assertFalse($blocked->canApply());
        self::assertTrue($this->plan()->canApply());
    }

    /**
     * The digest is over the counts, and only the counts.
     *
     * It is what stands between a librarian confirming "1,281 books will be retired" and
     * an import applied against a catalogue that moved in between. Hashing every value
     * instead would make an unrelated typo-fix in one row invalidate a confirmation whose
     * numbers had not budged, and a confirmation that expires for no visible reason is one
     * people learn to click through.
     */
    public function testTheDigestFollowsTheCountsAndNothingElse(): void
    {
        $first  = $this->plan();
        $second = $this->plan();

        self::assertSame($first->digest(), $second->digest(), 'the same counts must digest the same');

        //Same counts, different content in one row.
        $reworded = new ImportPlan(
            $first->map,
            [
                new PlannedRow(action: PlannedRow::CREATE, rowNumber: 2, withinLibraryId: 99),
                ...array_slice($first->rows, 1),
            ],
            [],
            false,
            4
        );
        self::assertSame($first->digest(), $reworded->digest());

        //A different number of retirements is a different question being asked.
        $withOneMore = new ImportPlan(
            $first->map,
            [...$first->rows, new PlannedRow(action: PlannedRow::INACTIVATE, bookId: 503)],
            [],
            false,
            4
        );
        self::assertNotSame($first->digest(), $withOneMore->digest());
    }

    public function testTheCompleteImportFlagIsPartOfTheDigest(): void
    {
        //Two plans can carry identical counts and mean different things: the flag decides
        //whether books missing from the sheet get retired, and it is settable between the
        //preview and the click.
        self::assertNotSame($this->plan(false)->digest(), $this->plan(true)->digest());
    }

    public function testARowKnowsWhetherItWrites(): void
    {
        $unchanged = new PlannedRow(action: PlannedRow::UPDATE, bookId: 1);
        $error     = new PlannedRow(action: PlannedRow::ERROR, reason: ImportProblem::BARCODE_MISSING);
        $changed   = new PlannedRow(action: PlannedRow::UPDATE, bookId: 1, changes: ['title' => ['a', 'b']]);

        self::assertTrue($unchanged->isUnchanged());
        self::assertFalse($unchanged->writes());
        self::assertFalse($error->writes());
        self::assertTrue($changed->writes());
        //An error is never "unchanged" — that word is only about a matched book.
        self::assertFalse($error->isUnchanged());
    }

    public function testARowsTitleIsAlwaysAString(): void
    {
        self::assertSame('', (new PlannedRow(action: PlannedRow::CREATE))->title());
        self::assertSame(
            'Leben im Bund',
            (new PlannedRow(action: PlannedRow::CREATE, values: ['title' => 'Leben im Bund']))->title()
        );
        //The review page prints this straight into a table cell, so a non-string value
        //from a spreadsheet must not reach it.
        self::assertSame('', (new PlannedRow(action: PlannedRow::CREATE, values: ['title' => 1988]))->title());
    }

    public function testGroupingByActionIsExhaustive(): void
    {
        $plan  = $this->plan();
        $total = 0;
        foreach ([
            PlannedRow::CREATE,
            PlannedRow::UPDATE,
            PlannedRow::INACTIVATE,
            PlannedRow::ERROR,
            PlannedRow::CREATE_COLLECTION,
        ] as $action) {
            $total += count($plan->withAction($action));
        }

        self::assertSame(count($plan->rows), $total, 'every row belongs to exactly one group');
    }
}

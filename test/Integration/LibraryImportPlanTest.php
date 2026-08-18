<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Books\Import\ImportProblem;
use App\Books\Import\LibraryImporter;
use App\Books\Import\PlannedRow;
use Books\Model\LibraryTable;
use Books\Model\PublicationsTable;
use Books\Service\SpreadsheetReader;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;
use Throwable;

use function array_key_first;
use function array_slice;
use function count;
use function is_readable;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * What the importer decides, against a real library.
 *
 * ## Nothing here writes, and that is asserted rather than assumed
 *
 * `plan()` is the half of the engine that only reads, and the split is the point: the old
 * `importSpreadsheetFile()` took a `&$simulate` flag it also wrote to, so "tell me what
 * would happen" and "do it" were the same code path with the same failure modes, and the
 * caller learned the file had been refused by watching the flag change. Every test here
 * counts `lib_books` before and after.
 *
 * The library under test is **7** (Schoenstatt University Men, 126 books) because it is
 * the smallest, and every barcode used for a "new" book is far outside its range.
 *
 * ## Why the spreadsheets are built here rather than committed
 *
 * `data/import/` is gitignored and always has been, so the fourteen real files cannot be
 * fixtures. Building them in the test also puts the header row in the test source, which
 * is where a reader needs it.
 */
class LibraryImportPlanTest extends TestCase
{
    private const LIBRARY = 7;

    private static ?ServiceManager $services = null;
    /** @var list<string> */
    private array $written = [];

    private function services(): ServiceManager
    {
        if (null !== self::$services) {
            return self::$services;
        }
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no local configuration; this test needs a database');
        }
        try {
            $appConfig = require __DIR__ . '/../../config/application.config.php';
            $appConfig['module_listener_options']['config_cache_enabled']     = false;
            $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

            $services = new ServiceManager();
            (new ServiceManagerConfig($appConfig['service_manager'] ?? []))->configureServiceManager($services);
            $services->setService('ApplicationConfig', $appConfig);
            $services->get('ModuleManager')->loadModules();

            /** @var AdapterInterface $adapter */
            $adapter = $services->get('Laminas\Db\Adapter\Adapter');
            $adapter->query('SELECT 1', []);

            return self::$services = $services;
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }
    }

    private function importer(): LibraryImporter
    {
        $services = $this->services();

        return new LibraryImporter(
            $services->get(LibraryTable::class),
            $services->get(PublicationsTable::class),
            $services->get(SpreadsheetReader::class)
        );
    }

    private function bookCount(): int
    {
        /** @var AdapterInterface $adapter */
        $adapter = $this->services()->get('Laminas\Db\Adapter\Adapter');
        $result  = $adapter->query('SELECT COUNT(*) AS c FROM lib_books', [])->current();

        return (int) $result['c'];
    }

    /**
     * @param list<string> $headers
     * @param list<list<string|int|null>> $rows
     */
    private function sheet(array $headers, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Books');
        $sheet->fromArray($headers, null, 'A1');
        $line = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, 'A' . $line);
            $line++;
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'import-plan-') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->written[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            @unlink($path);
        }
    }

    /** An existing barcode in library 7, whatever it happens to be. */
    private function anExistingBarcode(): int
    {
        /** @var LibraryTable $table */
        $table  = $this->services()->get(LibraryTable::class);
        $lookup = $table->getLibraryBookLookupWithActive(self::LIBRARY);
        self::assertNotSame([], $lookup, 'library 7 has no books; this test needs some');

        return (int) array_key_first($lookup);
    }

    public function testAKnownBarcodeUpdatesAndAnUnknownOneCreates(): void
    {
        $existing = $this->anExistingBarcode();
        $before   = $this->bookCount();

        $path = $this->sheet(
            ['Barcode', 'Title', 'Publisher'],
            [
                [$existing, 'Whatever this book is called', 'A PUBLISHER THAT IS NOT STORED'],
                [999001, 'A book with a barcode nothing has', 'Another'],
            ]
        );

        $plan = $this->importer()->plan(self::LIBRARY, $path, 'Books');

        self::assertSame([], $plan->blockers);
        self::assertSame(1, $plan->statistics()[PlannedRow::UPDATE]);
        self::assertSame(1, $plan->statistics()[PlannedRow::CREATE]);
        self::assertSame(0, $plan->statistics()[PlannedRow::ERROR]);

        $update = $plan->withAction(PlannedRow::UPDATE)[0];
        self::assertSame($existing, $update->withinLibraryId);
        self::assertNotNull($update->bookId);
        //The change list is what the review page shows, so it has to name the fields the
        //spreadsheet actually moves and not every field it mentions.
        self::assertArrayHasKey('publisher', $update->changes);

        self::assertSame($before, $this->bookCount(), 'plan() must write nothing');
    }

    public function testABlankBarcodeIsThatRowsOwnErrorAndNotTheFilesProblem(): void
    {
        //The defect this replaces: `(int) $cell` ran before the `is_numeric()` meant to
        //catch it, so a blank barcode became barcode 0 and was planned as a *new book*.
        //Import 1 still contains one such row. Two of them collided at 0 and blocked the
        //whole file with a message about duplicate barcodes.
        $path = $this->sheet(
            ['Barcode', 'Title'],
            [
                ['', 'A row somebody left the barcode off'],
                ['not a number', 'And one with a barcode that is not one'],
                [999002, 'A perfectly good row'],
            ]
        );

        $plan = $this->importer()->plan(self::LIBRARY, $path, 'Books');

        self::assertSame([], $plan->blockers, 'a bad row must not stop the rest of the file');
        self::assertSame(2, $plan->statistics()[PlannedRow::ERROR]);
        self::assertSame(1, $plan->statistics()[PlannedRow::CREATE]);
        foreach ($plan->errors() as $row) {
            self::assertSame(ImportProblem::BARCODE_MISSING, $row->reason);
            self::assertNull($row->withinLibraryId);
        }
    }

    public function testADuplicateBarcodeStopsTheWholeFile(): void
    {
        $path = $this->sheet(
            ['Barcode', 'Title'],
            [[999003, 'First'], [999004, 'Second'], [999003, 'The same barcode again']]
        );

        $plan = $this->importer()->plan(self::LIBRARY, $path, 'Books');

        //Reproduced from the old engine deliberately: a barcode is the only thing a row is
        //matched on, so two rows claiming one is a file whose meaning is undefined.
        self::assertFalse($plan->canApply());
        self::assertSame(ImportProblem::BARCODES_DUPLICATED, $plan->blockers[0]['reason']);
        //The offending row still says which one it is, and the rest of the plan is still
        //rendered — seeing what would have happened is how the file gets fixed.
        self::assertSame(ImportProblem::BARCODE_DUPLICATED, $plan->errors()[0]->reason);
        self::assertSame(4, $plan->errors()[0]->rowNumber, 'the row number Excel shows in its margin');
    }

    public function testARowWithNoTitleAndNoLiteratureRecordSaysWhich(): void
    {
        $path = $this->sheet(
            ['Barcode', 'Title', 'Literature ID'],
            [
                [999005, '', ''],
                //A publication id nothing will resolve. Six of import 3's eight errors are
                //this, and the page's own instructions call the case supported — which it
                //is, but only when the record exists.
                [999006, '', 999999999],
            ]
        );

        $plan = $this->importer()->plan(self::LIBRARY, $path, 'Books');

        self::assertSame(2, $plan->statistics()[PlannedRow::ERROR]);
        self::assertSame(ImportProblem::TITLE_MISSING, $plan->errors()[0]->reason);
        self::assertSame(ImportProblem::LITERATURE_ID_UNKNOWN, $plan->errors()[1]->reason);
        self::assertSame(['999999999'], $plan->errors()[1]->reasonParams);
    }

    public function testACompleteImportRetiresWhatTheSheetOmitsAndSparesWhatErrored(): void
    {
        /** @var LibraryTable $table */
        $table  = $this->services()->get(LibraryTable::class);
        $lookup = $table->getLibraryBookLookupWithActive(self::LIBRARY);
        $active = [];
        foreach ($lookup as $barcode => $info) {
            if (true === ($info['isActive'] ?? false)) {
                $active[] = (int) $barcode;
            }
        }
        self::assertGreaterThan(3, count($active), 'this test needs a few active books');

        $kept    = array_slice($active, 0, 1);
        $errored = array_slice($active, 1, 1);

        $path = $this->sheet(
            ['Barcode', 'Title'],
            [
                [$kept[0], 'Still here'],
                //Listed, but unimportable. It must not be swept: a typo cannot be allowed
                //to retire a shelf.
                [$errored[0], ''],
            ]
        );

        $plan = $this->importer()->plan(self::LIBRARY, $path, 'Books', null, true);

        $retired = [];
        foreach ($plan->withAction(PlannedRow::INACTIVATE) as $row) {
            $retired[] = $row->withinLibraryId;
        }

        self::assertNotContains($kept[0], $retired);
        self::assertNotContains($errored[0], $retired, 'a row that errored must not also retire its book');
        self::assertSame(count($active) - 2, count($retired));
        foreach ($plan->withAction(PlannedRow::INACTIVATE) as $row) {
            self::assertSame('Mass book import', $row->values['inactivationReason']);
            self::assertFalse($row->values['isActive']);
        }
    }

    public function testAMissingRequiredColumnIsAnAnswerRatherThanAnException(): void
    {
        //The old engine threw `Exception: Missing required fields for the excel file:
        //withinLibraryId, title` out of a controller, and it reached a librarian as a 500.
        $path = $this->sheet(['Author', 'Publisher'], [['King, Herbert', 'Schönstatt-Verlag']]);

        $plan = $this->importer()->plan(self::LIBRARY, $path, 'Books');

        self::assertFalse($plan->canApply());
        self::assertSame(ImportProblem::COLUMNS_MISSING, $plan->blockers[0]['reason']);
        //Named as the librarian sees them in the file, not as the entity spells them.
        self::assertSame(['Barcode', 'Title'], $plan->blockers[0]['params']);
        //No rows are planned at all: without a barcode column nothing can be matched, and
        //guessing would produce a page full of creations.
        self::assertSame([], $plan->rows);
        //The map is still carried, because the mapping screen is where this gets fixed.
        self::assertTrue($plan->map->has('authorsText'));
    }

    public function testAMissingFileIsAnAnswerToo(): void
    {
        $plan = $this->importer()->plan(self::LIBRARY, 'data/import/nothing-is-here.xlsx', 'Books');

        self::assertFalse($plan->canApply());
        self::assertSame(ImportProblem::FILE_MISSING, $plan->blockers[0]['reason']);
        self::assertSame([], $plan->rows);
    }

    public function testAWorksheetThatIsNotThereIsAnAnswerToo(): void
    {
        $path = $this->sheet(['Barcode', 'Title'], [[999007, 'A book']]);

        $plan = $this->importer()->plan(self::LIBRARY, $path, 'Hoja1');

        self::assertFalse($plan->canApply());
        self::assertSame(ImportProblem::WORKSHEET_MISSING, $plan->blockers[0]['reason']);
    }

    public function testAWhollyEmptyRowIsSkippedRatherThanErrored(): void
    {
        //A spreadsheet almost always has some. Treating them as errors would put a
        //hundred meaningless rows at the top of the review page, which is the position
        //reserved for the ones a librarian has to act on.
        $path = $this->sheet(
            ['Barcode', 'Title'],
            [[999008, 'A book'], ['', ''], [null, null]]
        );

        $plan = $this->importer()->plan(self::LIBRARY, $path, 'Books');

        self::assertSame(1, $plan->statistics()[PlannedRow::CREATE]);
        self::assertSame(0, $plan->statistics()[PlannedRow::ERROR]);
    }
}

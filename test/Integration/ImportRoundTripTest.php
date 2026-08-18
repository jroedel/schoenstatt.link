<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Books\Import\HeaderMatcher;
use App\Books\Import\ImportColumns;
use App\Books\Import\ImportTemplate;
use Books\Service\SpreadsheetReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

use function implode;
use function array_keys;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The template a librarian downloads has to be a file the import can read back.
 *
 * ## Why this is the load-bearing test of the feature
 *
 * The template writer and the header matcher agree only because both read
 * `App\Books\Import\ImportColumns`, and nothing enforces that they keep doing so.
 * Rename a heading in one and the file still opens, the sheet still looks right, and the
 * column silently stops matching — the import just quietly does less. There is no error,
 * no warning and nothing different on screen except a number in a summary nobody has a
 * baseline for.
 *
 * So: write a template, read it back through the same `SpreadsheetReader` the importer
 * uses, match its headings, and assert every core column found its own field.
 *
 * It also covers the two things Excel does to a spreadsheet that the import cares about,
 * both of which cost real data if they go wrong: a call number like `1944_Std. 2.1` must
 * not become a number, and `1-2` must not become a date. Cells are written with
 * `setValueExplicit(…, TYPE_STRING)` for exactly that reason.
 *
 * No database and no request — it needs `vendor/` for PhpSpreadsheet and nothing else, so
 * it runs on a bare CI runner.
 */
class ImportRoundTripTest extends TestCase
{
    private ImportTemplate $template;
    private SpreadsheetReader $reader;
    /** @var list<string> */
    private array $written = [];

    protected function setUp(): void
    {
        //The identity translator: this test is about columns, not about language, and the
        //headings are deliberately not translated anyway.
        $this->template = new ImportTemplate(static fn (string $message): string => $message);
        $this->reader   = new SpreadsheetReader();
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            @unlink($path);
        }
    }

    private function write(Spreadsheet $spreadsheet): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'import-round-trip-') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->written[] = $path;

        return $path;
    }

    public function testABlankTemplateCarriesTwoSheetsAndTheDataOneIsFirst(): void
    {
        $path  = $this->write($this->template->build('Colegio Mayor', ['Depósito', 'General', 'PK']));
        $names = $this->reader->worksheetNames($path);

        //Two, which is why the create step stores the *first* sheet rather than "the one,
        //if there is only one" — that condition left every template-based import with no
        //worksheet at all, and the configure page then disagreed with the stored row about
        //which sheet it was reading. It refused every import until it was found.
        self::assertSame(['Books', 'Instructions'], $names);
    }

    public function testEveryColumnTheTemplateWritesIsMatchedBackToItsOwnField(): void
    {
        $path  = $this->write($this->template->build('Colegio Mayor', []));
        $sheet = $this->reader->read($path, 'Books');
        $map   = (new HeaderMatcher())->match($sheet['header']);

        $expected = [];
        foreach (ImportColumns::core() as $column) {
            $expected[] = $column->field;
        }

        self::assertNotSame([], $expected);
        foreach (ImportColumns::core() as $index => $column) {
            self::assertTrue(
                $map->has($column->field),
                $column->heading . ' is written by the template and matched by nothing'
            );
            self::assertSame($index, $map->indexOf($column->field), $column->heading . ' moved column');
        }
        self::assertSame([], $map->missingRequired());
        self::assertSame([], $map->unusedColumns(), 'the template must write no column the import ignores');
    }

    public function testAFilledExportComesBackAsTheValuesItWentInAs(): void
    {
        $book = [
            'withinLibraryId' => 41467,
            'title'           => 'Leben im Bund',
            'authorsText'     => 'King, Herbert',
            //Two things Excel is eager to reinterpret. The call number holds a dot and a
            //space; `1-2` is what an edition looks like and what a date looks like.
            'callNumber'      => '1944_Std. 2.1',
            'bookEdition'     => '1-2',
            'inLanguage'      => ['de'],
            'keywords'        => ['Sch', 'PK'],
            'collectionName'  => 'PK',
            'publishedYear'   => 2002,
            'numberOfPages'   => 320,
            'publisher'       => 'Schönstatt-Verlag',
            'isbn'            => '978-3-935396-00-0',
        ];

        $path  = $this->write($this->template->build('Colegio Mayor', ['PK'], [$book]));
        $sheet = $this->reader->read($path, 'Books');
        $map   = (new HeaderMatcher())->match($sheet['header']);
        $row   = $sheet['rows'][0];

        $value = static fn (string $field): ?string => $row[$map->indexOf($field)] ?? null;

        self::assertSame('41467', $value('withinLibraryId'));
        self::assertSame('Leben im Bund', $value('title'));
        self::assertSame('1944_Std. 2.1', $value('callNumber'), 'a call number must not become a number');
        self::assertSame('1-2', $value('bookEdition'), 'an edition must not become a date');
        self::assertSame('Schönstatt-Verlag', $value('publisher'));
        self::assertSame('978-3-935396-00-0', $value('isbn'), 'an ISBN must not become an expression');
        self::assertSame('2002', $value('publishedYear'));
        //Multi-valued fields go out pipe-delimited, which is how the database stores them
        //and how the importer reads them back — see LibraryImporter::asList().
        self::assertSame('Sch|PK', $value('keywords'));
        self::assertSame('de', $value('inLanguage'));
        //`collection` is the importer's name for what the entity calls collectionName:
        //the import writes a name and resolves it to an id, so the export gives the name.
        self::assertSame('PK', $value('collection'));
    }

    public function testAnEmptyValueComesBackAsNullRatherThanAnEmptyString(): void
    {
        //The importer's empty-row detection and its "this cell erases the value" rule both
        //hinge on this. SpreadsheetReader normalises '' to null precisely because
        //PhpSpreadsheet >= 1.28 produces '' where PHPExcel produced null.
        $path  = $this->write($this->template->build('Bellavista', [], [
            ['withinLibraryId' => 1, 'title' => 'Only these two'],
        ]));
        $sheet = $this->reader->read($path, 'Books');
        $map   = (new HeaderMatcher())->match($sheet['header']);

        self::assertNull($sheet['rows'][0][$map->indexOf('publisher')]);
        self::assertSame('Only these two', $sheet['rows'][0][$map->indexOf('title')]);
    }

    /**
     * A spreadsheet a librarian already has, with none of the template's headings.
     *
     * Colegio Mayor's own header row, in its own order, plus the five columns nothing
     * recognises. This is the case the old import answered with an uncaught exception
     * unless the file was Colegio Mayor's.
     */
    public function testAForeignSpreadsheetMatchesWithoutBeingRenamed(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Biblioteca CM');
        $headers = ['ID', 'Lomo', 'PubID', 'Titulo', 'Autor', 'Categoría', 'Orden', 'Biblioteca',
            'Imprimir', 'Subtítulo', 'Editorial', 'Ciudad', 'Año', 'ISBN', 'Páginas', 'Idioma',
            'Info', 'Estado', 'Categorías', 'Edition'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray(['41467', 'Sch 44.1', '', 'Leben im Bund', 'King, Herbert', 'Sch', '1',
            'PK', '', '', 'Schönstatt-Verlag', '', '2002', '', '320', 'de', '', '', 'Sch|PK', ''], null, 'A2');

        $path = $this->write($spreadsheet);
        $read = $this->reader->read($path, 'Biblioteca CM');
        $map  = (new HeaderMatcher())->match($read['header']);

        self::assertSame(15, $map->count());
        self::assertSame([], $map->missingRequired());
        self::assertSame(
            'Orden, Imprimir, Subtítulo, Info, Estado',
            implode(', ', $map->unusedColumns()),
            'the five columns nothing recognises, reported rather than dropped in silence'
        );
    }

    public function testAWorksheetThatIsNotThereIsNamedRatherThanGuessedAt(): void
    {
        $path = $this->write($this->template->build('Colegio Mayor', []));

        $this->expectExceptionMessageMatches('/Hoja1/');
        //Names the sheets it does have, which is the whole reason the worksheet field
        //stopped being a text box.
        $this->expectExceptionMessageMatches('/Instructions/');
        $this->reader->read($path, 'Hoja1');
    }
}

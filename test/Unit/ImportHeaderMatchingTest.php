<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use App\Books\Import\ColumnMap;
use App\Books\Import\HeaderMatcher;
use App\Books\Import\LegacyColumnMapping;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Books/Import/ImportColumn.php';
require_once __DIR__ . '/../../src/Books/Import/ImportColumns.php';
require_once __DIR__ . '/../../src/Books/Import/ColumnMap.php';
require_once __DIR__ . '/../../src/Books/Import/HeaderMatcher.php';
require_once __DIR__ . '/../../src/Books/Import/LegacyColumnMapping.php';

/**
 * Turning a spreadsheet's first row into a decision about every column.
 *
 * The old import made this decision once, for every library, from a constant. A file
 * whose headings differed got `Exception: Missing required fields for the excel file:
 * withinLibraryId, title` — thrown out of a controller, rendered as a 500, naming two
 * internal field names to a librarian.
 */
class ImportHeaderMatchingTest extends TestCase
{
    /** Colegio Mayor's actual header row, in its actual order. */
    private const REAL_HEADERS = [
        'ID', 'Lomo', 'PubID', 'Titulo', 'Autor', 'Categoría', 'Orden', 'Biblioteca',
        'Imprimir', 'Subtítulo', 'Editorial', 'Ciudad', 'Año', 'ISBN', 'Páginas',
        'Idioma', 'Info', 'Estado', 'Categorías', 'Edition',
    ];

    public function testARealHeaderRowMatchesFifteenColumnsAndReportsFive(): void
    {
        $map = (new HeaderMatcher())->match(self::REAL_HEADERS);

        self::assertSame(15, $map->count());
        self::assertSame([], $map->missingRequired());
        self::assertSame(0, $map->indexOf('withinLibraryId'));
        self::assertSame('Titulo', $map->headingOf('title'));

        //Five columns nothing recognises. The old import ignored them in silence; this
        //is the list the mapping screen shows, and `Subtítulo` in it is real data that
        //`lib_books` has no column for.
        self::assertSame(
            [6 => 'Orden', 8 => 'Imprimir', 9 => 'Subtítulo', 16 => 'Info', 17 => 'Estado'],
            $map->unusedColumns()
        );
    }

    public function testAStoredMappingWinsOverAliasMatching(): void
    {
        //Two columns a librarian might reasonably have. Alias matching would take the
        //first; a stored mapping says which one this import has always read.
        $headers = ['Autor', 'Author', 'Titulo', 'ID'];

        $guessed = (new HeaderMatcher())->match($headers);
        self::assertSame(0, $guessed->indexOf('authorsText'), 'first column wins by default');

        $stored = (new HeaderMatcher())->match($headers, ['authorsText' => 'Author']);
        self::assertSame(1, $stored->indexOf('authorsText'));
        //And the column it did not take is reported rather than silently dropped.
        self::assertArrayHasKey(0, $stored->unusedColumns());
    }

    public function testAStoredMappingNamingAMissingHeadingFallsBackToMatching(): void
    {
        //A column removed from the spreadsheet since the last run. The mapping's other
        //entries still apply; this one re-matches or goes unmapped.
        $map = (new HeaderMatcher())->match(['ID', 'Titulo'], ['authorsText' => 'Autor', 'title' => 'Titulo']);

        self::assertFalse($map->has('authorsText'));
        self::assertSame(1, $map->indexOf('title'));
    }

    public function testAMissingRequiredColumnIsNamed(): void
    {
        $map = (new HeaderMatcher())->match(['Autor', 'Editorial']);

        self::assertSame(['withinLibraryId', 'title'], $map->missingRequired());
    }

    public function testAnEmptyOrUnnamedColumnIsNeitherMatchedNorReported(): void
    {
        $map = (new HeaderMatcher())->match(['ID', null, '   ', 'Titulo']);

        self::assertSame(2, $map->count());
        //A blank heading is a column a spreadsheet grew by accident, not one a librarian
        //needs told about.
        self::assertSame([], $map->unusedColumns());
    }

    public function testTheStoredFormIsHeadingsSoAnInsertedColumnSurvives(): void
    {
        $map    = (new HeaderMatcher())->match(self::REAL_HEADERS);
        $stored = $map->toStorage();

        self::assertSame('ID', $stored['withinLibraryId']);
        self::assertArrayNotHasKey(0, $stored, 'indexes must not be what is stored');

        //The same mapping, applied to the same sheet with a column inserted at the front.
        $shifted = (new HeaderMatcher())->match(['New column', ...self::REAL_HEADERS], $stored);
        self::assertSame(1, $shifted->indexOf('withinLibraryId'));
        self::assertSame(15, $shifted->count());
    }

    public function testPointingAFieldAtAColumnTakesItFromWhoeverHadIt(): void
    {
        $map = (new HeaderMatcher())->match(['ID', 'Titulo', 'Autor']);

        //One column cannot feed two fields: the mapping screen posts one select per
        //field, so choosing a taken column has to unmap whoever had it.
        $moved = $map->with('category', 2);
        self::assertSame(2, $moved->indexOf('category'));
        self::assertFalse($moved->has('authorsText'));

        $cleared = $map->with('authorsText', null);
        self::assertFalse($cleared->has('authorsText'));
        self::assertTrue($cleared->has('title'), 'clearing one field leaves the others alone');
    }

    public function testTheStoredMappingsInTheDatabaseAreTranslatedForward(): void
    {
        //Exactly what `unserialize()` returns for import 3's ColumnMapping column, which
        //was written before the 2018-11-02 entity rename. Following the edit page's
        //`@todo` and reading this straight would have mapped barcode and title and
        //dropped the other ten columns without a word.
        $stored = [
            'author'          => 'Autor',
            'title'           => 'Titulo',
            'pages'           => 'Páginas',
            'language'        => 'Idioma',
            'withinLibraryId' => 'ID',
            'copyrightYear'   => 'Año',
            'edition'         => 'Edition',
        ];

        self::assertSame(
            [
                'authorsText'     => 'Autor',
                'title'           => 'Titulo',
                'numberOfPages'   => 'Páginas',
                'inLanguage'      => 'Idioma',
                'withinLibraryId' => 'ID',
                'publishedYear'   => 'Año',
                'bookEdition'     => 'Edition',
            ],
            LegacyColumnMapping::forward($stored)
        );
    }

    public function testAnUnusableStoredMappingIsNull(): void
    {
        //`false` is what `unserialize()` answers for a NULL column — one of the fourteen
        //import rows has no mapping at all — and for bytes it cannot read, which is what
        //a value truncated by `ColumnMapping`'s varchar(2000) would be.
        self::assertNull(LegacyColumnMapping::forward(false));
        self::assertNull(LegacyColumnMapping::forward(null));
        self::assertNull(LegacyColumnMapping::forward([]));
        self::assertNull(LegacyColumnMapping::forward(['nosuchfield' => 'Whatever']));
    }

    public function testAnEmptyMapKnowsItIsEmpty(): void
    {
        $map = new ColumnMap([], []);

        self::assertTrue($map->isEmpty());
        self::assertSame(0, $map->count());
        self::assertNull($map->headingOf('title'));
    }
}

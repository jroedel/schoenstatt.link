<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use App\Books\Import\ImportColumn;
use App\Books\Import\ImportColumns;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_count_values;
use function array_filter;
use function array_keys;
use function count;
use function implode;

require_once __DIR__ . '/../../src/Books/Import/ImportColumn.php';
require_once __DIR__ . '/../../src/Books/Import/ImportColumns.php';

/**
 * The spreadsheet vocabulary: what a heading means, and what it must never mean.
 *
 * ## The list this is checked against
 *
 * `Books\Controller\LibraryImportsController::getColegioMayorLibraryFieldsMap()` — the
 * fifteen hardcoded mappings that were the entire import for nine years. Every heading in
 * it must still resolve to the same field, because Colegio Mayor's fourteen spreadsheets
 * are on disk and one of them will be re-imported one day. Two entries move: `Año` fed
 * `copyrightYear`, which was never a book field (see
 * `test/Integration/ImportColumnsWriteRealFieldsTest`), and the map's own internal names
 * predate the entity rename.
 *
 * ## The pair that must not merge
 *
 * `Categoría` is **Category** and `Categorías` is **Keywords** — different fields, one
 * letter apart. Normalisation folds case and accents, which brings `CATEGORÍA` and
 * `categoria` together, and it must not go one step further. A stemmer, a plural-stripper,
 * or `rtrim($heading, 's')` would silently merge two columns of Colegio Mayor's catalogue
 * into one field and there would be nothing on screen to show it: the mapping screen would
 * report a clean match for both.
 */
class ImportColumnVocabularyTest extends TestCase
{
    /**
     * getColegioMayorLibraryFieldsMap(), verbatim, with the two renames applied.
     *
     * @return array<string, array{0: string, 1: string, 2: string}> [heading, field, note]
     */
    public static function theHardcodedMap(): array
    {
        return [
            'Autor' => ['Autor', 'authorsText', 'unchanged'],
            'Titulo' => ['Titulo', 'title', 'unchanged'],
            'Lomo' => ['Lomo', 'callNumber', 'unchanged'],
            'Categoría' => ['Categoría', 'category', 'unchanged'],
            'Páginas' => ['Páginas', 'numberOfPages', 'unchanged'],
            'Idioma' => ['Idioma', 'inLanguage', 'unchanged'],
            'ID' => ['ID', 'withinLibraryId', 'unchanged'],
            'Año' => ['Año', 'publishedYear', 'was copyrightYear, which no column stores'],
            'Editorial' => ['Editorial', 'publisher', 'unchanged'],
            'Ciudad' => ['Ciudad', 'publishingPlace', 'unchanged'],
            'ISBN' => ['ISBN', 'isbn', 'unchanged'],
            'PubID' => ['PubID', 'publicationId', 'unchanged'],
            'Categorías' => ['Categorías', 'keywords', 'one letter from Categoría, and a different field'],
            'Edition' => ['Edition', 'bookEdition', 'unchanged'],
            'Biblioteca' => ['Biblioteca', 'collection', 'unchanged'],
        ];
    }

    #[DataProvider('theHardcodedMap')]
    public function testEveryHeadingTheOldImportKnewStillResolves(
        string $heading,
        string $field,
        string $note
    ): void {
        self::assertSame($field, ImportColumns::fieldFor($heading), $heading . ': ' . $note);
    }

    public function testCategorySingularAndPluralStayApart(): void
    {
        self::assertSame('category', ImportColumns::fieldFor('Categoría'));
        self::assertSame('keywords', ImportColumns::fieldFor('Categorías'));
        self::assertNotSame(
            ImportColumns::normalize('Categoría'),
            ImportColumns::normalize('Categorías'),
            'normalisation must fold case and accents and stop there'
        );
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function equivalentHeadings(): array
    {
        return [
            'accents'           => ['Categoría', 'CATEGORIA'],
            'surrounding space' => ['  Autor  ', 'autor'],
            'punctuation'       => ['Author(s)', 'author s'],
            'german umlaut'     => ['Schlagwörter', 'schlagworter'],
        ];
    }

    #[DataProvider('equivalentHeadings')]
    public function testNormalisationFoldsCaseAccentsAndPunctuation(string $one, string $other): void
    {
        self::assertSame(ImportColumns::normalize($one), ImportColumns::normalize($other));
    }

    public function testAnUnrecognisedHeadingResolvesToNothing(): void
    {
        //Reported on the mapping screen rather than guessed at. `Subtítulo` is real:
        //four of Colegio Mayor's spreadsheets carry it and lib_books has no column.
        self::assertNull(ImportColumns::fieldFor('Subtítulo'));
        self::assertNull(ImportColumns::fieldFor(''));
        self::assertNull(ImportColumns::fieldFor('   '));
    }

    /**
     * A template a librarian downloads must be a template the import can read back.
     *
     * The round trip is what makes the feature work at all, and it breaks the moment a
     * heading is changed in `build()` without being added to the aliases — which nothing
     * else would notice, because the file still opens and the columns simply stop
     * matching.
     */
    public function testEveryHeadingIsItsOwnAlias(): void
    {
        foreach (ImportColumns::all() as $column) {
            self::assertSame(
                $column->field,
                ImportColumns::fieldFor($column->heading),
                $column->heading . ' does not resolve to its own field'
            );
        }
    }

    public function testNoHeadingOrAliasIsClaimedByTwoFields(): void
    {
        $seen = [];
        foreach (ImportColumns::all() as $column) {
            foreach ([$column->heading, ...$column->aliases] as $heading) {
                $seen[] = ImportColumns::normalize($heading);
            }
        }
        $duplicates = array_keys(array_filter(array_count_values($seen), static fn (int $n): bool => $n > 1));

        self::assertSame(
            [],
            $duplicates,
            'a heading listed under two fields resolves by declaration order, which is not a decision: '
            . implode(', ', $duplicates)
        );
    }

    public function testTheRequiredFieldsAreDeclaredRequired(): void
    {
        $required = [];
        foreach (ImportColumns::all() as $column) {
            if ($column->required) {
                $required[] = $column->field;
            }
        }

        self::assertSame(ImportColumns::REQUIRED_FIELDS, $required);
    }

    /**
     * The blank template carries fewer columns than the import accepts, on purpose.
     *
     * A column that is present and empty **erases** the stored value. A template shipping
     * `Admin notes` would be a way to blank the admin notes of every book a librarian
     * touches, so the four non-core columns have to be added deliberately.
     */
    public function testTheBlankTemplateOmitsTheColumnsThatWouldEraseQuietly(): void
    {
        $core = [];
        foreach (ImportColumns::core() as $column) {
            $core[] = $column->field;
        }

        self::assertCount(15, $core);
        foreach (['adminNotes', 'adminTags', 'publicNotes', 'newCallNumber'] as $field) {
            self::assertNotContains($field, $core, $field . ' must not be in the blank template');
        }
        self::assertLessThan(count(ImportColumns::all()), count($core));
    }

    public function testGetReturnsNullForAFieldThatIsNotAnImportColumn(): void
    {
        self::assertNull(ImportColumns::get('sortText'));
        self::assertInstanceOf(ImportColumn::class, ImportColumns::get('title'));
        //heading() falls back to the field name rather than throwing: the review page
        //labels a change with it, and a change is worth showing under an ugly name.
        self::assertSame('sortText', ImportColumns::heading('sortText'));
        self::assertSame('Barcode', ImportColumns::heading('withinLibraryId'));
    }
}

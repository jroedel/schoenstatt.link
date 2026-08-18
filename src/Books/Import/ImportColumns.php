<?php

declare(strict_types=1);

namespace App\Books\Import;

use function array_filter;
use function array_values;
use function preg_replace;
use function strtr;
use function strtolower;
use function trim;

/**
 * The vocabulary a library-import spreadsheet is written in.
 *
 * Every part of the import surface reads its column names from here: the generated
 * template, the header matcher, the mapping screen, the review table and the console
 * command. Before this existed the list lived in
 * `Books\Controller\LibraryImportsController::getColegioMayorLibraryFieldsMap()` —
 * fifteen entries hardcoded to one library's Spanish headings, with a `@todo` beside
 * the edit page saying it should come from the import row's own `columnMapping`.
 *
 * ## Two things that list got wrong, both silently
 *
 * **`copyrightYear` is not a book field.** The book entity maps `publishedYear =>
 * copyright_year`; commit 4933155 renamed it on 2018-11-02 and the import was never
 * updated. `SionTable::createHelper()` and `updateHelper()` both skip a key absent
 * from `update_columns`, so from that day the year column in every spreadsheet was
 * read, cast to an int, and thrown away. Three imports ran afterwards. The field is
 * `publishedYear` here, and `copyrightYear` survives only as an alias for the
 * *heading*, which is what a spreadsheet may legitimately still call it.
 *
 * **The stored `columnMapping` cannot be used as-is.** Following that `@todo` would
 * read mappings written before the same rename: all thirteen stored rows say
 * `author`, `pages`, `language` and `edition`, none of which is a book field today.
 * An import "fixed" that way would write titles and barcodes and quietly drop
 * everything else. `LegacyColumnMapping` translates the old names forward; nothing
 * reads the stored array directly.
 *
 * ## Why the headings are English and the instructions are not
 *
 * A heading is a data key: it decides what a re-import matches, so it has to be
 * stable across locales and across the years between two imports of the same
 * library. The instructions sheet beside it is prose and is translated. Every
 * heading Colegio Mayor's existing spreadsheets use is an alias here, so those files
 * keep importing without being touched.
 */
final class ImportColumns
{
    /**
     * The mapped fields an import cannot proceed without.
     *
     * `title` is required as a *column*, not as a value: a row may leave it blank if
     * it carries a Literature ID that resolves, because the linked record supplies
     * the title. That exception is the one the create page has always documented and
     * has never fully been true — see LibraryImporter::planRow().
     */
    public const REQUIRED_FIELDS = ['withinLibraryId', 'title'];

    /**
     * Accented letters folded to their base, covering the five site languages plus the
     * Latin-1 range a spreadsheet exported from Excel can carry. Uppercase forms are
     * listed too: folding happens before lowercasing so that `\p{L}` never sees a
     * half-folded string.
     */
    private const FOLD = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss',
        'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Å' => 'A',
        'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ø' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
        'Ñ' => 'N', 'Ç' => 'C', 'Ý' => 'Y',
    ];

    /** @var list<ImportColumn>|null */
    private static ?array $columns = null;

    /** @var array<string, string>|null normalized heading => field */
    private static ?array $index = null;

    /** @return list<ImportColumn> */
    public static function all(): array
    {
        return self::$columns ??= self::build();
    }

    /**
     * The columns the blank template carries, in template order.
     *
     * @return list<ImportColumn>
     */
    public static function core(): array
    {
        return array_values(array_filter(self::all(), static fn (ImportColumn $c): bool => $c->core));
    }

    public static function get(string $field): ?ImportColumn
    {
        foreach (self::all() as $column) {
            if ($column->field === $field) {
                return $column;
            }
        }

        return null;
    }

    /** The heading for a field, or the field name itself when it is not an import column. */
    public static function heading(string $field): string
    {
        return self::get($field)?->heading ?? $field;
    }

    /** @return list<string> every field this vocabulary can write */
    public static function fields(): array
    {
        $fields = [];
        foreach (self::all() as $column) {
            $fields[] = $column->field;
        }

        return $fields;
    }

    /**
     * The field a spreadsheet heading names, or null when nothing recognises it.
     *
     * Matching is on the normalized form, so `Categoría`, `CATEGORIA` and ` categoria `
     * are one heading — while `Categoría` and `Categorías` stay two, which they must:
     * Colegio Mayor's spreadsheets use exactly that pair for `category` and `keywords`.
     */
    public static function fieldFor(string $heading): ?string
    {
        self::$index ??= self::buildIndex();

        return self::$index[self::normalize($heading)] ?? null;
    }

    /**
     * Lowercase, fold diacritics, reduce every run of non-alphanumerics to one space.
     *
     * Deliberately not a stemmer, and deliberately not `intl`. Plural and singular must
     * stay distinct — see `fieldFor()` — so nothing here touches the end of a word; and
     * an explicit fold beats `Normalizer` or `iconv//TRANSLIT` because both of those
     * make the answer depend on something outside this file (an extension being loaded,
     * a locale being set), and this answer decides which spreadsheet column becomes
     * which database field.
     */
    public static function normalize(string $heading): string
    {
        $folded = strtr($heading, self::FOLD);
        $spaced = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $folded) ?? $folded;

        return trim(strtolower($spaced));
    }

    /** @return array<string, string> */
    private static function buildIndex(): array
    {
        $index = [];
        foreach (self::all() as $column) {
            foreach ([$column->heading, ...$column->aliases] as $heading) {
                $key = self::normalize($heading);
                //First declaration wins, so a heading listed under two fields resolves
                //the same way on every run rather than by array order.
                $index[$key] ??= $column->field;
            }
        }

        return $index;
    }

    /** @return list<ImportColumn> */
    private static function build(): array
    {
        return [
            new ImportColumn(
                field: 'withinLibraryId',
                heading: 'Barcode',
                aliases: ['ID', 'Codigo', 'Codigo de barras', 'Registro', 'Item ID', 'Copy ID', 'withinLibraryId'],
                required: true,
                help: 'The library\'s own number for this copy. Required, must be a whole number, and must be '
                    . 'unique within the library — it is what an import matches a row to an existing book by.',
                width: 12
            ),
            new ImportColumn(
                field: 'title',
                heading: 'Title',
                aliases: ['Titulo', 'Titel', 'Titolo', 'Nombre'],
                required: true,
                help: 'Required, unless the row carries a Literature ID that names an existing record.',
                width: 44
            ),
            new ImportColumn(
                field: 'authorsText',
                heading: 'Author',
                aliases: ['Autor', 'Autores', 'Authors', 'Author(s)', 'Verfasser', 'Autore', 'Creator', 'authorsText'],
                help: 'One name, or several separated by a vertical bar: Kentenich, Josef | Schlickmann, Anna.',
                width: 28
            ),
            new ImportColumn(
                field: 'bookEdition',
                heading: 'Edition',
                aliases: ['Edicion', 'Auflage', 'Edizione'],
                help: 'Which edition or printing this copy is — the field that tells two copies of the same '
                    . 'work apart when nothing else does.',
                width: 14
            ),
            new ImportColumn(
                field: 'callNumber',
                heading: 'Call number',
                aliases: ['Lomo', 'Signatura', 'Cota', 'Shelfmark', 'Signatur', 'Classmark', 'Collocazione'],
                help: 'Where the copy stands on the shelf, as printed on its label.',
                width: 18
            ),
            new ImportColumn(
                field: 'collection',
                heading: 'Collection',
                aliases: ['Biblioteca', 'Coleccion', 'Sammlung', 'Sublocation', 'Seccion', 'Collezione'],
                help: 'The collection within this library. A name that does not exist yet is created by the '
                    . 'import. Ignored entirely by libraries that do not use collections.',
                width: 20
            ),
            new ImportColumn(
                field: 'category',
                heading: 'Category',
                aliases: ['Categoria', 'Kategorie', 'Clasificacion', 'Classification'],
                help: 'One classification for the copy. For several subject terms use Keywords instead.',
                width: 22
            ),
            new ImportColumn(
                field: 'keywords',
                heading: 'Keywords',
                aliases: ['Categorias', 'Palabras clave', 'Materias', 'Subjects', 'Subject', 'Schlagworter',
                    'Tags', 'Etiquetas', 'Parole chiave'],
                help: 'Subject terms shown to readers, separated by a vertical bar.',
                width: 24
            ),
            new ImportColumn(
                field: 'inLanguage',
                heading: 'Language',
                aliases: ['Idioma', 'Lengua', 'Sprache', 'Lingua', 'Lang'],
                help: 'Two-letter code: en, es, de, pt, it, fr, la, pl.',
                width: 10
            ),
            new ImportColumn(
                field: 'numberOfPages',
                heading: 'Pages',
                aliases: ['Paginas', 'Seiten', 'Pagine', 'Number of pages', 'Extent'],
                help: 'A whole number.',
                width: 8
            ),
            new ImportColumn(
                field: 'publishedYear',
                heading: 'Year published',
                aliases: ['Ano', 'Year', 'Jahr', 'Anno', 'Copyright year', 'copyrightYear', 'Ano de publicacion'],
                help: 'A four-digit year.',
                width: 10
            ),
            new ImportColumn(
                field: 'publisher',
                heading: 'Publisher',
                aliases: ['Editorial', 'Verlag', 'Editore', 'Casa editorial'],
                width: 24
            ),
            new ImportColumn(
                field: 'publishingPlace',
                heading: 'Place of publication',
                aliases: ['Ciudad', 'City', 'Lugar', 'Lugar de publicacion', 'Ort', 'Citta', 'Place',
                    'publishingPlace'],
                width: 20
            ),
            new ImportColumn(
                field: 'isbn',
                heading: 'ISBN',
                aliases: ['ISBN-13', 'ISBN13', 'ISBN-10'],
                width: 18
            ),
            new ImportColumn(
                field: 'publicationId',
                heading: 'Literature ID',
                aliases: ['PubID', 'Pub ID', 'Publication ID', 'publicationId', 'ID literatura'],
                help: 'Links this copy to a record in the site-wide literature catalogue. When it names an '
                    . 'existing record, that record supplies the title, author, year, publisher, place, pages, '
                    . 'language and ISBN — whatever those columns say in the spreadsheet is ignored for that row.',
                width: 12
            ),
            new ImportColumn(
                field: 'newCallNumber',
                heading: 'New call number',
                aliases: ['Nueva signatura', 'Nuevo lomo'],
                core: false,
                help: 'Only when the printed call number should change. It goes onto the next labels printed.',
                width: 18
            ),
            new ImportColumn(
                field: 'publicNotes',
                heading: 'Public notes',
                aliases: ['Notas', 'Notas publicas', 'Notes', 'Observaciones'],
                core: false,
                help: 'Shown to readers. Markdown.',
                width: 30
            ),
            new ImportColumn(
                field: 'adminNotes',
                heading: 'Admin notes',
                aliases: ['Notas administrativas'],
                core: false,
                help: 'Visible only to library administrators. Markdown.',
                width: 30
            ),
            new ImportColumn(
                field: 'adminTags',
                heading: 'Admin tags',
                aliases: ['Etiquetas administrativas'],
                core: false,
                help: 'Administrator-only tags, separated by a vertical bar.',
                width: 20
            ),
        ];
    }
}

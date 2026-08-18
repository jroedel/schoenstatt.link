<?php

declare(strict_types=1);

namespace App\Books\Import;

use App\Laminas\SionResult;
use Books\Model\LibraryTable;
use Books\Model\PublicationsTable;
use Books\Service\SpreadsheetReader;
use Laminas\Db\Sql\Predicate\PredicateInterface;
use InvalidArgumentException;
use Throwable;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function explode;
use function file_exists;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;
use function trim;

/**
 * Reads a library spreadsheet and says what importing it would do — then does it.
 *
 * ## Where this came from
 *
 * `Books\Controller\LibraryImportsController::importSpreadsheetFile()`, three hundred
 * lines inside a laminas controller, reachable only by rendering a page. It took a
 * `&$simulate` flag it also **wrote to**, so "tell me what would happen" and "do it"
 * were one method with one set of failure modes, and the flag being flipped inside was
 * how the caller learned the file had been refused. Splitting it into `plan()` and
 * `apply()` is most of what this class is.
 *
 * ## Four defects it inherited, and does not reproduce
 *
 * 1. **The year was thrown away.** The map named `copyrightYear`; the book entity calls
 *    that field `publishedYear`, and `SionTable` silently drops a key it has no column
 *    for. Every import since 2018-11-02 read the year, cast it to an int and discarded
 *    it. See ImportColumns.
 * 2. **Multi-valued fields were rewritten on every row.** `inLanguage`, `keywords` and
 *    `adminTags` come back from the database as arrays and were handed to
 *    `updateEntity()` as strings; `updateHelper()` compares with `==`, an array never
 *    equals a string, so every matched book had its language rewritten and a change row
 *    logged. December 2017 holds **44,095** `inLanguage` rows in `sch_changes` against
 *    734 for the next-busiest field that month. This class splits those three fields
 *    into arrays before handing them over, so an unchanged value compares equal.
 * 3. **A non-numeric barcode became zero.** `(int) $cell` ran before the check that was
 *    supposed to catch it, so `is_numeric()` was asked about an int and always said yes.
 *    Two rows with blank barcodes therefore collided at 0 and blocked the whole file
 *    with a message about duplicates. Here a blank or non-numeric barcode is that row's
 *    own error and the rest of the file still imports.
 * 4. **Errors had no reason.** Eight yellow rows on import 3 and nothing to say why; six
 *    were the case the instructions call supported. Every error now carries an
 *    ImportProblem key.
 *
 * ## What it deliberately keeps
 *
 * A **blank cell erases the stored value**. Present-and-empty means empty, not "leave
 * alone" — that is what makes the export-edit-reimport round trip able to clear a field
 * at all, and it is why the generated blank template carries only the core columns.
 * The review page shows it as a change like any other, which is the protection.
 *
 * A **complete import inactivates** every active book the sheet does not mention. Rows
 * that errored are excluded from that sweep, exactly as before: a typo must not be able
 * to retire a shelf.
 */
final class LibraryImporter
{
    /**
     * Entity fields stored pipe-delimited and read back as arrays.
     *
     * @see processBookRow() in LibraryTable, which calls filterDbArray() on these three
     */
    private const MULTI_VALUED = ['inLanguage', 'keywords', 'adminTags'];

    /**
     * Fields a linked literature record supplies, overriding the spreadsheet.
     *
     * publication field => book field. `copyrightYear` on the left is the publication's
     * own field name, which really is called that; `publishedYear` on the right is the
     * book's. Conflating the two is defect 1 above.
     */
    private const FROM_PUBLICATION = [
        'title'           => 'title',
        'authorsText'     => 'authorsText',
        'copyrightYear'   => 'publishedYear',
        'publisher'       => 'publisher',
        'publishingPlace' => 'publishingPlace',
        'numberOfPages'   => 'numberOfPages',
        'inLanguage'      => 'inLanguage',
        'isbn'            => 'isbn',
    ];

    public function __construct(
        private readonly LibraryTable $library,
        private readonly PublicationsTable $publications,
        private readonly SpreadsheetReader $reader,
        private readonly HeaderMatcher $matcher = new HeaderMatcher()
    ) {
    }

    /**
     * What importing this sheet into this library would do. Reads; never writes.
     *
     * @param array<string, string>|null $storedMapping field => heading, already put
     *        through LegacyColumnMapping
     */
    public function plan(
        int $libraryId,
        string $filePath,
        string $worksheet,
        ?array $storedMapping = null,
        bool $isCompleteImport = false
    ): ImportPlan {
        if ('' === $filePath || ! file_exists($filePath)) {
            return $this->refused(ImportProblem::FILE_MISSING, [$filePath]);
        }

        try {
            $sheet = $this->reader->read($filePath, $worksheet);
        } catch (InvalidArgumentException $e) {
            return $this->refused(ImportProblem::WORKSHEET_MISSING, [$worksheet, $e->getMessage()]);
        } catch (Throwable $e) {
            return $this->refused(ImportProblem::NO_ROWS, [$e->getMessage()]);
        }

        $map = $this->matcher->match($sheet['header'], $storedMapping);

        $missing = $map->missingRequired();
        if ([] !== $missing) {
            //Rows are not planned at all: without a barcode column nothing can be matched
            //to a book, and guessing would produce a page full of creations. The mapping
            //screen is where this gets fixed, and it needs the map, which is why the plan
            //still carries one.
            return new ImportPlan(
                $map,
                [],
                [$this->blocker(ImportProblem::COLUMNS_MISSING, $this->headingsFor($missing))],
                $isCompleteImport,
                count($sheet['rows'])
            );
        }

        return $this->planRows($libraryId, $map, $sheet['rows'], $isCompleteImport);
    }

    /**
     * @param array<int, array<int, string|null>> $rows
     */
    private function planRows(int $libraryId, ColumnMap $map, array $rows, bool $isCompleteImport): ImportPlan
    {
        $this->library->setLibraryId($libraryId);

        $options     = $this->libraryOptions($libraryId);
        $collections = $this->collectionsByName($options);
        $books       = $this->library->getLibraryBooksByBarcode($libraryId);
        $lookup      = $this->library->getLibraryBookLookupWithActive($libraryId);
        $publications = $this->publicationsNamedBy($rows, $map);

        $planned            = [];
        $seenBarcodes       = [];
        $duplicates         = [];
        $queuedCollections  = [];
        $touchedBookIds     = [];

        foreach ($rows as $offset => $cells) {
            //+2: the header is row 1 and $rows starts at the row under it, so this is the
            //number the librarian sees in Excel's margin.
            $rowNumber = $offset + 2;

            $values = $this->valuesFor($map, $cells);
            if ([] === $values) {
                continue; //a wholly empty row is not an error, it is the end of the data
            }

            $barcode = $this->barcode($map, $cells);
            if (null === $barcode) {
                $planned[] = new PlannedRow(
                    action: PlannedRow::ERROR,
                    rowNumber: $rowNumber,
                    values: $values,
                    reason: ImportProblem::BARCODE_MISSING
                );
                continue;
            }

            $values['withinLibraryId'] = $barcode;
            $values['libraryId']       = $libraryId;

            $isDuplicate = in_array($barcode, $seenBarcodes, true);
            if ($isDuplicate) {
                $duplicates[$barcode] = true;
            }
            $seenBarcodes[] = $barcode;

            $collectionName = null;
            if (array_key_exists('collection', $values)) {
                $collectionName = is_string($values['collection']) ? trim($values['collection']) : null;
                unset($values['collection']);
            }
            if (! $options['useCollections']) {
                //The library does not use collections, so the column is inert rather than
                //an error — some libraries share a spreadsheet layout with ones that do.
                unset($values['collectionId']);
                $collectionName = null;
            } elseif (null !== $collectionName && '' !== $collectionName) {
                if (isset($collections[$collectionName])) {
                    $values['collectionId'] = $collections[$collectionName];
                } elseif (! isset($queuedCollections[$collectionName])) {
                    $queuedCollections[$collectionName] = true;
                    $planned[]                          = new PlannedRow(
                        action: PlannedRow::CREATE_COLLECTION,
                        collection: $collectionName
                    );
                }
            }

            $linked = $this->applyPublication($values, $publications);

            $existing = $books[$barcode] ?? null;
            $bookId   = isset($lookup[$barcode]) ? (int) $lookup[$barcode]['bookId'] : null;

            $problem = $this->rowProblem($values, $isDuplicate, $linked);
            if (null !== $problem) {
                if (null !== $bookId) {
                    //Excluded from the complete-import sweep: a row we refused to import
                    //must not also cause the book it names to be retired.
                    $touchedBookIds[$bookId] = true;
                }
                $planned[] = new PlannedRow(
                    action: PlannedRow::ERROR,
                    rowNumber: $rowNumber,
                    bookId: $bookId,
                    withinLibraryId: $barcode,
                    values: $values,
                    reason: $problem[0],
                    reasonParams: $problem[1],
                    collection: $collectionName
                );
                continue;
            }

            if (null !== $bookId) {
                $values['bookId'] = $bookId;
                if (true === ($lookup[$barcode]['isActive'] ?? false)) {
                    $values['isActive'] = true;
                }
                $touchedBookIds[$bookId] = true;
                $planned[]               = new PlannedRow(
                    action: PlannedRow::UPDATE,
                    rowNumber: $rowNumber,
                    bookId: $bookId,
                    withinLibraryId: $barcode,
                    values: $values,
                    changes: $this->changes(is_array($existing) ? $existing : [], $values),
                    collection: $collectionName
                );
                continue;
            }

            $planned[] = new PlannedRow(
                action: PlannedRow::CREATE,
                rowNumber: $rowNumber,
                withinLibraryId: $barcode,
                values: $values,
                collection: $collectionName
            );
        }

        if ($isCompleteImport) {
            foreach ($lookup as $barcode => $info) {
                $bookId = (int) $info['bookId'];
                if (true !== ($info['isActive'] ?? false) || isset($touchedBookIds[$bookId])) {
                    continue;
                }
                $planned[] = new PlannedRow(
                    action: PlannedRow::INACTIVATE,
                    bookId: $bookId,
                    withinLibraryId: is_numeric($barcode) ? (int) $barcode : null,
                    values: [
                        'bookId'             => $bookId,
                        'isActive'           => false,
                        'inactivationReason' => 'Mass book import',
                        'title'              => $books[$barcode]['title'] ?? null,
                    ]
                );
            }
        }

        $blockers = [];
        if ([] !== $duplicates) {
            $blockers[] = $this->blocker(
                ImportProblem::BARCODES_DUPLICATED,
                [implode(', ', array_map('strval', array_keys($duplicates)))]
            );
        }

        return new ImportPlan($map, $planned, $blockers, $isCompleteImport, count($rows));
    }

    /**
     * Perform a plan. Refuses a plan with blockers rather than doing part of it.
     */
    public function apply(int $libraryId, ImportPlan $plan): ImportResult
    {
        if (! $plan->canApply()) {
            throw new InvalidArgumentException('This import plan has unresolved blockers and cannot be applied.');
        }

        $this->library->setLibraryId($libraryId);

        $created        = 0;
        $updated        = 0;
        $inactivated    = 0;
        $skipped        = 0;
        $newCollections = [];

        foreach ($plan->rows as $row) {
            if (PlannedRow::ERROR === $row->action) {
                $skipped++;
                continue;
            }
            if (PlannedRow::CREATE_COLLECTION === $row->action && null !== $row->collection) {
                $newId = $this->library->createEntity('collection', [
                    'libraryId' => $libraryId,
                    'name'      => $row->collection,
                ], false);
                if (is_numeric($newId)) {
                    $newCollections[$row->collection] = (int) $newId;
                }
                continue;
            }

            $values = $row->values;
            //A collection created a moment ago by this same run. The plan could not know
            //the id, so it is filled in here rather than in a second pass.
            if (
                null !== $row->collection
                && ! isset($values['collectionId'])
                && isset($newCollections[$row->collection])
            ) {
                $values['collectionId'] = $newCollections[$row->collection];
            }

            switch ($row->action) {
                case PlannedRow::CREATE:
                    $this->library->createEntity('book', $values, false);
                    $created++;
                    break;
                case PlannedRow::UPDATE:
                    if ($row->isUnchanged()) {
                        //Nothing the spreadsheet says differs from what is stored. The old
                        //engine still called updateEntity() for all 10,091 of these, each
                        //of which is two getObject() calls before it decides to do nothing.
                        $skipped++;
                        break;
                    }
                    $this->library->updateEntity('book', (int) $row->bookId, $values, [], false);
                    $updated++;
                    break;
                case PlannedRow::INACTIVATE:
                    $this->library->updateEntity('book', (int) $row->bookId, $values, [], false);
                    $inactivated++;
                    break;
            }
        }

        $this->library->removeDependentCacheItems('book');
        if ([] !== $newCollections) {
            $this->library->removeDependentCacheItems('collection');
        }

        return new ImportResult($created, $updated, $inactivated, $newCollections, $skipped);
    }

    /**
     * The reason this row cannot be imported, or null.
     *
     * @param array<string, mixed> $values
     * @return array{0: string, 1: list<string>}|null
     */
    private function rowProblem(array $values, bool $isDuplicate, bool $linked): ?array
    {
        if ($isDuplicate) {
            return [ImportProblem::BARCODE_DUPLICATED, [(string) ($values['withinLibraryId'] ?? '')]];
        }

        $title = $values['title'] ?? null;
        if (is_string($title) && '' !== trim($title)) {
            return null;
        }

        $publicationId = $values['publicationId'] ?? null;
        if (is_numeric($publicationId) && ! $linked) {
            //The documented exception — "a row with a publicationId need not have a
            //title" — is true only when the record exists. Six of import 3's eight
            //errors are this, and the old page said nothing at all.
            return [ImportProblem::LITERATURE_ID_UNKNOWN, [(string) $publicationId]];
        }

        return [ImportProblem::TITLE_MISSING, []];
    }

    /**
     * Fill a row from its linked literature record, and say whether one was found.
     *
     * @param array<string, mixed> $values
     * @param array<int, array<string, mixed>> $publications
     */
    private function applyPublication(array &$values, array $publications): bool
    {
        $publicationId = $values['publicationId'] ?? null;
        if (! is_numeric($publicationId)) {
            return false;
        }
        $publication = $publications[(int) $publicationId] ?? null;
        if (! is_array($publication)) {
            return false;
        }

        foreach (self::FROM_PUBLICATION as $from => $to) {
            if (! isset($publication[$from])) {
                continue;
            }
            $values[$to] = in_array($to, self::MULTI_VALUED, true)
                ? $this->asList($publication[$from])
                : $publication[$from];
        }

        return true;
    }

    /**
     * The publications every row in the sheet names, in one query.
     *
     * @param array<int, array<int, string|null>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function publicationsNamedBy(array $rows, ColumnMap $map): array
    {
        $index = $map->indexOf('publicationId');
        if (null === $index) {
            return [];
        }
        $ids = [];
        foreach ($rows as $cells) {
            $value = $cells[$index] ?? null;
            if (is_numeric($value)) {
                $ids[(int) $value] = true;
            }
        }
        if ([] === $ids) {
            return [];
        }

        //queryObjects()'s @param names predicates while every caller in the application
        //passes a column => value map, which is what its SQL builder expects. Stated at
        //one place rather than suppressed at the call, as LibraryCollectionsController does.
        /** @var array<PredicateInterface> $predicate */
        $predicate = ['publicationId' => array_keys($ids)];
        //SionResult, not `is_array($found) ? … : []`: queryObjects() is annotated
        //`@return mixed[]` and returns null on some paths, so the guard is real while the
        //annotation says it is dead code. See App\Laminas\SionResult.
        $found = SionResult::rows($this->publications->queryObjects('publication', $predicate));

        $publications = [];
        foreach ($found as $id => $publication) {
            if (is_array($publication)) {
                $publications[(int) $id] = $publication;
            }
        }

        return $publications;
    }

    /**
     * The row's cells as entity fields, or an empty array when the row is blank.
     *
     * @param array<int, string|null> $cells
     * @return array<string, mixed>
     */
    private function valuesFor(ColumnMap $map, array $cells): array
    {
        $values = [];
        $found  = false;
        foreach ($map->assignments() as $field => $index) {
            $raw = $cells[$index] ?? null;
            if (null !== $raw && '' !== trim((string) $raw)) {
                $found = true;
            }
            $values[$field] = $this->castField($field, $raw);
        }

        return $found ? $values : [];
    }

    /**
     * The barcode this row declares, or null when it declares none usable.
     *
     * @param array<int, string|null> $cells
     */
    private function barcode(ColumnMap $map, array $cells): ?int
    {
        $index = $map->indexOf('withinLibraryId');
        $raw   = null === $index ? null : ($cells[$index] ?? null);
        if (! is_scalar($raw)) {
            return null;
        }
        $raw = trim((string) $raw);

        //`(int)` only after is_numeric, which is the ordering the old engine got backwards.
        return '' !== $raw && is_numeric($raw) ? (int) $raw : null;
    }

    private function castField(string $field, mixed $raw): mixed
    {
        if (in_array($field, self::MULTI_VALUED, true)) {
            return $this->asList($raw);
        }
        if (in_array($field, ['publishedYear', 'numberOfPages', 'publicationId'], true)) {
            return is_numeric($raw) ? (int) $raw : null;
        }

        return null === $raw ? null : (is_scalar($raw) ? trim((string) $raw) : null);
    }

    /**
     * A pipe-delimited cell as the array the entity stores, matching filterDbArray().
     *
     * @return list<string>
     */
    private function asList(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw), static fn (string $v): bool => '' !== $v));
        }
        if (! is_scalar($raw)) {
            return [];
        }
        $raw = trim((string) $raw);
        if ('' === $raw) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode('|', $raw)),
            static fn (string $v): bool => '' !== $v
        ));
    }

    /**
     * What this row changes about the book it matched.
     *
     * Compared field by field over the mapped columns only, so what a librarian sees is
     * "what this spreadsheet says that the catalogue does not". Derived columns the
     * database maintains for itself — `sortText` above all, which
     * `LibraryTable::preprocessBook()` recomputes on every write — are deliberately not
     * listed: they follow from these and are not a decision anyone is being asked about.
     *
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $values
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function changes(array $existing, array $values): array
    {
        $changes = [];
        foreach ($values as $field => $new) {
            if (in_array($field, ['bookId', 'libraryId', 'isActive'], true)) {
                continue;
            }
            if (! array_key_exists($field, $existing)) {
                continue;
            }
            $old = $existing[$field];
            if ($this->same($old, $new)) {
                continue;
            }
            $changes[$field] = [$old, $new];
        }

        return $changes;
    }

    /**
     * Whether two stored values are the same thing.
     *
     * **Compared trimmed**, which is not pedantry: 285 books in Colegio Mayor hold a
     * title or author with a trailing space, and the generated export writes cells
     * trimmed. Without this, exporting a library and re-importing it unchanged reports
     * 285 changes that are invisible in both the spreadsheet and the catalogue, and they
     * bury the ones that are real. A difference nobody can see is not a change worth
     * showing or making.
     *
     * Arrays are compared as lists, so `['es']` and the string `es` agree — the shape
     * mismatch that had `updateHelper()` rewriting `lang` on every matched book and
     * filling `sch_changes` with 44,095 rows in December 2017 alone.
     */
    private function same(mixed $old, mixed $new): bool
    {
        if (is_array($old) || is_array($new)) {
            return $this->asList($old) === $this->asList($new);
        }
        $oldText = null === $old ? '' : trim((string) $old);
        $newText = null === $new ? '' : trim((string) $new);

        return $oldText === $newText;
    }

    /** @return array<string, mixed> */
    private function libraryOptions(int $libraryId): array
    {
        $library = SionResult::rowOrNull($this->library->getObject('library', $libraryId, true));
        $options = null === $library ? null : ($library['options'] ?? null);

        return [
            'useCollections' => (bool) ($options->useCollections ?? false),
            'collections'    => is_array($options->collections ?? null) ? $options->collections : [],
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, int> collection name => id
     */
    private function collectionsByName(array $options): array
    {
        $byName = [];
        /** @var array<int, object> $collections */
        $collections = $options['collections'];
        foreach ($collections as $collectionId => $collection) {
            $name = $collection->name ?? null;
            if (is_string($name) && '' !== $name) {
                $byName[$name] = (int) $collectionId;
            }
        }

        return $byName;
    }

    /**
     * @param list<string> $fields
     * @return list<string>
     */
    private function headingsFor(array $fields): array
    {
        $headings = [];
        foreach ($fields as $field) {
            $headings[] = ImportColumns::heading($field);
        }

        return $headings;
    }

    /**
     * @param list<string> $params
     * @return array{reason: string, params: list<string>}
     */
    private function blocker(string $reason, array $params): array
    {
        return ['reason' => $reason, 'params' => $params];
    }

    /** @param list<string> $params */
    private function refused(string $reason, array $params): ImportPlan
    {
        return new ImportPlan(new ColumnMap([], []), [], [$this->blocker($reason, $params)]);
    }
}

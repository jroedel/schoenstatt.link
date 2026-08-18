<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\Import\ColumnMap;
use App\Books\Import\ImportColumns;
use App\Books\Import\ImportMappingForm;
use App\Books\Import\ImportPlan;
use App\Books\Import\ImportStorage;
use App\Books\Import\LegacyColumnMapping;
use App\Books\Import\LibraryImporter;
use App\Books\Import\PlannedRow;
use App\Books\Import\RunImportForm;
use App\Books\LibraryPage;
use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Laminas\SionResult;
use Books\Model\LibraryTable;
use Books\Model\PublicationsTable;
use Books\Service\SpreadsheetReader;
use JTranslate\Controller\Plugin\NowMessenger;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Twig\Environment;

use function array_filter;
use function array_slice;
use function array_values;
use function basename;
use function count;
use function is_array;
use function is_numeric;
use function in_array;
use function is_string;
use function max;
use function serialize;
use function strlen;

/**
 * Configure an import, see what it would do, and run it.
 *
 * The page batch 11b deliberately left on laminas, and the reason it did:
 * `LibraryImportsController::editAction()` was not an edit form. It opened a
 * spreadsheet, walked it against a hardcoded column map, and either simulated the
 * import or **performed** it — creating, updating and inactivating books in bulk —
 * from three hundred lines living inside the controller. Porting it meant extracting
 * the engine first, which is App\Books\Import\LibraryImporter.
 *
 * ## What the page used to be
 *
 * One form and one table. The table held every mapped column of every row: for import
 * 3 that is 11,381 rows in a 5.27 MB HTML document, of which eight were coloured yellow
 * for "error" with no reason given and 9,749 were books the spreadsheet changed nothing
 * about. Below the table, a button marked *Import data* which did the work on a plain
 * submit, with nothing anywhere naming the 1,281 books it would retire.
 *
 * ## What it is now
 *
 * Three sections, in the order a librarian needs them. **Which columns** — matched
 * automatically from the file's own headings, correctable, and saved onto the import
 * row so a re-run reads the same columns. **What would happen** — counts first, then
 * the rows that are not "no change", errors at the top with a reason each. **Run it** —
 * a POST behind a CSRF token and a digest of the counts that were shown, so a plan
 * whose numbers moved between the preview and the click is re-shown rather than
 * applied.
 *
 * ## The plan is recomputed on every request, including the POST that runs it
 *
 * Deliberately, and it costs about eight seconds on the largest library. Caching it
 * would mean applying a decision made against a catalogue that has since changed, which
 * is exactly what the digest exists to refuse. Eight seconds twice is the price of the
 * confirmation meaning something.
 */
final class LibraryImportConfigureController
{
    /** Rows of each kind rendered before the page starts summarising instead. */
    private const ROWS_SHOWN = 200;

    /** `lib_imports.ColumnMapping` is varchar(2000), and a truncated serialization is unparseable. */
    private const MAX_MAPPING_BYTES = 2000;

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly LibraryPage $page,
        private readonly ImportStorage $storage
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $importId = (int) $request->attributes->get('import_id');
        $redirect = LocalePrefix::redirect(
            $request,
            $this->urls,
            'library-imports/library-import/edit',
            ['import_id' => $importId]
        );
        if (null !== $redirect) {
            return $redirect;
        }

        /** @var LibraryTable $table */
        $table  = $this->laminas->get(LibraryTable::class);
        $import = SionResult::rowOrNull($table->getLibraryImport($importId));
        if (null === $import) {
            return new RedirectResponse($this->urls->path('libraries'));
        }

        $libraryId = (int) ($import['libraryId'] ?? 0);
        $library   = SionResult::rowOrNull($table->getObject('library', $libraryId, true));
        $refusal   = $this->page->refuse($library, LibraryPage::ADMINISTRATE);
        if (null !== $refusal) {
            return $refusal;
        }
        /** @var array<string, mixed> $library */

        if ($this->hasRun($import)) {
            //Its file and worksheet are frozen, so the old page disabled every input and
            //showed the counts. Same answer, without a form nobody may use.
            return new Response($this->twig->render('books/library-import-configure.html.twig', [
                'page_title'  => 'Import details',
                'import'      => $import,
                'library'     => $library,
                'has_run'     => true,
                'detail_url'  => $this->urls->path(
                    'library-imports/library-import',
                    ['import_id' => $importId]
                ),
                'library_url' => $this->urls->path('libraries/library/admin', ['library_id' => $libraryId]),
            ]));
        }

        $filePath = is_string($import['filePath'] ?? null) ? $import['filePath'] : '';
        //An import row can name any path a 2017 form accepted, including one on
        //somebody's Windows desktop. Only files this application stored are opened.
        $readable = $this->storage->owns($filePath) && $this->storage->exists($filePath);

        if ($request->isMethod('POST')) {
            $response = $this->handlePost($request, $table, $import, $libraryId, $filePath, $readable);
            if (null !== $response) {
                return $response;
            }
        }

        return $this->render($request, $table, $import, $library, $filePath, $readable);
    }

    /**
     * @param array<string, mixed> $import
     * @return Response|null a response when the post settled the request, null to fall
     *         through to a normal render with the form's messages on it
     */
    private function handlePost(
        Request $request,
        LibraryTable $table,
        array $import,
        int $libraryId,
        string $filePath,
        bool $readable
    ): ?Response {
        if (! $readable) {
            return null;
        }
        $importId = (int) $import['importId'];

        return $request->request->has('digest')
            ? $this->run($request, $table, $import, $libraryId, $filePath)
            : $this->saveMapping($request, $table, $importId, $filePath, $import);
    }

    /**
     * Save the worksheet and the column choices, then redirect.
     *
     * Post/redirect/get, so that a refresh of the preview does not re-post a mapping —
     * and so the saved mapping is what the preview is built from, rather than the posted
     * one. Those can differ if the save is refused, and showing a preview of a mapping
     * that was not stored is how somebody runs an import they did not configure.
     *
     * @param array<string, mixed> $import
     */
    private function saveMapping(
        Request $request,
        LibraryTable $table,
        int $importId,
        string $filePath,
        array $import
    ): ?Response {
        /** @var SpreadsheetReader $reader */
        $reader = $this->laminas->get(SpreadsheetReader::class);

        try {
            $worksheets = $reader->worksheetNames($filePath);
        } catch (Throwable) {
            return null;
        }

        /** @var array<string, mixed> $posted */
        $posted    = $request->request->all();
        $worksheet = is_string($posted['worksheet'] ?? null) ? $posted['worksheet'] : '';
        if (! in_array($worksheet, $worksheets, true)) {
            $worksheet = $worksheets[0] ?? '';
        }

        $headers = $this->headersOf($reader, $filePath, $worksheet);
        $form    = new ImportMappingForm($worksheets, $headers, new ColumnMap($headers, []), $worksheet);
        $form->setData($posted);
        if (! $form->isValid()) {
            $this->now('Error in form submission, please review.');

            return null;
        }

        /** @var array<string, mixed> $data */
        $data     = $form->getData();
        $chosen   = is_array($data['map'] ?? null) ? $data['map'] : [];
        $map      = new ColumnMap($headers, []);
        foreach (ImportColumns::fields() as $field) {
            $index = $chosen[$field] ?? ImportMappingForm::UNMAPPED;
            if (is_numeric($index)) {
                $map = $map->with($field, (int) $index);
            }
        }

        $update = ['worksheet' => $worksheet];
        $stored = $map->toStorage();
        if (strlen(serialize($stored)) <= self::MAX_MAPPING_BYTES) {
            $update['columnMapping'] = $stored;
        } else {
            //`ColumnMapping` is varchar(2000) and MySQL would truncate rather than
            //refuse, leaving bytes `unserialize()` cannot read. Not storing it is the
            //better failure: the next visit re-matches the headings from scratch.
            $this->now('The column choices were too long to store, so they will be matched again next time.');
        }
        $table->updateEntity('library-import', $importId, $update);

        (new FlashMessenger())
            ->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
            ->addMessage('Columns saved.');

        return new RedirectResponse($this->urls->path(
            'library-imports/library-import/edit',
            ['import_id' => $importId]
        ));
    }

    /**
     * Apply the plan, if it is still the plan that was confirmed.
     *
     * @param array<string, mixed> $import
     */
    private function run(
        Request $request,
        LibraryTable $table,
        array $import,
        int $libraryId,
        string $filePath
    ): ?Response {
        $importId = (int) $import['importId'];
        //**Resolved exactly as the preview resolves it.** These two used to differ: the
        //preview fell back to the file's first sheet when the row named none, while this
        //passed the row's null straight through, so the plan confirmed and the plan run
        //were of different worksheets and the digest refused every single import. Found
        //by running one.
        $plan = $this->plan($import, $libraryId, $filePath, $this->worksheetFor($import, $filePath));

        $form = new RunImportForm(RunImportForm::digestOf($plan));
        /** @var array<string, mixed> $posted */
        $posted = $request->request->all();
        $form->setData($posted);
        if (! $form->isValid()) {
            $this->now('This confirmation has expired. Check the summary below and confirm again.');

            return null;
        }
        if (($posted['digest'] ?? null) !== RunImportForm::digestOf($plan)) {
            //The catalogue or the file moved between the preview and the click. Refusing
            //is the whole point of the digest — see App\Books\Import\RunImportForm.
            $this->now('The library has changed since this preview was made. Please review it again.');

            return null;
        }
        if (! $plan->canApply()) {
            $this->now('This import cannot be run yet.');

            return null;
        }

        $result = $this->importer()->apply($libraryId, $plan);

        $table->updateEntity('library-import', $importId, [
            'booksCreated'     => $result->created,
            'booksUpdated'     => $result->updated,
            'booksInactivated' => $result->inactivated,
            'status'           => LibraryTable::IMPORT_STATUS_COMPLETED,
        ], []);

        (new FlashMessenger())
            ->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
            ->addMessage('The import has been run.');

        return new RedirectResponse($this->urls->path(
            'library-imports/library-import',
            ['import_id' => $importId]
        ));
    }

    /**
     * @param array<string, mixed> $import
     * @param array<string, mixed> $library
     */
    private function render(
        Request $request,
        LibraryTable $table,
        array $import,
        array $library,
        string $filePath,
        bool $readable
    ): Response {
        $importId  = (int) $import['importId'];
        $libraryId = (int) ($import['libraryId'] ?? 0);
        $selfUrl   = $this->urls->path(
            'library-imports/library-import/edit',
            ['import_id' => $importId]
        );

        $view = [
            'page_title'   => 'Configure import',
            'import'       => $import,
            'library'      => $library,
            'has_run'      => false,
            'readable'     => $readable,
            'file_name'    => basename($filePath),
            'self_url'     => $selfUrl,
            'detail_url'   => $this->urls->path(
                'library-imports/library-import',
                ['import_id' => $importId]
            ),
            'library_url'  => $this->urls->path('libraries/library/admin', ['library_id' => $libraryId]),
            'template_url' => $this->urls->path(
                'library-imports/library/template',
                ['library_id' => $libraryId]
            ),
            //Every key the template can read is set here, including the ones only some
            //paths fill. Twig runs with strict_variables, and an undefined variable
            //throws *after* the response is assembled — HTTP 200, empty body, no error
            //page. Three ported pages shipped that way before this rule was written down.
            'mapping_form' => null,
            'plan'         => null,
            'run_form'     => null,
            'worksheets'   => [],
            'unused'       => [],
            'blockers'     => [],
            'statistics'   => [],
            'file_error'   => null,
            'file_path'    => $filePath,
            //field => the heading the template writes for it, so the change list can name
            //"Year published" where the engine says `publishedYear`.
            'headings'     => $this->headings(),
        ];

        if (! $readable) {
            return new Response($this->twig->render('books/library-import-configure.html.twig', $view));
        }

        /** @var SpreadsheetReader $reader */
        $reader = $this->laminas->get(SpreadsheetReader::class);
        try {
            $worksheets = $reader->worksheetNames($filePath);
        } catch (Throwable $e) {
            $view['file_error'] = $e->getMessage();

            return new Response($this->twig->render('books/library-import-configure.html.twig', $view));
        }

        $worksheet = $this->worksheetFor($import, $filePath, $worksheets);

        $plan = $this->plan($import, $libraryId, $filePath, $worksheet);

        $view['worksheets']   = $worksheets;
        $view['mapping_form'] = new ImportMappingForm(
            $worksheets,
            $plan->map->headers,
            $plan->map,
            $worksheet
        );
        $view['plan']        = $this->planForView($plan);
        $view['unused']      = $plan->map->unusedColumns();
        $view['blockers']    = $plan->blockers;
        $view['statistics']  = $plan->statistics();
        if ($plan->canApply() && $plan->writeCount() > 0) {
            $view['run_form'] = new RunImportForm(RunImportForm::digestOf($plan));
        }

        return new Response($this->twig->render('books/library-import-configure.html.twig', $view));
    }

    /**
     * The plan, shaped for a template: counts, the rows worth showing, and how many
     * were left out.
     *
     * @return array<string, mixed>
     */
    private function planForView(ImportPlan $plan): array
    {
        $groups = [];
        foreach (
            [
                PlannedRow::ERROR,
                PlannedRow::CREATE_COLLECTION,
                PlannedRow::CREATE,
                PlannedRow::INACTIVATE,
                PlannedRow::UPDATE,
            ] as $action
        ) {
            $rows = $plan->withAction($action);
            if (PlannedRow::UPDATE === $action) {
                //Only the ones that change something. The other 9,749 are the reason the
                //old page was unreadable.
                $rows = array_values(array_filter($rows, static fn (PlannedRow $r): bool => ! $r->isUnchanged()));
            }
            if ([] === $rows) {
                continue;
            }
            $groups[] = [
                'action'    => $action,
                'total'     => count($rows),
                'rows'      => array_slice($rows, 0, self::ROWS_SHOWN),
                'truncated' => max(0, count($rows) - self::ROWS_SHOWN),
            ];
        }

        //Changed rows that carry a Literature ID, counted because they are the one
        //surprising thing an import does: a linked row takes its title, author, year,
        //publisher, place, pages, language and ISBN from the literature record, so a
        //library exported and re-imported unchanged still reports these as changes.
        //Measured on Colegio Mayor: 459 linked books, 326 of which differ from their
        //record. Without a sentence saying so, that reads as the import renaming books
        //at random.
        $linked = 0;
        foreach ($plan->rows as $row) {
            if (PlannedRow::UPDATE !== $row->action || $row->isUnchanged()) {
                continue;
            }
            if (null !== ($row->values['publicationId'] ?? null)) {
                $linked++;
            }
        }

        return [
            'groups'         => $groups,
            'write_count'    => $plan->writeCount(),
            'row_count'      => $plan->sheetRowCount,
            'linked_changes' => $linked,
        ];
    }

    /**
     * The worksheet this import reads: the one it names, or the file's first.
     *
     * One implementation, called from both the preview and the run — see the note in
     * run() for what happens when they disagree.
     *
     * @param array<string, mixed> $import
     * @param list<string>|null $worksheets already read, when the caller has them
     */
    private function worksheetFor(array $import, string $filePath, ?array $worksheets = null): string
    {
        if (null === $worksheets) {
            /** @var SpreadsheetReader $reader */
            $reader = $this->laminas->get(SpreadsheetReader::class);
            try {
                $worksheets = $reader->worksheetNames($filePath);
            } catch (Throwable) {
                $worksheets = [];
            }
        }

        $named = is_string($import['worksheet'] ?? null) ? $import['worksheet'] : '';

        return in_array($named, $worksheets, true) ? $named : ($worksheets[0] ?? '');
    }

    /** @return array<string, string> field => heading */
    private function headings(): array
    {
        $headings = [];
        foreach (ImportColumns::all() as $column) {
            $headings[$column->field] = $column->heading;
        }

        return $headings;
    }

    /** @param array<string, mixed> $import */
    private function plan(array $import, int $libraryId, string $filePath, ?string $worksheet = null): ImportPlan
    {
        return $this->importer()->plan(
            $libraryId,
            $filePath,
            $worksheet ?? (is_string($import['worksheet'] ?? null) ? $import['worksheet'] : ''),
            LegacyColumnMapping::forward($import['columnMapping'] ?? null),
            true === ($import['isCompleteImport'] ?? false)
        );
    }

    private function importer(): LibraryImporter
    {
        /** @var LibraryTable $library */
        $library = $this->laminas->get(LibraryTable::class);
        /** @var PublicationsTable $publications */
        $publications = $this->laminas->get(PublicationsTable::class);
        /** @var SpreadsheetReader $reader */
        $reader = $this->laminas->get(SpreadsheetReader::class);

        return new LibraryImporter($library, $publications, $reader);
    }

    /** @return list<string|null> */
    private function headersOf(SpreadsheetReader $reader, string $filePath, string $worksheet): array
    {
        try {
            return array_values($reader->read($filePath, $worksheet)['header']);
        } catch (Throwable) {
            return [];
        }
    }

    /** Whether this import has already been performed. @param array<string, mixed> $import */
    private function hasRun(array $import): bool
    {
        if (LibraryTable::IMPORT_STATUS_COMPLETED === ($import['status'] ?? null)) {
            return true;
        }

        //The condition the old page used, kept because a row can carry counts without
        //the status having been set — two of the fourteen do.
        return null !== ($import['booksCreated'] ?? null)
            || null !== ($import['booksUpdated'] ?? null)
            || null !== ($import['booksInactivated'] ?? null);
    }

    private function now(string $message): void
    {
        /** @var NowMessenger $messenger */
        $messenger = $this->laminas->get('ControllerPluginManager')->get('nowMessenger');
        $messenger->setNamespace(NowMessenger::NAMESPACE_ERROR)->addMessage($message);
    }
}

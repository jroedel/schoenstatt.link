<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\Import\ImportColumns;
use App\Books\Import\ImportTemplate;
use App\Books\Import\SpreadsheetUpload;
use App\Books\LibraryPage;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Laminas\SionResult;
use Books\Form\ImportForm;
use Books\Model\LibraryTable;
use JTranslate\Controller\Plugin\NowMessenger;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Twig\Environment;

use function gmdate;
use function is_array;
use function is_string;
use function time;

/**
 * The spreadsheet-import surface, except the page that configures and runs one.
 *
 * Four routes: the list of a library's imports, the template download, starting a new
 * import, and one import's detail. The fifth — configure, preview, run — is
 * App\Controller\LibraryImportConfigureController, because it is a different kind of
 * page and it is the one that writes to `lib_books`.
 *
 * ## The list 500s on laminas for five of the six libraries
 *
 * `indexAction()` deferred to `SionController::indexAction()`, which reaches
 * `SionTable::getObject()` on a path that throws `InvalidArgumentException: No entity
 * provided.` when the library has no imports. All fourteen imports in the database
 * belong to Colegio Mayor, so `/library-imports/library/1`, `/4`, `/5`, `/6` and `/7`
 * were all 500s. Fixed rather than reproduced when the page was ported: an empty list
 * is not an error, and this is a page whose entire content is a list.
 *
 * ## Why the template download is a route and not a static file
 *
 * Because it is per-library. The dropdown of collections is that library's, the
 * instructions name it, and the pre-filled variant is its catalogue. A file in
 * `public/` could be none of those, and a librarian who downloaded one last year would
 * be filling in last year's columns.
 */
final class LibraryImportsController
{
    /** Route default marking the single-import page rather than the list. */
    public const DETAIL = '_library_import_detail';

    /** Route default marking the "start a new import" form. */
    public const CREATE = '_library_import_create';

    /** Route default marking the spreadsheet download. */
    public const TEMPLATE = '_library_import_template';

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly LibraryPage $page,
        private readonly SpreadsheetUpload $upload,
        private readonly ImportTemplate $template
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var LibraryTable $table */
        $table = $this->laminas->get(LibraryTable::class);

        if (true === $request->attributes->get(self::TEMPLATE)) {
            return $this->downloadTemplate($request, $table);
        }
        if (true === $request->attributes->get(self::CREATE)) {
            return $this->create($request, $table);
        }

        return true === $request->attributes->get(self::DETAIL)
            ? $this->detail($request, $table)
            : $this->index($request, $table);
    }

    private function index(Request $request, LibraryTable $table): Response
    {
        $libraryId = (int) $request->attributes->get('library_id');

        $library = $this->page->library($request);
        $refusal = $this->page->refuse($library, LibraryPage::ADMINISTRATE);
        if (null !== $refusal) {
            return $refusal;
        }

        //getLibraryImports() honours the table's own libraryId, so it is set first —
        //without it the list is every library's imports, which is what the laminas
        //action's own getLibraryId() call arranged a moment earlier.
        $table->setLibraryId($libraryId);
        $imports = $table->getLibraryImports();

        return new Response($this->twig->render('books/library-imports.html.twig', [
            //index.phtml sets no headTitle; the layout default stands.
            'page_title'    => null,
            'library_id'    => $libraryId,
            'objects'       => SionResult::rows($imports),
            'template_url'  => $this->templateUrl($libraryId, false),
            'export_url'    => $this->templateUrl($libraryId, true),
        ]));
    }

    /**
     * Start an import: name it, and hand over the file.
     *
     * The step that changed most. It used to ask for a **path on the server** and the
     * **exact name of a worksheet**, both as free text, and offered no way to get a file
     * onto the server in the first place. Now it takes an upload, proves the file opens
     * before writing a row, and settles the worksheet itself when there is only one.
     */
    private function create(Request $request, LibraryTable $table): Response
    {
        $libraryId = (int) $request->attributes->get('library_id');

        $library = $this->page->library($request);
        $refusal = $this->page->refuse($library, LibraryPage::ADMINISTRATE);
        if (null !== $refusal) {
            return $refusal;
        }

        $form = new ImportForm();

        if ($request->isMethod('POST')) {
            /** @var array<string, mixed> $posted */
            $posted              = $request->request->all();
            $posted['libraryId'] = $libraryId;
            $form->setData($posted);

            $file            = $request->files->get('file');
            $uploadedFile    = $file instanceof UploadedFile ? $file : null;
            [$problem]    = $this->upload->problem($uploadedFile);
            $formIsValid     = $form->isValid();

            if (null !== $problem) {
                $form->get('file')->setMessages([$this->uploadMessage($problem)]);
            }

            if ($formIsValid && null === $problem && null !== $uploadedFile) {
                $stored = $this->upload->accept($uploadedFile, $libraryId, gmdate('Y-m-d'));
                if (null !== $stored['error']) {
                    $form->get('file')->setMessages([
                        $this->uploadMessage($stored['error'], $stored['params']),
                    ]);
                } else {
                    return $this->createRow($table, $form, $libraryId, $stored);
                }
            }

            $this->now('Error in form submission, please review.');
        }

        return new Response($this->twig->render('books/library-import-create.html.twig', [
            'page_title'   => 'Begin new import',
            'form'         => $form,
            'columns'      => $this->columnHelp(),
            'template_url' => $this->templateUrl($libraryId, false),
            'export_url'   => $this->templateUrl($libraryId, true),
            'self_url'     => $this->urls->path(
                'library-imports/library/create',
                ['library_id' => $libraryId]
            ),
        ]));
    }

    /**
     * @param array{path: string|null, worksheets: list<string>, error: string|null, params: list<string>} $stored
     */
    private function createRow(LibraryTable $table, ImportForm $form, int $libraryId, array $stored): Response
    {
        /** @var array<string, mixed> $data */
        $data              = $form->getData();
        $data['libraryId'] = $libraryId;
        $data['filePath']  = $stored['path'];
        //The first sheet, whatever the file holds. The generated template has two —
        //Books and Instructions — so "only store it when there is exactly one" left every
        //template-based import with no worksheet at all, and the configure page falling
        //back to a name the stored row did not agree with. The mapping screen is where a
        //different sheet gets chosen.
        $data['worksheet'] = $stored['worksheets'][0] ?? null;
        unset($data['file'], $data['submit']);

        $newId = $table->createEntity('library-import', $data);
        if (! $newId) {
            $this->now('Error in form submission, please review.');

            return new Response($this->twig->render('books/library-import-create.html.twig', [
                'page_title'   => 'Begin new import',
                'form'         => $form,
                'columns'      => $this->columnHelp(),
                'template_url' => $this->templateUrl($libraryId, false),
                'export_url'   => $this->templateUrl($libraryId, true),
                'self_url'     => $this->urls->path(
                    'library-imports/library/create',
                    ['library_id' => $libraryId]
                ),
            ]));
        }

        (new FlashMessenger())
            ->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
            ->addMessage('File uploaded. Check the columns below, then preview the import.');

        return new RedirectResponse($this->urls->path(
            'library-imports/library-import/edit',
            ['import_id' => (int) $newId]
        ));
    }

    /**
     * The blank template, or the library's own books in the same layout.
     *
     * Streamed rather than built into a string: the filled variant for Colegio Mayor is
     * 10,874 rows, and PhpSpreadsheet's writer already knows how to write to a stream.
     */
    private function downloadTemplate(Request $request, LibraryTable $table): Response
    {
        $libraryId = (int) $request->attributes->get('library_id');

        $library = $this->page->library($request);
        //`administrate`, the same as every other page here: the filled variant is the
        //library's whole catalogue including admin notes, in one file.
        $refusal = $this->page->refuse($library, LibraryPage::ADMINISTRATE);
        if (null !== $refusal) {
            return $refusal;
        }
        /** @var array<string, mixed> $library */

        $filled = '' !== (string) $request->query->get('books', '');
        $name   = is_string($library['name'] ?? null) ? $library['name'] : 'Library';

        $books = [];
        if ($filled) {
            $table->setLibraryId($libraryId);
            foreach ($table->getLibraryBooksByBarcode($libraryId) as $book) {
                if (is_array($book) && true === ($book['isActive'] ?? false)) {
                    $books[] = $book;
                }
            }
        }

        $spreadsheet = $this->template->build($name, $this->collectionNames($library), $books);
        $filename    = ImportTemplate::filename($name, $filled, gmdate('Y-m-d', time()));

        $response = new StreamedResponse(static function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        });
        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename)
        );
        //A catalogue export is per-library and per-moment; nothing may keep it.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * @param array<string, mixed> $library
     * @return list<string>
     */
    private function collectionNames(array $library): array
    {
        $options     = $library['options'] ?? null;
        $collections = is_array($options->collections ?? null) ? $options->collections : [];
        $names       = [];
        foreach ($collections as $collection) {
            $name = $collection->name ?? null;
            if (is_string($name) && '' !== $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function detail(Request $request, LibraryTable $table): Response
    {
        $importId = (int) $request->attributes->get('import_id');

        $import = SionResult::rowOrNull($table->getLibraryImport($importId));
        if (null === $import) {
            return new RedirectResponse($this->urls->path('libraries'));
        }

        //The import names its library; the ACL question is about *that* library, not
        //about a route parameter this route does not carry.
        $library = SionResult::rowOrNull($table->getObject('library', $import['libraryId'] ?? 0, true));
        $refusal = $this->page->refuse($library, LibraryPage::ADMINISTRATE);
        if (null !== $refusal) {
            return $refusal;
        }

        return new Response($this->twig->render('books/library-import.html.twig', [
            'page_title' => 'Import details',
            'entity'     => $import,
            //Only a pending import links to the page that runs it — not a completed one
            //and not an abandoned one, both of which that page refuses anyway.
            'edit_url'   => LibraryTable::IMPORT_STATUS_PENDING === ($import['status'] ?? null)
                ? $this->urls->path('library-imports/library-import/edit', ['import_id' => $importId])
                : null,
        ]));
    }

    /** @return list<array{heading: string, required: bool, help: string}> */
    private function columnHelp(): array
    {
        $columns = [];
        foreach (ImportColumns::core() as $column) {
            $columns[] = [
                'heading'  => $column->heading,
                'required' => $column->required,
                'help'     => $column->help,
            ];
        }

        return $columns;
    }

    private function templateUrl(int $libraryId, bool $filled): string
    {
        $path = $this->urls->path('library-imports/library/template', ['library_id' => $libraryId]);

        return $filled ? $path . '?books=1' : $path;
    }

    /**
     * A librarian-facing sentence for an upload problem.
     *
     * The engine's own parameters are deliberately **not** interpolated for the four
     * cases a librarian can act on: "that file could not be opened as a spreadsheet" is
     * actionable, and `Reader\Exception: Unable to identify a reader for this file` is
     * the same fact addressed to somebody else.
     *
     * The catch-all is the exception, and on purpose. It means the *server* refused —
     * most likely `shared/data/import` missing or not writable by the web user, which is
     * the one thing about this feature a deploy can get wrong (see
     * docs/DEPLOY.md § The layout on the server). "Please try again" would be advice to
     * repeat a failure forever. Only library administrators reach this page, and the
     * detail is a relative path.
     *
     * @param list<string> $params
     */
    private function uploadMessage(string $problem, array $params = []): string
    {
        return match ($problem) {
            SpreadsheetUpload::NO_FILE    => 'Choose a spreadsheet to upload.',
            SpreadsheetUpload::TOO_LARGE  => 'That file is larger than 10 MB.',
            SpreadsheetUpload::WRONG_TYPE => 'Only .xlsx, .xls and .ods files can be imported.',
            SpreadsheetUpload::UNREADABLE => 'That file could not be opened as a spreadsheet.',
            default                       => 'The upload could not be stored on the server: '
                . ($params[0] ?? 'no reason given') . '.',
        };
    }

    private function now(string $message): void
    {
        /** @var NowMessenger $messenger */
        $messenger = $this->laminas->get('ControllerPluginManager')->get('nowMessenger');
        $messenger->setNamespace(NowMessenger::NAMESPACE_ERROR)->addMessage($message);
    }
}

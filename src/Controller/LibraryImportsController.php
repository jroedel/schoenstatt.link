<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\LibraryPage;
use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\SionResult;
use App\Laminas\ServiceBridge;
use Books\Form\ImportForm;
use Books\Model\LibraryTable;
use JTranslate\Controller\Plugin\NowMessenger;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_array;
use function ksort;

/**
 * The two read pages of the spreadsheet-import surface: the list of a library's imports,
 * and one import's detail.
 *
 * ## The list 500s on laminas for five of the six libraries
 *
 * `indexAction()` defers to `SionController::indexAction()`, which reaches
 * `SionTable::getObject()` on a path that throws `InvalidArgumentException: No entity
 * provided.` when the library has no imports. All 22 imports in the database belong to
 * Colegio Mayor, so `/library-imports/library/1`, `/4`, `/5`, `/6` and `/7` are all 500s
 * today. Measured, not inferred.
 *
 * Fixed here rather than reproduced, at the user's direction: an empty list is not an
 * error, and this is a page whose entire content is a list.
 *
 * ## Two routes stay on laminas, and neither is "not done yet"
 *
 * `library-imports/library/create` and `library-imports/library-import/edit` are not
 * pages with a form on them; they are the import engine. `editAction()` opens a
 * spreadsheet, walks it against a column map, and either simulates or **performs** the
 * import — creating books, updating books, inactivating books — through
 * `importSpreadsheetFile()`, three hundred lines living inside the laminas controller and
 * reachable only through it. `createAction()` overrides two SionController hooks
 * (`getPostDataForCreateAction`, `createEntityPostFormValidation`) to inject the column
 * map, which the shared create action has no equivalent for.
 *
 * Porting either means extracting that engine into a service both front controllers call.
 * That is a worthwhile refactor and it is not a port: it moves destructive, untested code
 * that writes to `lib_books` in bulk. Doing it as the tail of a batch of twenty-three
 * routes is how a library gets silently re-imported. Filed in docs/BACKLOG.md.
 */
final class LibraryImportsController
{
    /** Route default marking the single-import page rather than the list. */
    public const DETAIL = '_library_import_detail';

    /** Route default marking the "start a new import" form. */
    public const CREATE = '_library_import_create';

    /**
     * `LibraryImportsController::getColegioMayorLibraryFieldsMap()` — spreadsheet column
     * headings to entity fields, hardcoded there and here.
     *
     * Hardcoded in the original with a `@todo` beside the edit page's use of it saying it
     * should come from the import row's own `columnMapping`. It does not, so an import of
     * anything but a Colegio Mayor spreadsheet needs this list edited. Reproduced rather
     * than fixed: the fix is the same refactor the edit page needs.
     */
    private const FIELDS_MAP = [
        'authorsText'     => 'Autor',
        'title'           => 'Titulo',
        'callNumber'      => 'Lomo',
        'category'        => 'Categoría',
        'numberOfPages'   => 'Páginas',
        'inLanguage'      => 'Idioma',
        'withinLibraryId' => 'ID',
        'copyrightYear'   => 'Año',
        'publisher'       => 'Editorial',
        'publishingPlace' => 'Ciudad',
        'isbn'            => 'ISBN',
        'publicationId'   => 'PubID',
        'keywords'        => 'Categorías',
        'bookEdition'     => 'Edition',
        'collection'      => 'Biblioteca',
    ];

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly LibraryPage $page
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var LibraryTable $table */
        $table = $this->laminas->get(LibraryTable::class);

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
        $redirect  = LocalePrefix::redirect(
            $request,
            $this->urls,
            'library-imports/library',
            ['library_id' => $libraryId]
        );
        if (null !== $redirect) {
            return $redirect;
        }

        $refusal = $this->page->refuse($this->page->library($request), LibraryPage::ADMINISTRATE);
        if (null !== $refusal) {
            return $refusal;
        }

        //getLibraryImports() honours the table's own libraryId, so it is set first —
        //without it the list is every library's imports, which is what the laminas
        //action's own getLibraryId() call arranges a moment earlier.
        $table->setLibraryId($libraryId);
        $imports = $table->getLibraryImports();

        return new Response($this->twig->render('books/library-imports.html.twig', [
            //index.phtml sets no headTitle; the layout default stands.
            'page_title' => null,
            'library_id' => $libraryId,
            'objects'    => SionResult::rows($imports),
        ]));
    }

    /**
     * `createAction()` plus the two SionController hooks it overrides.
     *
     * `getPostDataForCreateAction()` adds the library id to the posted data — the form's
     * `libraryId` is a hidden field, so this is what stops a visitor retargeting the
     * import at another library — and `createEntityPostFormValidation()` adds the column
     * map before the row is written. Both are two lines and both are here rather than as
     * hooks on the shared create action, because one route needing a data-mutation hook
     * is not a reason to give every route one.
     *
     * Success redirects to the *edit* page, which runs the simulation and stays on
     * laminas. That is the redirect the spec declares and it is not changed here.
     */
    private function create(Request $request, LibraryTable $table): Response
    {
        $libraryId = (int) $request->attributes->get('library_id');
        $redirect  = LocalePrefix::redirect(
            $request,
            $this->urls,
            'library-imports/library/create',
            ['library_id' => $libraryId]
        );
        if (null !== $redirect) {
            return $redirect;
        }

        $refusal = $this->page->refuse($this->page->library($request), LibraryPage::ADMINISTRATE);
        if (null !== $refusal) {
            return $refusal;
        }

        $form = new ImportForm();
        //`Simulate import`, not `Save`: pressing it creates the record and lands the
        //visitor on the page that simulates the import against the spreadsheet.
        $form->get('submit')->setValue('Simulate import');

        if ($request->isMethod('POST')) {
            /** @var array<string, mixed> $posted */
            $posted              = $request->request->all();
            $posted['libraryId'] = $libraryId;
            $form->setData($posted);
            if ($form->isValid()) {
                /** @var array<string, mixed> $data */
                $data                  = $form->getData();
                $data['columnMapping'] = self::FIELDS_MAP;
                $newId                 = $table->createEntity('library-import', $data);
                if ($newId) {
                    (new FlashMessenger())
                        ->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                        ->addMessage('Library-import successfully created.');

                    return new RedirectResponse($this->urls->path(
                        'library-imports/library-import/edit',
                        ['import_id' => (int) $newId]
                    ));
                }
                $this->now('Error in form submission, please review.');
            } else {
                $this->now('Error in form submission, please review.');
            }
        }

        return new Response($this->twig->render('books/library-import-create.html.twig', [
            'page_title' => 'Begin new import',
            'form'       => $form,
            'fields_map' => self::sortedFieldsMap(),
            'self_url'   => $this->urls->path(
                'library-imports/library/create',
                ['library_id' => $libraryId]
            ),
        ]));
    }

    /**
     * `ksort($map)` — create.phtml sorts by field name before rendering the list.
     *
     * @return array<string, string>
     */
    private static function sortedFieldsMap(): array
    {
        $map = self::FIELDS_MAP;
        ksort($map);

        return $map;
    }

    private function now(string $message): void
    {
        /** @var NowMessenger $messenger */
        $messenger = $this->laminas->get('ControllerPluginManager')->get('nowMessenger');
        $messenger->setNamespace(NowMessenger::NAMESPACE_ERROR)->addMessage($message);
    }

    private function detail(Request $request, LibraryTable $table): Response
    {
        $importId = (int) $request->attributes->get('import_id');
        $redirect = LocalePrefix::redirect(
            $request,
            $this->urls,
            'library-imports/library-import',
            ['import_id' => $importId]
        );
        if (null !== $redirect) {
            return $redirect;
        }

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
        ]));
    }
}

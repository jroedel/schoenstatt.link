<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\LibraryPage;
use App\Laminas\ServiceBridge;
use App\Laminas\SionResult;
use Books\Form\SearchForm;
use Books\Model\LibraryTable;
use Books\Model\PublicationsTable;
use SionModel\Form\Element\Select;
use JTranslate\Model\TranslationsTable;
use Laminas\Translator\TranslatorInterface;
use SionModel\Problem\EntityProblem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function array_filter;
use function array_key_exists;
use function count;
use function is_array;
use function is_object;
use function is_string;
use function method_exists;
use function sprintf;
use function str_replace;
use function stripos;

/**
 * GET /libraries/{library_id} and /libraries/{library_id}/admin.
 *
 * One controller because the laminas pair is one action calling the other:
 * `adminAction()` opens with `$view = $this->showAction()` and then adds the menu, so
 * the admin page carries the search bar, the heading and the whole category listing
 * underneath its list of links. Reproducing that as two independent controllers would
 * be two copies of the search half.
 *
 * ## The search is the page
 *
 * `showAction()` runs `SearchForm` against the query string and, **only when more than
 * one field survives**, calls `searchBooks()` capped at 200 rows. That `count($data) > 1`
 * is not a stray guard: the form always yields `libraryId`, so "one field" means "no
 * search terms" and the page shows its category statistics instead. Reproduced exactly,
 * including the cap.
 *
 * ## What the admin menu counts
 *
 * Every badge is a count. A fourth used to call `ProblemService::autoFixProblems()` for
 * the auto-fix entry — a dry run, not a write, verified at the time — but that entry had
 * been commented out of the `pages` config for years, so the branch never ran, and the
 * route itself was retired on 2026-09-08 (docs/laminas-exit.md).
 */
final class LibraryController
{
    /** Route default marking this the admin variant rather than the library page. */
    public const ADMIN = '_library_admin';

    /** `searchBooks()` is capped here in the laminas action; the number is its own. */
    private const MAX_RESULTS = 200;

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly LibraryPage $page
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $isAdmin   = true === $request->attributes->get(self::ADMIN);
        $libraryId = (int) $request->attributes->get('library_id');
        $routeName = $isAdmin ? 'libraries/library/admin' : 'libraries/library';

        $library = $this->page->library($request);
        //**Both checks on the admin page, in this order.** `adminAction()` opens with
        //`$view = $this->showAction()`, so a visitor who may not *see* the library is
        //redirected by the show half before the `administrate` check ever throws. Asking
        //only about `administrate` would answer 403 where laminas answers 302.
        $refusal = $this->page->refuse($library, LibraryPage::SHOW);
        if (null === $refusal && $isAdmin) {
            $refusal = $this->page->refuse($library, LibraryPage::ADMINISTRATE);
        }
        if (null !== $refusal) {
            return $refusal;
        }
        /** @var array<string, mixed> $library */

        /** @var LibraryTable $table */
        $table = $this->laminas->get(LibraryTable::class);

        //`getLibraries()[$id]` rather than the row LibraryPage loaded: the statistics
        //arrays this page renders — categoryStatistics, collectionCategoryStatistics —
        //are built by getLibraries() and are absent from a single-row getObject().
        $libraries = $table->getLibraries();
        $entity    = is_array($libraries) && isset($libraries[$libraryId]) && is_array($libraries[$libraryId])
            ? $libraries[$libraryId]
            : $library;

        $table->registerVisit('library', $libraryId);

        [$books, $borrowers, $form] = $this->search($table, $request, $libraryId);

        $name = is_string($entity['name'] ?? null) ? $entity['name'] : '';

        if (! $isAdmin) {
            return new Response($this->twig->render('books/library.html.twig', [
                //show.phtml disables the title translator and passes the library's own
                //name, which is record content — translating it would file a phrase row
                //per library. `page_title_translate: false` is how the layout is told.
                'page_title'           => $name,
                'page_title_translate' => false,
                'breadcrumbs'          => $this->page->breadcrumbs($entity),
                'entity'               => $entity,
                'main_show_display'    => $this->display($entity),
                'form'                 => $form,
                'books'                => $books,
                'borrowers'            => $borrowers,
                'publications'         => $this->publications(),
            ]));
        }

        return new Response($this->twig->render('books/library-admin.html.twig', [
            //Translated **before** the name goes in, which is the order adminAction() uses:
            //`sprintf($this->translate('Administrate %s'), $name)`. Assembling first and
            //translating after would look up the whole sentence, library name included —
            //which finds nothing, files a phrase row per library, and rendered
            //"Administrate Bellavista" on /es where laminas says "Administrar".
            'page_title'           => sprintf($this->translate('Administrate %s'), $name),
            'page_title_translate' => false,
            'entity'               => $entity,
            'main_show_display'    => $this->display($entity),
            'form'                 => $form,
            'books'                => $books,
            'borrowers'            => $borrowers,
            'publications'         => $this->publications(),
            'pages'                => $this->adminPages($table, $libraryId),
        ]));
    }

    /** The page's own text domain, which is where its format strings live. */
    private function translate(string $message): string
    {
        /** @var TranslatorInterface $translator */
        $translator = $this->laminas->get('MvcTranslator');

        return $translator->translate($message, 'Books');
    }

    /**
     * The `MainShowDisplay` value, narrowed to a template branch this file has.
     *
     * `show-collections` is a valid option in the library edit form and has no .phtml on
     * the laminas side either, so choosing it makes the laminas page fatal. Falling back
     * to the default here is the one behaviour difference on this route.
     *
     * @param array<string, mixed> $entity
     */
    private function display(array $entity): string
    {
        $options = $entity['options'] ?? null;
        $value   = is_object($options) && isset($options->mainShowDisplay) ? $options->mainShowDisplay : null;

        return LibraryTable::MAIN_SHOW_DISPLAY_SHOW_COLLECTIONS_CATEGORIES === $value
            ? LibraryTable::MAIN_SHOW_DISPLAY_SHOW_COLLECTIONS_CATEGORIES
            : LibraryTable::MAIN_SHOW_DISPLAY_SHOW_CATEGORIES;
    }

    /**
     * The search half of `showAction()`.
     *
     * @return array{0: array<int|string, mixed>|null, 1: array<int|string, mixed>, 2: SearchForm}
     */
    private function search(LibraryTable $table, Request $request, int $libraryId): array
    {
        /** @var SearchForm $form */
        $form = $this->laminas->get(SearchForm::class);
        $collectionId = $form->get('collectionId');
        if ($collectionId instanceof Select) {
            $collectionId->setValueOptions($table->getCollectionValueOptions($libraryId));
        }

        $params              = $request->query->all();
        $params['libraryId'] = $libraryId;
        $form->setData($params);

        $books     = null;
        $borrowers = [];

        if ($form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            foreach ($data as $key => $value) {
                if (null === $value) {
                    unset($data[$key]);
                }
            }
            //`> 1` because libraryId is always present: one field means no search terms.
            if (count($data) > 1) {
                $books     = SionResult::rows($table->searchBooks($data, ['maxResults' => self::MAX_RESULTS]));
                $borrowers = SionResult::rows($this->laminas->get('Books\BorrowersValueOptions'));
            }
        }

        return [$books, $borrowers, $form];
    }

    /** @return array<int|string, mixed> */
    private function publications(): array
    {
        /** @var PublicationsTable $table */
        $table = $this->laminas->get(PublicationsTable::class);
        return SionResult::rows($table->getObjects('publication'));
    }

    /**
     * The admin menu's entries with their badge counts and `:libraryId` substituted.
     *
     * @return array<string, array<string, mixed>>
     */
    private function adminPages(LibraryTable $table, int $libraryId): array
    {
        $config = $this->laminas->config();
        $pages  = $config['books']['admin_pages'] ?? null;
        if (! is_array($pages)) {
            return [];
        }

        /** @var array<string, array<string, mixed>> $pages */
        if (isset($pages['jtranslate'])) {
            /** @var TranslationsTable $translations */
            $translations              = $this->laminas->get(TranslationsTable::class);
            $pages['jtranslate']['badges'] = [(string) $translations->getOutstandingTranslationCount()];
        }
        if (isset($pages['libraries/library/data-problems'])) {
            $pages['libraries/library/data-problems']['badges'] = $this->problemCounts($table, $libraryId);
        }
        if (isset($pages['library-imports/library'])) {
            //`getLibraryImports()` reads the table's own libraryId, and nothing on this
            //page has set it — so without this line the badge counts every library's
            //imports, or whatever a previous request left in the APCu entry cache. It
            //read 14 against laminas' 0 before this, on a library with none.
            $table->setLibraryId($libraryId);
            $pages['library-imports/library']['badges'] = [count(SionResult::rows($table->getLibraryImports()))];
        }

        foreach ($pages as $route => $values) {
            $params = $values['route_parameters'] ?? null;
            if (! is_array($params)) {
                continue;
            }
            foreach ($params as $key => $param) {
                if (is_string($param) && false !== stripos($param, ':libraryId')) {
                    $params[$key] = str_replace(':libraryId', (string) $libraryId, $param);
                }
            }
            $pages[$route]['route_parameters'] = $params;
        }

        return $pages;
    }

    /**
     * Problem counts by severity, zero-severities dropped — `getProblemCounts()`.
     *
     * @return array<string, int>
     */
    private function problemCounts(LibraryTable $table, int $libraryId): array
    {
        $library  = SionResult::rows($table->getObject('library', $libraryId, true));
        $problems = [
            ...SionResult::listOf($table->getLibraryProblems($library)),
            ...SionResult::listOf($table->getLibraryBookProblems($libraryId)),
        ];

        //Keys are EntityProblem's own severity strings, and SEVERITY_ERROR is 'danger'
        //rather than 'error' — the template maps them onto Bootstrap alert classes.
        $counts = [
            EntityProblem::SEVERITY_ERROR   => 0,
            EntityProblem::SEVERITY_WARNING => 0,
            EntityProblem::SEVERITY_INFO    => 0,
        ];
        foreach ($problems as $problem) {
            $severity = is_object($problem) && method_exists($problem, 'getSeverity')
                ? $problem->getSeverity()
                : null;
            if (is_string($severity) && array_key_exists($severity, $counts)) {
                $counts[$severity]++;
            }
        }

        return array_filter($counts, static fn (int $count): bool => 0 !== $count);
    }
}

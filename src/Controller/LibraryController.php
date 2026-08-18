<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\LibraryPage;
use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Books\Form\SearchForm;
use Books\Model\LibraryTable;
use Books\Model\PublicationsTable;
use SionModel\I18n\TranslationsTable;
use SionModel\Problem\EntityProblem;
use SionModel\Service\ProblemService;
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
 * ## What the admin menu counts, and the one that used to worry me
 *
 * Three of the four badges are counts. The fourth calls
 * `ProblemService::autoFixProblems()`, whose name says it writes — it does not: the
 * parameter is `$simulate = true` and the action passes nothing. Verified rather than
 * assumed, because a GET that silently repairs data is exactly the kind of thing this
 * batch went looking for, and this one is innocent.
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
        private readonly RouteUrl $urls,
        private readonly LibraryPage $page
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $isAdmin   = true === $request->attributes->get(self::ADMIN);
        $libraryId = (int) $request->attributes->get('library_id');
        $routeName = $isAdmin ? 'libraries/library/admin' : 'libraries/library';

        $redirect = LocalePrefix::redirect($request, $this->urls, $routeName, ['library_id' => $libraryId]);
        if (null !== $redirect) {
            return $redirect;
        }

        $library = $this->page->library($request);
        $refusal = $this->page->refuse($library, $isAdmin ? LibraryPage::ADMINISTRATE : LibraryPage::SHOW);
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
            'page_title'           => sprintf('Administrate %s', $name),
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
     * @return array{0: list<mixed>|null, 1: array<int|string, mixed>, 2: SearchForm}
     */
    private function search(LibraryTable $table, Request $request, int $libraryId): array
    {
        /** @var SearchForm $form */
        $form = $this->laminas->get(SearchForm::class);
        $form->get('collectionId')->setValueOptions($table->getCollectionValueOptions($libraryId));

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
                $found     = $table->searchBooks($data, ['maxResults' => self::MAX_RESULTS]);
                $books     = is_array($found) ? $found : [];
                $found     = $this->laminas->get('Books\BorrowersValueOptions');
                $borrowers = is_array($found) ? $found : [];
            }
        }

        return [$books, $borrowers, $form];
    }

    /** @return array<int|string, mixed> */
    private function publications(): array
    {
        /** @var PublicationsTable $table */
        $table = $this->laminas->get(PublicationsTable::class);
        $rows  = $table->getObjects('publication');

        return is_array($rows) ? $rows : [];
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
            $imports = $table->getLibraryImports();
            $pages['library-imports/library']['badges'] = [count(is_array($imports) ? $imports : [])];
        }
        if (isset($pages['sion-model/auto-fix-data-problems'])) {
            /** @var ProblemService $problems */
            $problems = $this->laminas->get(ProblemService::class);
            //$simulate defaults true, so this counts rather than repairs — see the class docblock
            $found    = $problems->autoFixProblems();
            $pages['sion-model/auto-fix-data-problems']['badges'] = [count(is_array($found) ? $found : [])];
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
        $library  = $table->getObject('library', $libraryId, true);
        $problems = [
            ...(is_array($found = $table->getLibraryProblems(is_array($library) ? $library : [])) ? $found : []),
            ...(is_array($found = $table->getLibraryBookProblems($libraryId)) ? $found : []),
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

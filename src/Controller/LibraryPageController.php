<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\LibraryPage;
use App\Laminas\SionResult;
use App\Laminas\ServiceBridge;
use Books\Model\LibraryTable;
use Laminas\Db\Sql\Predicate;
use RuntimeException;
use Schoenstatt\Model\SchoenstattTable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function array_key_exists;
use function is_array;
use function is_string;

/**
 * The library pages whose whole action is "check the ACL, read one thing, render".
 *
 * Four routes, one controller, selected by the `PAGE` route default. They are together
 * because separating them would produce four classes differing in one method call each —
 * `Books\Controller\LibrariesController` has them as four methods for the same reason.
 * Anything with a form, a write, or a template of its own gets its own class.
 *
 * | page | laminas action | permission |
 * |---|---|---|
 * | `label-management` | `labelManagementAction` | administrate |
 * | `book-list` | `bookListAction` | show |
 * | `book-list-json` | `getBookListJsonAction` | administrate |
 * | `data-problems` | `dataProblemsAction` | **none** |
 *
 * That last row is the one difference this class introduces. `dataProblemsAction()` makes
 * no per-library check at all while its thirteen siblings do, and its route guard is
 * `lib_user` — a default role — so every signed-in account can read any library's data
 * problems today, including book titles and call numbers from libraries they have no
 * part in. The port asks for `show`, which is the weakest of the two permissions and the
 * one the library's own `ViewRole` grants. Recorded here rather than filed silently,
 * because a reader comparing the two implementations will otherwise read it as a
 * transcription error.
 */
final class LibraryPageController
{
    /** Route default naming which of the four pages this route is. */
    public const PAGE = '_library_page';

    private const LABEL_MANAGEMENT = 'label-management';
    private const BOOK_LIST        = 'book-list';
    private const BOOK_LIST_JSON   = 'book-list-json';
    private const DATA_PROBLEMS    = 'data-problems';

    /**
     * page => per-library permission.
     *
     * This used to carry the laminas route name alongside, for the sole purpose of
     * assembling the locale-prefix redirect. App\Http\LocalePrefixListener derives that
     * from the matched route now, so the name was data this class no longer had a use for.
     */
    private const PAGES = [
        self::LABEL_MANAGEMENT => LibraryPage::ADMINISTRATE,
        self::BOOK_LIST        => LibraryPage::SHOW,
        self::BOOK_LIST_JSON   => LibraryPage::ADMINISTRATE,
        self::DATA_PROBLEMS    => LibraryPage::SHOW,
    ];

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly LibraryPage $page
    ) {
    }

    /**
     * `['libraryId' => $id]`, typed to satisfy `queryObjects()`'s @param.
     *
     * SionTable declares the parameter as predicates and documents it as
     * `array|PredicateInterface|PredicateInterface[]`; a column => value map is what every
     * caller in the application passes and what the method's own SQL builder expects. The
     * annotation is the thing that is wrong; this states the shape at one place instead of
     * suppressing it at each call.
     *
     * @return array<Predicate\PredicateInterface>
     */
    private function predicate(int $libraryId): array
    {
        /** @var array<Predicate\PredicateInterface> $map */
        $map = ['libraryId' => $libraryId];

        return $map;
    }

    public function __invoke(Request $request): Response
    {
        $page = $request->attributes->get(self::PAGE);
        if (! is_string($page) || ! array_key_exists($page, self::PAGES)) {
            throw new RuntimeException('A library page route declared no known page name.');
        }
        $permission = self::PAGES[$page];

        $libraryId = (int) $request->attributes->get('library_id');

        $library = $this->page->library($request);
        $refusal = $this->page->refuse($library, $permission);
        if (null !== $refusal) {
            return $refusal;
        }
        /** @var array<string, mixed> $library */

        /** @var LibraryTable $table */
        $table = $this->laminas->get(LibraryTable::class);

        return match ($page) {
            self::LABEL_MANAGEMENT => new Response(
                $this->twig->render('books/library-label-management.html.twig', [
                    'page_title' => 'Label management',
                ])
            ),
            self::BOOK_LIST => new Response(
                $this->twig->render('books/library-book-list.html.twig', [
                    //bookList.phtml sets no headTitle, so the layout default stands
                    //alone; null rather than absent, because layout.html.twig runs with
                    //strict_variables.
                    'page_title' => null,
                    'objects'    => SionResult::rows($this->books($table, $libraryId)),
                ])
            ),
            self::BOOK_LIST_JSON => new JsonResponse(['books' => $this->bookStatuses($table, $libraryId)]),
            default              => new Response(
                $this->twig->render('sion-model/data-problems.html.twig', [
                    //data-problems.phtml sets no headTitle; the layout default stands.
                    'page_title'         => null,
                    'problems'           => $this->problems($table, $library, $libraryId),
                    'displayEditPencil'  => true,
                ])
            ),
        };
    }

    /**
     * The JSON route's payload: every book's status, with the borrower id swapped for a
     * name.
     *
     * `getUnlinkedPersons()` is the same lookup the laminas action makes, and the same
     * `key_exists` guard: a checkout can name a person the projection does not return,
     * and the id is then left in place rather than blanked.
     *
     * @return array<int|string, mixed>
     */
    private function bookStatuses(LibraryTable $table, int $libraryId): array
    {
        $table->setLibraryId($libraryId);
        $books = $table->getLibraryBooksStatuses();
        if (! is_array($books)) {
            return [];
        }

        /** @var SchoenstattTable $schoenstatt */
        $schoenstatt = $this->laminas->get(SchoenstattTable::class);
        $persons     = $schoenstatt->getUnlinkedPersons();
        if (! is_array($persons)) {
            return $books;
        }

        foreach ($books as $bookId => $book) {
            if (! is_array($book) || ! isset($book['checkedOutBy'])) {
                continue;
            }
            $by = $book['checkedOutBy'];
            if (array_key_exists($by, $persons) && is_array($persons[$by])) {
                $books[$bookId]['checkedOutBy'] = $persons[$by]['fullFriendlyName'] ?? $by;
            }
        }

        return $books;
    }

    /**
     * @param array<string, mixed> $library
     * @return list<mixed>
     */
    private function problems(LibraryTable $table, array $library, int $libraryId): array
    {
        return [
            ...SionResult::listOf($table->getLibraryProblems($library)),
            ...SionResult::listOf($table->getLibraryBookProblems($libraryId)),
        ];
    }

    /** The library's books, as SionTable answers them — see App\Laminas\SionResult. */
    private function books(LibraryTable $table, int $libraryId): mixed
    {
        return $table->queryObjects('book', $this->predicate($libraryId));
    }
}

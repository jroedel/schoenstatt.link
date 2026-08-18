<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\LibraryPage;
use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\SionResult;
use App\Laminas\ServiceBridge;
use Books\Model\LibraryTable;
use Laminas\I18n\Translator\TranslatorInterface;
use RuntimeException;
use Schoenstatt\Model\SchoenstattTable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function array_key_exists;
use function is_array;
use function is_string;
use function sprintf;

/**
 * GET /checkouts/library/{library_id}[/current|/overdue] — one library's loan records.
 *
 * Three routes, one action: the laminas tree hangs `current` and `overdue` under
 * `checkouts/library` as Literal children that override nothing but the `subset` default,
 * inheriting `action => library` from the parent. (Worth stating because a child under a
 * `may_terminate` parent is *usually* the trap where the parent's action runs by
 * accident — here it is deliberate, and both children consume a path segment of their
 * own, so they really do match.)
 *
 * `subset` chooses the heading, the column set, and which rows `getCheckoutsForLibrary()`
 * returns. Nothing else differs.
 *
 * Gated on `administrate`, not `show`: this page names who has which book, which is a
 * person's reading. The route guard is `lib_user` and therefore admits every signed-in
 * account, so the per-library check is the only real protection — the same shape as
 * `Books\Controller\BorrowersController::showAction()`, and for the same reason.
 */
final class CheckoutsController
{
    /** Route default naming the subset — the laminas route's own parameter name. */
    public const SUBSET = 'subset';

    private const TITLES = [
        'all'     => 'Checkouts for %s',
        'current' => 'Current checkouts for %s',
        'overdue' => 'Overdue checkouts for %s',
    ];

    private const COLUMNS = [
        'all'     => ['book', 'borrower', 'checkedOutOn', 'dueOn', 'checkedInOn'],
        'current' => ['withinLibraryId', 'callNumber', 'book', 'borrower', 'checkedOutOn', 'dueOn'],
        'overdue' => ['withinLibraryId', 'book', 'borrower', 'checkedOutOn', 'dueOn'],
    ];

    private const ROUTES = [
        'all'     => 'checkouts/library',
        'current' => 'checkouts/library/current',
        'overdue' => 'checkouts/library/overdue',
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
        $subset = $request->attributes->get(self::SUBSET);
        if (! is_string($subset) || ! array_key_exists($subset, self::TITLES)) {
            throw new RuntimeException('A checkouts route declared no known subset.');
        }

        $libraryId = (int) $request->attributes->get('library_id');
        $redirect  = LocalePrefix::redirect(
            $request,
            $this->urls,
            self::ROUTES[$subset],
            ['library_id' => $libraryId]
        );
        if (null !== $redirect) {
            return $redirect;
        }

        $library = $this->page->library($request);
        $refusal = $this->page->refuse($library, LibraryPage::ADMINISTRATE);
        if (null !== $refusal) {
            return $refusal;
        }
        /** @var array<string, mixed> $library */

        /** @var LibraryTable $table */
        $table = $this->laminas->get(LibraryTable::class);
        /** @var SchoenstattTable $schoenstatt */
        $schoenstatt = $this->laminas->get(SchoenstattTable::class);

        $entities = $table->getCheckoutsForLibrary($libraryId, $subset);
        $persons  = $schoenstatt->getPersons();
        $name     = is_string($library['name'] ?? null) ? $library['name'] : '';

        return new Response($this->twig->render('books/checkouts-library.html.twig', [
            //The heading is a format string translated *before* the name is inserted, and
            //the name is record content: translating the assembled sentence would file a
            //phrase row per library. Hence page_title_translate false and the sprintf here.
            'page_title'           => sprintf($this->translate(self::TITLES[$subset]), $name),
            'page_title_translate' => false,
            'entities'             => SionResult::rows($entities),
            'columns'              => self::COLUMNS[$subset],
            'persons'              => SionResult::rows($persons),
        ]));
    }

    /** The page's own text domain, which is where its format strings live. */
    private function translate(string $message): string
    {
        /** @var TranslatorInterface $translator */
        $translator = $this->laminas->get('MvcTranslator');

        return $translator->translate($message, 'Books');
    }
}

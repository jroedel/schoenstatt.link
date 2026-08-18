<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\LibraryPage;
use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Books\Model\LibraryTable;
use Schoenstatt\Model\SchoenstattTable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_array;
use function is_string;

/**
 * GET /borrowers/{person_id} — one person's loan history, filtered to what the viewer
 * may see.
 *
 * ## The filter is the authorization, and it is not the route guard
 *
 * `route/borrowers/borrower` names `lib_user`, which is `is_default = 1`: every account
 * has it. Until the filter was added, any account holder could read any borrower's
 * complete loan history across every library — a person's reading, which is not public
 * information. What actually restricts this page is the per-checkout check below, on the
 * library each book belongs to.
 *
 * **Filtering rather than refusing** is deliberate and is the laminas behaviour: a
 * librarian responsible for one library should see that library's loans here, not a 403
 * caused by a book the person borrowed somewhere else.
 *
 * **One answer for two questions**, also deliberate: a person with no visible loans and
 * a person who does not exist both redirect to `/libraries`, so the page cannot be used
 * to discover which libraries a stranger borrows from.
 *
 * ## `/borrowers` itself is gone
 *
 * The parent route declared an `index` action that `BorrowersController` does not have,
 * rendering a template that does not exist; it answered 500 for every request. Retired
 * in this batch rather than ported — see docs/strangler.md.
 */
final class BorrowerController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly LibraryPage $page
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $personId = (int) $request->attributes->get('person_id');
        $redirect = LocalePrefix::redirect($request, $this->urls, 'borrowers/borrower', ['person_id' => $personId]);
        if (null !== $redirect) {
            return $redirect;
        }

        if ($personId <= 0) {
            return new RedirectResponse($this->urls->path('libraries'));
        }

        /** @var SchoenstattTable $schoenstatt */
        $schoenstatt = $this->laminas->get(SchoenstattTable::class);
        /** @var LibraryTable $table */
        $table = $this->laminas->get(LibraryTable::class);

        $person   = $schoenstatt->getSimplePerson($personId);
        $found    = $table->getCheckoutsForPerson($personId);
        $entities = [];
        $libraries = [];

        foreach (is_array($found) ? $found : [] as $checkoutId => $checkout) {
            $libraryId = $checkout['book']['libraryId'] ?? null;
            if (null === $libraryId || ! $this->page->isAllowed('library_' . $libraryId, LibraryPage::ADMINISTRATE)) {
                continue;
            }
            $entities[$checkoutId] = $checkout;

            $library = $checkout['book']['library'] ?? null;
            if (! isset($libraries[$libraryId]) && is_array($library)) {
                $libraries[$libraryId] = $library;
            }
        }

        if ([] === $entities) {
            return new RedirectResponse($this->urls->path('libraries'));
        }

        $buttons = [];
        foreach ($libraries as $libraryId => $library) {
            $buttons[] = [
                'name' => is_string($library['name'] ?? null) ? $library['name'] : '',
                //`onlyOverdueBorrowers => '0'` is what makes this button mean "email this
                //person about these books" rather than "run the overdue sweep".
                'href' => $this->urls->path(
                    'libraries/library/send-book-notices',
                    ['library_id' => $libraryId],
                    ['query' => ['borrowers' => [$personId], 'onlyOverdueBorrowers' => '0']]
                ),
            ];
        }

        $name = is_array($person) && is_string($person['fullFriendlyName'] ?? null)
            ? $person['fullFriendlyName']
            : '';

        return new Response($this->twig->render('books/borrower.html.twig', [
            'page_title'           => $name,
            'page_title_translate' => false,
            'entities'             => $entities,
            'notice_buttons'       => $buttons,
        ]));
    }
}

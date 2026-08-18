<?php

declare(strict_types=1);

namespace App\Controller;

use App\Authorization\Denial;
use App\Books\LibraryPage;
use App\Http\LocalePrefix;
use App\Http\MaintenanceKey;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Books\Model\LibraryTable;
use Books\Mailing\BooksMailer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_array;
use function is_string;
use function sprintf;

/**
 * GET /libraries/{library_id}/send-book-notices — preview the overdue notices, and send
 * them.
 *
 * ## Its route guard is `['user', 'guest']` and that is not the hole it looks like
 *
 * The guard admits everyone, because two different callers must reach this URL: a
 * librarian who administrates the library, and an automated one presenting a key. The
 * action does that check itself —
 *
 *     if (! isAllowed('library_<id>', 'administrate')) { assertApiKeyIn($apiKeys); }
 *
 * — and it is the only protection the endpoint has. Reproduced exactly, including the
 * OR: neither half may be dropped, and the check must stay *inside* rather than becoming
 * a route guard, because a route guard cannot express "or a valid key".
 *
 * The key must arrive in `X-Api-Key`. `?key=` was removed application-wide on 2026-08-17
 * — a query string is written verbatim into the access log — and this action was the last
 * caller of the query fallback. App\Http\MaintenanceKey::accepts() is the same comparison
 * the laminas trait makes, `hash_equals` included.
 *
 * ## Why this stayed a GET when refresh-sort did not
 *
 * Both send-book-notices and refresh-sort do real work on a GET, and only one of them was
 * converted. The difference is who can reach it: refresh-sort was open to every signed-in
 * account and had no check at all, so a link-prefetcher was enough. This route is behind
 * `administrate`-or-a-key, sends nothing unless `?simulate=0` is explicit, and its own
 * preview page is how a librarian decides to send. Converting it would break the
 * automated caller and the borrower page's "Send email regarding X books" links for a
 * risk that the check already covers. Filed as a question, not fixed in a porting batch.
 */
final class LibraryNoticesController
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
        $libraryId = (int) $request->attributes->get('library_id');
        $redirect  = LocalePrefix::redirect(
            $request,
            $this->urls,
            'libraries/library/send-book-notices',
            ['library_id' => $libraryId]
        );
        if (null !== $redirect) {
            return $redirect;
        }

        $library = $this->page->library($request);
        if (null === $library) {
            return $this->page->refuse(null, LibraryPage::ADMINISTRATE) ?? new Response('', 404);
        }

        $resourceId = is_string($library['resourceId'] ?? null) ? $library['resourceId'] : '';
        if (! $this->page->isAllowed($resourceId, LibraryPage::ADMINISTRATE)) {
            $keys = $this->laminas->config()['schoenstatt']['api_keys'] ?? null;
            if (! MaintenanceKey::accepts($keys, $request)) {
                return Denial::forbiddenPage($this->twig, $resourceId);
            }
        }

        //Every one of these defaults matters. `simulate` defaults **true**, so a bare GET
        //previews; `onlyOverdueBorrowers` defaults **true**, so a bare GET is the overdue
        //sweep rather than a mail-everyone; `borrowers` defaults to the empty list, which
        //the mailer reads as "no subset". The borrower page's button sets the first two.
        $simulate  = (bool) $request->query->get('simulate', true);
        $subset    = $request->query->all('borrowers');
        $onlyOverdue = (bool) $request->query->get('onlyOverdueBorrowers', true);

        /** @var BooksMailer $mailer */
        $mailer    = $this->laminas->get(BooksMailer::class);
        $borrowers = $mailer->sendBookNotices($libraryId, $onlyOverdue, $simulate, $subset);

        $query             = $request->query->all();
        $query['simulate'] = 0;

        return new Response($this->twig->render('books/library-book-notices.html.twig', [
            'page_title'           => sprintf(
                'Book notices for %s',
                is_string($library['name'] ?? null) ? $library['name'] : ''
            ),
            'page_title_translate' => false,
            'library'              => $library,
            'simulate'             => $simulate,
            'borrowers'            => is_array($borrowers) ? $borrowers : [],
            'send_now_url'         => $this->urls->path(
                'libraries/library/send-book-notices',
                ['library_id' => $libraryId],
                ['query' => $query]
            ),
        ]));
    }
}

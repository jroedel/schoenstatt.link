<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\ServiceBridge;
use Books\Model\BorrowerTokenTable;
use Books\Model\LibraryTable;
use Schoenstatt\Model\SchoenstattTable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_array;
use function is_string;

/**
 * GET/POST /library/my-books — a borrower's own checkouts, reached by an emailed link.
 *
 * ## The token is the authorization, and it is the whole authorization
 *
 * The route is declared `RouteAccess::openToEveryone()` because the ACL has nothing
 * useful to say here: the caller is not signed in and has no account. What stands in
 * for a session is the `?t=` token, which resolves to exactly one person at one
 * library (see Books\Model\BorrowerTokenTable for why an account was the wrong
 * answer). Every read and every write below is scoped by the personId the token
 * resolved to — never by anything in the request.
 *
 * That last point is the one to preserve. There is no `person_id` in the URL and
 * there must never be one: the moment a request parameter can name a person, the
 * token stops being a grant over one person's data and becomes a grant over the
 * library's. The renewal POST therefore carries a checkoutId and the controller
 * re-checks that the checkout belongs to the token's person before touching it —
 * checking ownership at the point of use rather than trusting that the form was
 * rendered from a page that only listed their own.
 *
 * ## Why it is Symfony-side
 *
 * Not merely because new work goes here. A laminas route would have been guarded by
 * BjyAuthorize, and the guard vocabulary available is roles — `lib_user` and friends,
 * every one of them `is_default = 1` and therefore meaning "signed in". There is no
 * way to say "this one person, via this token" in that vocabulary. Here the check is
 * ordinary code with an ordinary test.
 */
final class BorrowerCheckoutsController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $token = $request->query->get('t');
        if (! is_string($token) || '' === $token) {
            return $this->refuse();
        }

        /** @var BorrowerTokenTable $tokens */
        $tokens = $this->laminas->get(BorrowerTokenTable::class);
        $grant = $tokens->redeem($token);
        if (null === $grant) {
            return $this->refuse();
        }

        /** @var LibraryTable $libraryTable */
        $libraryTable = $this->laminas->get(LibraryTable::class);
        /** @var SchoenstattTable $schoenstatt */
        $schoenstatt = $this->laminas->get(SchoenstattTable::class);

        if ($request->isMethod('POST')) {
            $outcome = $this->renew($request, $libraryTable, $grant['personId'], $grant['libraryId']);

            //POST/redirect/GET so a refresh cannot renew twice. The outcome travels as
            //a short code in the query rather than in a flash message, because this
            //page has no session to put one in: the application's flash messenger is
            //laminas-session-backed and a borrower here is not signed in. The code is
            //looked up in a fixed table below, so nothing from the request reaches the
            //rendered page.
            return new RedirectResponse('/library/my-books?t=' . urlencode($token) . '&msg=' . $outcome);
        }

        $library   = $libraryTable->getObject('library', $grant['libraryId']);
        $person    = $schoenstatt->getSimplePerson($grant['personId']);
        $checkouts = $this->ownCheckouts($libraryTable, $grant['personId'], $grant['libraryId']);

        return new Response($this->twig->render('books/borrower-checkouts.html.twig', [
            //layout.html.twig reads page_title unconditionally; omitting it is a Twig
            //RuntimeError, which the error handler turns into an empty 200 rather than
            //anything that looks like a failure.
            'page_title' => 'Your books',
            'library'   => $library,
            'person'    => $person,
            'checkouts' => $checkouts,
            'token'     => $token,
            'notice'    => $this->notice((string) $request->query->get('msg', ''), $library),
            'maximum'   => (int) $library['options']->maximumBookRenewals,
        ]));
    }

    /**
     * The checkouts this token may see: one person, one library, still out.
     *
     * getCheckoutsForPerson() spans every library the person has ever borrowed from
     * and includes returned books, so filtering here is not tidying — it is the
     * difference between the token's stated scope and something wider.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ownCheckouts(LibraryTable $libraryTable, int $personId, int $libraryId): array
    {
        $all = $libraryTable->getCheckoutsForPerson($personId);
        if (! is_array($all)) {
            return [];
        }
        $mine = [];
        foreach ($all as $checkoutId => $checkout) {
            if (isset($checkout['checkedInOn']) && null !== $checkout['checkedInOn']) {
                continue;
            }
            if ((int) ($checkout['book']['libraryId'] ?? 0) !== $libraryId) {
                continue;
            }
            $mine[(int) $checkoutId] = $checkout;
        }

        return $mine;
    }

    /** @return string one of the keys understood by notice() */
    private function renew(Request $request, LibraryTable $libraryTable, int $personId, int $libraryId): string
    {
        $checkoutId = (int) $request->request->get('checkoutId');

        //Ownership is re-checked here rather than assumed from the rendered page. A
        //POST is just bytes; that it was preceded by a GET of a list containing only
        //this person's checkouts is not something the server may take on faith.
        //
        //No CSRF token, deliberately: this page carries no ambient authority. There is
        //no session and no cookie to ride, so a forged cross-site POST would have to
        //include the borrower token — and anyone holding that can renew directly. A
        //CSRF field would be ceremony protecting nothing.
        if (! isset($this->ownCheckouts($libraryTable, $personId, $libraryId)[$checkoutId])) {
            return 'notyours';
        }

        return match ($libraryTable->renewBook($checkoutId)['status']) {
            LibraryTable::RENEW_OK               => 'renewed',
            LibraryTable::RENEW_LIMIT_REACHED    => 'limit',
            LibraryTable::RENEW_DISABLED         => 'disabled',
            LibraryTable::RENEW_ALREADY_RETURNED => 'returned',
            default                              => 'error',
        };
    }

    /**
     * Turn the redirect's `msg` code into a message. A fixed table, so the query
     * string can choose between our sentences but never contribute one.
     *
     * @param array<string, mixed> $library
     * @return array{level: string, message: string}|null
     */
    private function notice(string $code, array $library): ?array
    {
        $maximum = (int) $library['options']->maximumBookRenewals;

        return match ($code) {
            'renewed'  => [
                'level'   => 'success',
                'message' => 'Renewed. The new due date is in your list below.',
            ],
            'returned' => [
                'level'   => 'success',
                'message' => 'That book is already back with the library — nothing to renew.',
            ],
            'limit'    => [
                'level'   => 'error',
                'message' => 'That book has already been renewed ' . $maximum . ' times, which is this '
                    . 'library\'s limit. Please reply to the notice to arrange it with the librarian.',
            ],
            'disabled' => [
                'level'   => 'error',
                'message' => 'This library does not offer online renewals. Please reply to the notice.',
            ],
            'notyours' => ['level' => 'error', 'message' => 'That book is not on your list.'],
            'error'    => ['level' => 'error', 'message' => 'That book could not be renewed.'],
            default    => null,
        };
    }

    /**
     * One answer for every bad token — unknown, expired, revoked or malformed.
     *
     * Distinguishing them would tell an unauthenticated caller whether a token ever
     * existed, and the borrower cannot act on the difference anyway: in all four
     * cases the thing to do is ask the librarian for a fresh link.
     */
    private function refuse(): Response
    {
        return new Response(
            $this->twig->render('books/borrower-checkouts-expired.html.twig', [
                'page_title' => 'Link no longer valid',
            ]),
            Response::HTTP_NOT_FOUND
        );
    }
}

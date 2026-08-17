<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * Who can reach a library's lending form, and — just as importantly — who is *meant* to.
 *
 * The checkout route had no test of any kind until 2026-08-17, which is part of why the
 * per-library rules behind it went years without review. This file pins both halves of
 * the design, because only pinning one of them invites the other to be "fixed":
 *
 *  - **Schoenstatt Fathers Austin (6) is open on purpose.** `CheckoutBooksRole = guest`,
 *    which `LibraryTable::getRules()` expands to `[guest, user]`, and `user` is the root
 *    every library role descends from — so every signed-in account can reach the lending
 *    form there. That reads like a misconfiguration and is not one. In a religious
 *    community, a checkout that is not effortless simply does not happen: people take the
 *    book to their room and nothing is recorded. The accepted worst case is a bot marking
 *    books checked out that were not, which is recoverable; unrecorded loans are not.
 *    **If this test fails because someone tightened the rule, read this paragraph before
 *    "fixing" the test.**
 *
 *  - **Colegio Mayor (3) is restricted on purpose.** `CheckoutBooksRole = lib_patres`, an
 *    internal library serving its own community. It is the proof that restriction is used
 *    where it is wanted, and the reason the openness above cannot be dismissed as nobody
 *    having thought about it.
 *
 * The distinction the ACL is actually drawing is *who may borrow*, not who may work a
 * desk. There is no user-to-person link in this database — `sch_persons` was built for the
 * /movement route and reused — so the borrower is picked from a list rather than inferred
 * from the account. `docs/libraries.md` has the whole picture.
 */
class LibraryCheckoutAuthorizationSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** Distinct from the other suites' prefixes, or one class's tearDown deletes another's accounts. */
    private const EMAIL_PREFIX = 'libcheckout-smoke-';

    /** `CheckoutBooksRole = guest`, deliberately open to every signed-in account. */
    private const OPEN_LIBRARY = 6;

    /** `CheckoutBooksRole = lib_patres`, deliberately restricted. */
    private const RESTRICTED_LIBRARY = 3;

    /**
     * The borrower select is what makes this the lending form rather than any other page.
     * Matched on the attribute, not on the label: the label is "Who's checking out?" and
     * the apostrophe reaches the body as `&#039;`, so asserting the readable string fails
     * against a page that renders perfectly.
     */
    private const FORM_MARKER_NOTE = 'the lending form should carry its borrower select';

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    private function checkoutPath(int $libraryId): string
    {
        return '/en/libraries/' . $libraryId . '/checkout';
    }

    /**
     * Anonymous visitors get the sign-in redirect, at both libraries.
     *
     * This is the route guard (`lib_user`) rather than the per-library rule, and it is the
     * only thing standing between an anonymous visitor and the open library — worth its
     * own assertion because `CheckoutBooksRole = guest` would otherwise admit them.
     */
    public function testAnonymousVisitorsAreSentToSignIn(): void
    {
        foreach ([self::OPEN_LIBRARY, self::RESTRICTED_LIBRARY] as $libraryId) {
            $this->assertRequiresLogin($this->checkoutPath($libraryId));
        }
    }

    /**
     * An account with nothing but the default roles reaches the open library's form.
     *
     * Registration grants lib_user, pub_user, sch_user and bib_user by itself, so this is
     * the weakest identity the site issues — and at library 6 that is enough, by design.
     */
    public function testADefaultAccountReachesTheOpenLibrary(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get($this->checkoutPath(self::OPEN_LIBRARY), false, $jar);

        $this->assertSame(
            200,
            $response['status'],
            'library ' . self::OPEN_LIBRARY . ' is deliberately open to every signed-in account — '
            . 'see this class\'s docblock before changing it'
        );
        $this->assertStringContainsString('name="personId"', $response['body'], self::FORM_MARKER_NOTE);
    }

    /**
     * The same account does not reach the restricted library's form.
     *
     * `CheckoutsController::createAction()` throws `UnAuthorizedException` before rendering
     * anything, so what a denied visitor gets is decided by JUser's RedirectionStrategy
     * rather than by the controller. The assertion is "not 200, and none of the form" —
     * pinning the exact status would pin the strategy, which is a separate decision.
     */
    public function testADefaultAccountIsRefusedByTheRestrictedLibrary(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get($this->checkoutPath(self::RESTRICTED_LIBRARY), false, $jar);

        $this->assertNotSame(
            200,
            $response['status'],
            'library ' . self::RESTRICTED_LIBRARY . ' is lib_patres only; a default account must not '
            . 'reach its lending form'
        );
        $this->assertStringNotContainsString('name="personId"', $response['body'], 'the form leaked');
    }

    /**
     * An account holding `lib_patres` does reach the restricted library's form.
     *
     * Without this, the assertion above would still pass if the route broke outright, and
     * "nobody can reach it" would look exactly like "the restriction works".
     */
    public function testALibPatresAccountReachesTheRestrictedLibrary(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_patres']);

        $response = $this->get($this->checkoutPath(self::RESTRICTED_LIBRARY), false, $jar);

        $this->assertSame(
            200,
            $response['status'],
            'lib_patres is exactly the role library ' . self::RESTRICTED_LIBRARY . ' names'
        );
        $this->assertStringContainsString('name="personId"', $response['body'], self::FORM_MARKER_NOTE);
    }
}

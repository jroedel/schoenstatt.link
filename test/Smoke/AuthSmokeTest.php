<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PDO;

/**
 * End-to-end characterization of the passwordless (email magic-link) sign-in.
 *
 * The flow under test, all over plain HTTP against the running app:
 *   1. GET the login form (needs the GDPR consent cookie, else the app serves
 *      an explainer instead of a form and refuses to set any cookie).
 *   2. POST an email address with the form's CSRF token.
 *   3. The app mails a single-use link; we fish it out of Mailpit's REST API.
 *   4. GETting that link authenticates the session and burns the token.
 *
 * Side effects this test creates and cleans up again in tearDown():
 *   - accounts, because an unknown address is registered on the spot
 *     (open registration), plus their user_role_linker rows;
 *   - messages sitting in Mailpit.
 *
 * NOTE the addresses use @example.com, not the RFC 2606 @example.test: the
 * form's email validator checks the TLD against IANA's list and .test is not
 * on it, so an @example.test address is rejected before anything is sent.
 */
class AuthSmokeTest extends SmokeTestCase
{
    // The flow itself lives here, because two other tests need it too: the
    // authorization smoke test needs a signed-in session and tools/form-regression.php
    // needed one before it. This class characterizes the flow; the trait performs it.
    use MagicLinkSignIn;

    /** Local-part prefix of every address this test invents; also the cleanup key. */
    private const EMAIL_PREFIX = 'smoke-test-';

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    public function testMagicLinkSignInRoundTrip(): void
    {
        $email = $this->uniqueEmail();
        $jar = $this->newCookieJar();

        // 1. ask for a link
        $response = $this->requestSignInLink($jar, $email);
        $this->assertSame(200, $response['status'], 'requesting a link should render a page, not redirect');
        $this->assertStringContainsString(
            '<h1>Check your email</h1>',
            $response['body'],
            'the app should confirm without saying whether the address is known'
        );

        // 2. the mail, and the display name derived from the local part
        $message = $this->awaitMessageFor($email);
        $this->assertSame(
            'Your sign-in link for schoenstatt.link',
            $message['Subject'],
            'sign-in mail subject'
        );
        $localPart = strstr($email, '@', true);
        $this->assertStringContainsString(
            (string) $localPart,
            $message['Text'],
            'the mail greets the user by the display name derived from the email local part'
        );

        // 3. the link itself: absolute, canonical host, 64 hex characters of token
        $verifyUrl = $this->extractVerifyUrl($message['Text']);
        $verifyPath = $this->toLocalPath($verifyUrl);
        $this->assertMatchesRegularExpression(
            '#^/[a-z]{2}/user/verify\?token=[0-9a-f]{64}$#',
            $verifyPath,
            'shape of the magic link'
        );

        // 4. redeeming it authenticates the session
        $verify = $this->get($verifyPath, false, $jar);
        $this->assertSame(302, $verify['status'], 'a valid link should sign the visitor in and redirect');
        $this->assertStringEndsWith('/en/', $verify['redirect'], 'signed-in users land on the localized home page');

        $home = $this->get('/en/', false, $jar);
        $this->assertSame(200, $home['status']);
        $this->assertStringContainsString('>Logout</a>', $home['body'], 'the nav should offer a way out');
        $this->assertStringNotContainsString('>Sign in</a>', $home['body'], 'the nav should no longer offer sign-in');

        // 5. single use: the very same link is dead now
        $replay = $this->get($verifyPath, false, $jar);
        $this->assertSame(400, $replay['status'], 'a link must not work twice');
        $this->assertStringContainsString('sign-in link didn', $replay['body'], 'the "link did not work" page');

        // 6. and logging out puts the session back to anonymous
        $logout = $this->get('/en/user/logout', false, $jar);
        $this->assertSame(302, $logout['status']);
        $this->assertStringContainsString('/user/login', $logout['redirect']);

        $homeAgain = $this->get('/en/', false, $jar);
        $this->assertStringContainsString('>Sign in</a>', $homeAgain['body'], 'the nav is anonymous again');
        $this->assertStringNotContainsString('>Logout</a>', $homeAgain['body']);
    }

    /**
     * A token that was never issued is refused exactly like a spent one:
     * same status, same page, nothing that tells the two apart.
     */
    public function testBogusTokenRejected(): void
    {
        $response = $this->get('/en/user/verify?token=' . str_repeat('0', 64), false, $this->newCookieJar());

        $this->assertSame(400, $response['status'], 'an unknown token should be refused');
        $this->assertStringContainsString('sign-in link didn', $response['body']);
    }

    /**
     * The point of the flow: the response must not leak whether an account
     * exists. An unknown address is registered silently, a known one just gets
     * another link, and both look the same from the outside.
     */
    public function testUniformResponseForUnknownAndKnownEmail(): void
    {
        $marker = '<h1>Check your email</h1>';
        $unknown = $this->uniqueEmail();

        // first time: nobody has this address
        $first = $this->requestSignInLink($this->newCookieJar(), $unknown);
        $this->assertSame(200, $first['status']);
        $this->assertStringContainsString($marker, $first['body']);

        // second time, fresh session: the account now exists
        $known = $this->requestSignInLink($this->newCookieJar(), $unknown);
        $this->assertSame(200, $known['status']);
        $this->assertStringContainsString($marker, $known['body']);

        // a third, still unknown address, to be sure the marker is not accidental
        $other = $this->requestSignInLink($this->newCookieJar(), $this->uniqueEmail());
        $this->assertStringContainsString($marker, $other['body']);

        // and none of the three offers the form back or hints at an account
        foreach (['unknown' => $first, 'known' => $known, 'other' => $other] as $label => $response) {
            $this->assertStringNotContainsString(
                'name="email"',
                $response['body'],
                "the $label address should not get the form re-rendered (that would signal a rejection)"
            );
        }
    }

    /**
     * Asking again within the throttle window is answered identically but
     * sends nothing, so a stranger cannot use the endpoint to flood an inbox.
     */
    public function testResendWithinThrottleWindowSendsNoSecondMail(): void
    {
        $email = $this->uniqueEmail();

        $this->requestSignInLink($this->newCookieJar(), $email);
        $this->awaitMessageFor($email);

        $second = $this->requestSignInLink($this->newCookieJar(), $email);
        $this->assertSame(200, $second['status']);
        $this->assertStringContainsString('<h1>Check your email</h1>', $second['body'], 'same answer either way');

        // give a would-be second mail as long as the first one needed
        usleep(self::MAIL_POLL_MICROSECONDS * 4);
        $this->assertCount(
            1,
            $this->searchMailFor($email),
            'the second request inside the throttle window must not send another mail'
        );
    }

    /**
     * Redeeming a link proves the address works, for EVERY account — not just
     * brand-new ones. Regression test for a gap where only inactive accounts
     * (via activateUser) ever got email_verified set: an account that was
     * already active but never verified could sign in forever without the
     * flag flipping.
     *
     * **Since 2026-08-21 that is the only case there is**, which makes this test more
     * central than it was rather than redundant. `createUserFromEmail()` now creates
     * accounts active-and-unverified — it had to, once `state = 0` became a hard refusal —
     * so `clearVerificationToken()` is the *sole* thing that ever sets `email_verified`,
     * and `activateUser()` has no caller at all. The explicit UPDATE below is kept because
     * the test must not depend on the default shape of a new row to be testing the right
     * thing; it just no longer changes anything, hence the row-exists check rather than a
     * rows-changed one (PDO counts changed rows, not matched ones).
     */
    public function testSignInMarksEmailVerifiedEvenForActiveAccounts(): void
    {
        $email = $this->uniqueEmail();
        $jar = $this->newCookieJar();

        // create the account and obtain a live token the normal way
        $this->requestSignInLink($jar, $email);
        $verifyPath = $this->toLocalPath($this->extractVerifyUrl($this->awaitMessageFor($email)['Text']));

        // state the gap scenario explicitly: active, but email never verified
        $this->pdo()
            ->prepare('UPDATE user SET state = 1, email_verified = 0 WHERE email = :email')
            ->execute(['email' => $email]);
        $exists = $this->pdo()->prepare('SELECT COUNT(*) FROM user WHERE email = :email');
        $exists->execute(['email' => $email]);
        $this->assertSame(1, (int) $exists->fetchColumn(), 'the account should exist by now');

        $verify = $this->get($verifyPath, false, $jar);
        $this->assertSame(302, $verify['status'], 'the link should still sign the user in');

        $row = $this->pdo()->prepare(
            'SELECT email_verified, verification_token FROM user WHERE email = :email'
        );
        $row->execute(['email' => $email]);
        $state = $row->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('1', (string) $state['email_verified'], 'redeeming the link must verify the address');
        $this->assertNull($state['verification_token'], 'and still burn the token');
    }

    /**
     * Cookies are load-bearing for the whole flow, so without consent the app
     * reroutes even the verify link to the explainer instead of failing it.
     * That matters: the token stays unspent and the link still works once the
     * visitor consents.
     */
    public function testNoConsentIsRedirectedToExplainer(): void
    {
        $response = $this->get('/en/user/verify?token=whatever', false, $this->newCookieJar(false));

        $this->assertSame(200, $response['status'], 'no consent means an explainer, not a rejection');
        $this->assertStringContainsString('<h1>Sign In</h1>', $response['body']);
    }

    // ------------------------------------------------- the properties of the token

    /**
     * A token past its expiry is refused, and the clock is the only thing that changed.
     *
     * Expiry is one of the four things a single-use emailed credential rests on and it was
     * the one with no test at all until 2026-08-21 — single use is asserted in step 5 of
     * the round trip above, the throttle and the uniform response have their own tests,
     * and this did not, because a test cannot wait out the window.
     *
     * So the window is moved instead of waited out: the row's `verification_expiration` is
     * pushed into the past, which is exactly the state a link left in a mailbox overnight
     * reaches on its own. Nothing else is touched — same token, same account, same
     * session.
     *
     * The token is deliberately **not** consumed on this path (`redeemToken()` returns
     * before burning it), and that is asserted too: it costs nothing, since an expired
     * digest can never be redeemed again, and if the order ever inverted, a *live* token
     * would start being spent by a failed attempt.
     */
    public function testAnExpiredTokenIsRefused(): void
    {
        $email = $this->uniqueEmail();
        $jar   = $this->newCookieJar();

        $this->requestSignInLink($jar, $email);
        $verifyPath = $this->toLocalPath($this->extractVerifyUrl($this->awaitMessageFor($email)['Text']));

        $expire = $this->pdo()->prepare(
            'UPDATE user SET verification_expiration = :past WHERE email = :email'
        );
        $expire->execute(['past' => '2020-01-01 00:00:00', 'email' => $email]);
        $this->assertSame(1, $expire->rowCount(), 'the account should exist by now');

        $verify = $this->get($verifyPath, false, $jar);
        $this->assertSame(400, $verify['status'], 'an expired link must not sign anyone in');
        $this->assertStringContainsString('sign-in link didn', $verify['body']);

        $home = $this->get('/en/', false, $jar);
        $this->assertStringContainsString('>Sign in</a>', $home['body'], 'the session must still be anonymous');

        $row = $this->pdo()->prepare('SELECT verification_token FROM user WHERE email = :email');
        $row->execute(['email' => $email]);
        $this->assertNotNull(
            $row->fetchColumn(),
            'an expired token is refused before it is burned; if that inverts, a live token '
            . 'would be spent by a failed attempt'
        );
    }

    /**
     * Only a digest of the emailed token is stored.
     *
     * The plaintext exists in exactly two places — the email, and the reader's browser
     * when they click — and never in the database. So a read of `user` is not a set of
     * bearer credentials, which is the whole reason `UserTable::hashToken()` exists.
     *
     * Asserted by recomputing the digest rather than merely checking that the column
     * differs from the plaintext: "different" would also be satisfied by a truncation, an
     * encoding, or the wrong token entirely.
     */
    public function testOnlyADigestOfTheEmailedTokenIsStored(): void
    {
        $email = $this->uniqueEmail();

        $this->requestSignInLink($this->newCookieJar(), $email);
        $verifyUrl = $this->extractVerifyUrl($this->awaitMessageFor($email)['Text']);

        $this->assertSame(1, preg_match('/token=([0-9a-f]{64})/', $verifyUrl, $m), 'the emailed token');
        $plaintext = $m[1];

        $row = $this->pdo()->prepare('SELECT verification_token FROM user WHERE email = :email');
        $row->execute(['email' => $email]);
        $stored = (string) $row->fetchColumn();

        $this->assertNotSame($plaintext, $stored, 'the plaintext token must never be stored');
        $this->assertSame(hash('sha256', $plaintext), $stored, 'the column holds its sha256 digest');
    }

    /**
     * Signing in issues a new session id.
     *
     * The session-fixation defence: an attacker who can fix a victim's session id before
     * they authenticate holds an authenticated session afterwards, unless the id changes
     * at the moment the privilege level does. `verifyAction()` calls
     * `regenerateId(true)` for that, one line with a three-word comment and, until
     * 2026-08-21, no test — and a port that establishes the identity without it would
     * pass every other assertion in this file.
     */
    public function testSigningInIssuesANewSessionId(): void
    {
        $email = $this->uniqueEmail();
        $jar   = $this->newCookieJar();

        $this->requestSignInLink($jar, $email);
        $before = $this->sessionIdIn($jar);
        $this->assertNotSame('', $before, 'asking for a link should already have started a session');

        $verifyPath = $this->toLocalPath($this->extractVerifyUrl($this->awaitMessageFor($email)['Text']));
        $this->assertSame(302, $this->get($verifyPath, false, $jar)['status']);

        $this->assertNotSame(
            $before,
            $this->sessionIdIn($jar),
            'the session id must change when the privilege level does'
        );
    }

    // ------------------------------------------------------- deactivated accounts

    /**
     * A deactivated account is sent no link — and the page does not say so.
     *
     * Both halves matter. `user`.`state` means "may sign in" as of 2026-08-21; before
     * that it meant nothing at all, because `verifyAction()` activated whatever it
     * redeemed, so every deactivated account reactivated itself on its next sign-in link.
     * 32 of 292 real accounts sat at `state = 0` and could all sign in.
     *
     * The second half is the response. It has to be byte-identical to the one a live
     * account gets, for the same reason an unknown address gets the "check your email"
     * page: a distinguishable answer turns the sign-in form into an oracle, here for
     * which accounts an administrator has disabled.
     */
    public function testADeactivatedAccountIsSentNoLinkAndIsNotToldSo(): void
    {
        $email = $this->uniqueEmail();

        //created by the first request, which is also the only way to make one here
        $this->requestSignInLink($this->newCookieJar(), $email);
        $this->awaitMessageFor($email);
        $this->purgeMail();

        $deactivate = $this->pdo()->prepare('UPDATE user SET state = 0 WHERE email = :email');
        $deactivate->execute(['email' => $email]);
        $this->assertSame(1, $deactivate->rowCount());

        $response = $this->requestSignInLink($this->newCookieJar(), $email);
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            '<h1>Check your email</h1>',
            $response['body'],
            'the answer must not reveal that the account is disabled'
        );

        //as long as a real mail took to arrive, above
        usleep(self::MAIL_POLL_MICROSECONDS * 4);
        $this->assertSame([], $this->searchMailFor($email), 'no link may be mailed to a deactivated account');
    }

    /**
     * …and a link that was already in flight stops working.
     *
     * The defence-in-depth half, and the one that decides whether "deactivate" means
     * *now* or *after whatever is in that inbox expires*. An administrator revoking
     * access has to mean now.
     *
     * A different page and a different status from the expired/unknown case, deliberately:
     * "the link did not work" invites a retry that will fail forever, while "access has
     * been turned off" names the only thing that fixes it. Saying so leaks nothing —
     * reaching this page requires holding a live token for that very account.
     */
    public function testALiveLinkForADeactivatedAccountIsRefused(): void
    {
        $email = $this->uniqueEmail();
        $jar   = $this->newCookieJar();

        $this->requestSignInLink($jar, $email);
        $verifyPath = $this->toLocalPath($this->extractVerifyUrl($this->awaitMessageFor($email)['Text']));

        $deactivate = $this->pdo()->prepare('UPDATE user SET state = 0 WHERE email = :email');
        $deactivate->execute(['email' => $email]);
        $this->assertSame(1, $deactivate->rowCount());

        $verify = $this->get($verifyPath, false, $jar);
        $this->assertSame(403, $verify['status'], 'a deactivated account may not sign in');
        $this->assertStringContainsString('deactivated', $verify['body']);
        $this->assertStringNotContainsString(
            'Send me a new link',
            $verify['body'],
            'offering another link here would be an invitation to retry forever'
        );

        $home = $this->get('/en/', false, $jar);
        $this->assertStringContainsString('>Sign in</a>', $home['body'], 'the session must still be anonymous');
    }

    /**
     * Deactivation reaches a session that is already open, on its next request.
     *
     * The half that decides whether `state = 0` means "cannot act" or merely "cannot sign
     * in again". Without it, an administrator revoking access to a compromised account
     * would be waiting on a session timeout they cannot see, influence or verify.
     *
     * Nothing was built for this: `JUser\Authentication\Storage\SessionUser` keeps only
     * the user id in the session and re-reads the row every request, so refusing to resolve
     * a deactivated one is all it takes — and `isEmpty()` already *clears* the storage when
     * a read comes back null, which it did for deleted accounts, the same problem one step
     * further along.
     *
     * Asserted through a guarded page rather than the navbar, so the claim is about
     * authorization and not about which links are rendered.
     *
     * **The box is unticked on the real form, not with an UPDATE**, and that is the
     * whole difference between this test passing and this test meaning something.
     * `SessionUser::read()` re-reads the row through `UserTable::findById()`, which
     * serves out of the `all-linked-users` cache when it is warm — so a `state`
     * change written straight to the database is invisible until the cache expires,
     * and an assertion built on one is really asserting that the cache happens to be
     * cold. It was, until `max_items_to_cache` was retired on 2026-08-22 and that
     * item started being written on every cold pass instead of losing its slot to a
     * larger key. Going through the form exercises the invalidation too, which is the
     * half an administrator's revocation actually depends on.
     *
     * The out-of-band case is still real, and is a fact about operations rather than
     * a bug: an UPDATE run by hand, or by a migration, leaves an open session valid
     * until something writes to a user through the application. `database/db8.6.sql`
     * was exactly that kind of change.
     */
    public function testDeactivationEndsASessionThatIsAlreadyOpen(): void
    {
        //signIn() invents its own address from emailPrefix(), which is also what
        //tearDown purges on, so this test needs no address of its own.
        $jar = $this->newCookieJar();

        $email = $this->signIn($jar, ['administrator'], '/en/user/login');
        $this->assertSame(200, $this->get('/en/users', false, $jar)['status'], 'the session should be admin');

        $account = $this->pdo()->prepare(
            'SELECT user_id, username, display_name FROM user WHERE email = :email'
        );
        $account->execute(['email' => $email]);
        $row = $account->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row, 'signIn() should have created an account');
        $userId = (int) $row['user_id'];

        $roles = $this->pdo()->prepare('SELECT role_id FROM user_role_linker WHERE user_id = :id');
        $roles->execute(['id' => $userId]);

        $path = sprintf('/en/users/%d/edit', $userId);
        $form = $this->get($path, false, $jar);
        $this->assertSame(200, $form['status'], 'an administrator should reach their own edit form');

        $saved = $this->request('POST', $path, [], false, $jar, [
            'userId'            => (string) $userId,
            'username'          => (string) $row['username'],
            'email'             => $email,
            'displayName'       => (string) $row['display_name'],
            //every role the account already has, posted back: the form replaces the
            //set rather than merging into it
            'rolesList'         => array_map('strval', $roles->fetchAll(PDO::FETCH_COLUMN)),
            'personId'          => '',
            'isMultiPersonUser' => '0',
            'emailVerified'     => '1',
            //the one field under test — an unticked Active box
            'active'            => '0',
            'security'          => $this->extractCsrfToken($form['body']),
            'submit'            => 'Submit',
        ]);
        $this->assertSame(302, $saved['status'], 'the save should succeed for the request that made it');
        $this->assertSame(
            '0',
            (string) $this->pdo()
                ->query('SELECT state FROM user WHERE user_id = ' . $userId)
                ->fetchColumn(),
            'precondition: the form wrote state = 0'
        );

        $refused = $this->get('/en/users', false, $jar);
        $this->assertSame(302, $refused['status'], 'the open session must stop being an identity');
        $this->assertStringContainsString('/user/login', (string) $refused['redirect']);

        $home = $this->get('/en/', false, $jar);
        $this->assertStringContainsString('>Sign in</a>', $home['body'], 'and the session is anonymous again');
    }

    /**
     * A brand-new account is created **active** and **unverified**.
     *
     * This is what keeps open registration working now that `state = 0` is a hard
     * refusal: an account created inactive would have its very first magic link declined
     * by the check above, and registration — which is the same request as signing in —
     * would stop working entirely for everyone.
     *
     * It is also what makes `state = 0` mean something. After this, the only way an
     * account reaches it is an administrator unticking the box.
     */
    public function testANewAccountIsCreatedActiveAndUnverified(): void
    {
        $email = $this->uniqueEmail();

        $this->requestSignInLink($this->newCookieJar(), $email);
        $this->awaitMessageFor($email);

        $row = $this->pdo()->prepare('SELECT state, email_verified FROM user WHERE email = :email');
        $row->execute(['email' => $email]);
        $account = $row->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($account, 'an unknown address should have been registered');
        $this->assertSame('1', (string) $account['state'], 'a new account must be able to sign in');
        $this->assertSame('0', (string) $account['email_verified'], 'nobody has proved they read mail there yet');
    }

    /** The PHPSESSID value curl has written into a cookie jar, or '' when there is none. */
    private function sessionIdIn(string $jar): string
    {
        $contents = (string) file_get_contents($jar);

        return preg_match('/\bPHPSESSID\s+(\S+)/', $contents, $m) === 1 ? $m[1] : '';
    }

    /**
     * Undo everything the flow created — purgeMail() and purgeAccounts() come from
     * MagicLinkSignIn and key on emailPrefix(). Runs whatever the assertions did, so a
     * failing test does not leave accounts or mail behind for the next run.
     */
    protected function tearDown(): void
    {
        try {
            $this->purgeMail();
            $this->purgeAccounts();
        } finally {
            parent::tearDown();
        }
    }
}

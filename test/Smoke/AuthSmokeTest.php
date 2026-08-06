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
     */
    public function testSignInMarksEmailVerifiedEvenForActiveAccounts(): void
    {
        $email = $this->uniqueEmail();
        $jar = $this->newCookieJar();

        // create the account and obtain a live token the normal way
        $this->requestSignInLink($jar, $email);
        $verifyPath = $this->toLocalPath($this->extractVerifyUrl($this->awaitMessageFor($email)['Text']));

        // force the gap scenario: active, but email never verified
        $update = $this->pdo()->prepare('UPDATE user SET state = 1, email_verified = 0 WHERE email = :email');
        $update->execute(['email' => $email]);
        $this->assertSame(1, $update->rowCount(), 'the account should exist by now');

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

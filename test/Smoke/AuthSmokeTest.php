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
    /** Local-part prefix of every address this test invents; also the cleanup key. */
    private const EMAIL_PREFIX = 'smoke-test-';

    /** Domain of every address this test invents. */
    private const EMAIL_DOMAIN = '@example.com';

    /** How long we give the app to hand the message to Mailpit. */
    private const MAIL_POLL_ATTEMPTS = 10;
    private const MAIL_POLL_MICROSECONDS = 500000;

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

    // ---------------------------------------------------------------- helpers

    /** Unique enough that parallel or repeated runs never collide. */
    private function uniqueEmail(): string
    {
        return sprintf('%s%d-%d%s', self::EMAIL_PREFIX, time(), random_int(1000, 9999), self::EMAIL_DOMAIN);
    }

    /**
     * GET the form for its CSRF token, then POST the address. The token is
     * single-use, so every request needs its own GET first.
     *
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    private function requestSignInLink(string $jar, string $email, string $path = '/en/user/login'): array
    {
        $form = $this->get($path, false, $jar);
        $this->assertSame(200, $form['status'], "GET $path should render the form");

        return $this->request('POST', $path, [], false, $jar, [
            'email' => $email,
            'redirect' => '',
            'security' => $this->extractCsrfToken($form['body']),
            'submit' => 'Send me a sign-in link',
        ]);
    }

    private function extractCsrfToken(string $body): string
    {
        $found = preg_match('/name="security"[^>]*value="([^"]+)"/', $body, $matches);
        $this->assertSame(1, $found, 'the sign-in form should carry a CSRF token');

        return $matches[1];
    }

    private function extractVerifyUrl(string $mailBody): string
    {
        $found = preg_match('#https?://\S+/user/verify\?token=[0-9a-f]+#', $mailBody, $matches);
        $this->assertSame(1, $found, 'the mail should contain an absolute sign-in link');

        return $matches[0];
    }

    /**
     * The mail links to the canonical public host; keep the path and query and
     * point them back at whatever host the suite is testing.
     */
    private function toLocalPath(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        return null === $query ? $path : $path . '?' . $query;
    }

    /**
     * Poll Mailpit until the message shows up; sending is synchronous but the
     * SMTP hop is not, so a couple of retries beat a fixed sleep.
     *
     * @return array<string, mixed> the full message, body included
     */
    private function awaitMessageFor(string $email): array
    {
        for ($attempt = 0; $attempt < self::MAIL_POLL_ATTEMPTS; $attempt++) {
            $messages = $this->searchMailFor($email);
            if ([] !== $messages) {
                return $this->mailpit('GET', '/api/v1/message/' . rawurlencode((string) $messages[0]['ID']));
            }
            usleep(self::MAIL_POLL_MICROSECONDS);
        }
        $this->fail("No sign-in mail arrived for $email");
    }

    /** @return array<int, array<string, mixed>> the search hits, newest first */
    private function searchMailFor(string $email): array
    {
        $result = $this->mailpit('GET', '/api/v1/search?query=' . rawurlencode('to:' . $email));

        return isset($result['messages']) && is_array($result['messages']) ? $result['messages'] : [];
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed> decoded JSON, or [] for an empty response
     */
    private function mailpit(string $method, string $path, ?array $payload = null): array
    {
        $ch = curl_init($this->mailpitBaseUrl() . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        if (null !== $payload) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($payload));
        }
        $body = curl_exec($ch);
        $error = curl_error($ch);

        if (false === $body) {
            $this->fail("Mailpit request to $path failed: $error — is the mailpit container up?");
        }
        if ('' === trim((string) $body)) {
            return [];
        }
        $decoded = json_decode((string) $body, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function mailpitBaseUrl(): string
    {
        return rtrim(getenv('SMOKE_MAILPIT_URL') ?: 'http://mailpit:8025', '/');
    }

    /**
     * Undo everything the flow created. Runs whatever the assertions did, so a
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

    private function purgeMail(): void
    {
        $hits = $this->mailpit('GET', '/api/v1/search?query=' . rawurlencode('to:' . self::EMAIL_PREFIX));
        if (! isset($hits['messages']) || ! is_array($hits['messages']) || [] === $hits['messages']) {
            return;
        }
        $ids = [];
        foreach ($hits['messages'] as $message) {
            if (isset($message['ID'])) {
                $ids[] = (string) $message['ID'];
            }
        }
        if ([] !== $ids) {
            $this->mailpit('DELETE', '/api/v1/messages', ['IDs' => $ids]);
        }
    }

    /**
     * Open registration means every address we posted became an account.
     * Drop the role links first, then the accounts themselves — user_role_linker
     * cascades on delete, but being explicit keeps this honest if that changes.
     */
    private function pdo(): PDO
    {
        return new PDO(
            sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                getenv('SMOKE_DB_HOST') ?: 'db',
                getenv('SMOKE_DB_NAME') ?: 'ourlink_db1'
            ),
            getenv('SMOKE_DB_USER') ?: 'schoenstatt',
            getenv('SMOKE_DB_PASSWORD') ?: 'schoenstatt',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    private function purgeAccounts(): void
    {
        $pattern = self::EMAIL_PREFIX . '%' . self::EMAIL_DOMAIN;
        $pdo = $this->pdo();

        $linker = $pdo->prepare(
            'DELETE FROM user_role_linker'
            . ' WHERE user_id IN (SELECT user_id FROM user WHERE email LIKE :pattern)'
        );
        $linker->execute(['pattern' => $pattern]);

        $users = $pdo->prepare('DELETE FROM user WHERE email LIKE :pattern');
        $users->execute(['pattern' => $pattern]);
    }
}

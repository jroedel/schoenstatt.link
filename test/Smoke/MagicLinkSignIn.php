<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PDO;
use RuntimeException;

/**
 * A real signed-in session, over HTTP, for a smoke test that needs one.
 *
 * Extracted from AuthSmokeTest (which characterizes the flow itself) and from
 * tools/form-regression.php (which invented the role-elevation half) when
 * AdminAuthorizationSmokeTest needed the same thing for a third time. Three copies
 * of a magic-link redemption is two too many, and the failure mode of a stale copy
 * is a test that quietly stops signing in and asserts against an anonymous page.
 *
 * There is no other way to get a session here. The identity lives in the laminas
 * session; nothing sets it but the real flow; and a CLI process cannot even build
 * `Laminas\Session\Config\ConfigInterface` once PHPUnit has written its progress
 * output ("'session.cache_expire' is not a valid sessions-related ini setting"). So
 * an authenticated assertion has to be an HTTP assertion.
 *
 * Roles are granted by `INSERT` rather than through the UI, before the link is
 * redeemed. The ordering is convenience, **not** a requirement, and saying so
 * corrects a comment in tools/form-regression.php which claims the role has to exist
 * first because "BjyAuthorize reads the roles when the session identity is
 * established". It does not:
 * `JUser\Provider\Identity\ZfcUserZendDbPlusSelfAsRole::getIdentityRoles()` runs a
 * `SELECT` against `user_role_linker` on **every request**, and
 * `bjyauthorize.cache_enabled` is false, so a role granted mid-session is in force on
 * the next request. Measured — the assertion is in AdminAuthorizationSmokeTest.
 *
 * Every user of this trait must purge in tearDown — `purgeAccounts()` and
 * `purgeMail()`, both keyed on emailPrefix(). Registration here is open, so every
 * address posted becomes a real account.
 */
trait MagicLinkSignIn
{
    /** How long the app gets to hand a message to Mailpit. Sending is synchronous, the SMTP hop is not. */
    private const MAIL_POLL_ATTEMPTS = 10;
    private const MAIL_POLL_MICROSECONDS = 500000;

    /**
     * Local-part prefix of every address the using class invents, and the key both
     * purges match on. Must be unique per test class, or one class's tearDown
     * deletes another's fixtures mid-run.
     */
    abstract protected function emailPrefix(): string;

    /**
     * NOTE @example.com, not the RFC 2606 @example.test: the form's email validator
     * checks the TLD against IANA's list, and .test is not on it, so an
     * @example.test address is rejected before anything is sent.
     */
    protected function emailDomain(): string
    {
        return '@example.com';
    }

    /**
     * Sign in as a brand-new account and return its address.
     *
     * @param list<string> $roles `user_role.role_id` values to grant on top of
     *        whatever registration assigns by itself (which is lib_user, pub_user,
     *        sch_user and bib_user — measured, and the reason "signed in" and
     *        "privileged" are different questions in these tests).
     */
    protected function signIn(string $jar, array $roles = []): string
    {
        $email = $this->uniqueEmail();

        $request = $this->requestSignInLink($jar, $email);
        $this->assertSame(200, $request['status'], 'requesting a sign-in link');

        $verifyPath = $this->toLocalPath($this->extractVerifyUrl($this->awaitMessageFor($email)['Text']));

        $this->activate($email);
        if ([] !== $roles) {
            $this->grantRoles($email, $roles);
        }

        $verify = $this->get($verifyPath, false, $jar);
        $this->assertSame(302, $verify['status'], 'redeeming the sign-in link should authenticate and redirect');

        return $email;
    }

    /** Unique enough that parallel or repeated runs never collide. */
    protected function uniqueEmail(): string
    {
        return sprintf('%s%d-%d%s', $this->emailPrefix(), time(), random_int(1000, 9999), $this->emailDomain());
    }

    /**
     * GET the form for its CSRF token, then POST the address. The token is
     * single-use, so every request needs its own GET first.
     *
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    protected function requestSignInLink(string $jar, string $email, string $path = '/en/user/login'): array
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

    protected function extractCsrfToken(string $body): string
    {
        $found = preg_match('/name="security"[^>]*value="([^"]+)"/', $body, $matches);
        $this->assertSame(1, $found, 'the sign-in form should carry a CSRF token');

        return $matches[1];
    }

    protected function extractVerifyUrl(string $mailBody): string
    {
        $found = preg_match('#https?://\S+/user/verify\?token=[0-9a-f]+#', $mailBody, $matches);
        $this->assertSame(1, $found, 'the mail should contain an absolute sign-in link');

        return $matches[0];
    }

    /**
     * The mail links to the canonical public host; keep the path and query and
     * point them back at whatever host the suite is testing.
     */
    protected function toLocalPath(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        return null === $query ? $path : $path . '?' . $query;
    }

    /**
     * Poll Mailpit until the message shows up; a couple of retries beat a fixed
     * sleep.
     *
     * @return array<string, mixed> the full message, body included
     */
    protected function awaitMessageFor(string $email): array
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
    protected function searchMailFor(string $email): array
    {
        $result = $this->mailpit('GET', '/api/v1/search?query=' . rawurlencode('to:' . $email));

        return isset($result['messages']) && is_array($result['messages']) ? $result['messages'] : [];
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed> decoded JSON, or [] for an empty response
     */
    protected function mailpit(string $method, string $path, ?array $payload = null): array
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

    protected function mailpitBaseUrl(): string
    {
        return rtrim(getenv('SMOKE_MAILPIT_URL') ?: 'http://mailpit:8025', '/');
    }

    /**
     * Make the account active, i.e. what a real moderator's account looks like.
     *
     * Redeeming the link works without this — JUser activates on verification — so
     * this is about the fixture being realistic rather than about the flow needing it.
     */
    protected function activate(string $email): void
    {
        $this->pdo()->prepare('UPDATE user SET state = 1 WHERE email = :email')->execute(['email' => $email]);
    }

    /**
     * @param list<string> $roles
     * @throws RuntimeException when a named role does not exist — a silent no-op here
     *         would turn "holds the role" into "does not" and pass the wrong test.
     */
    protected function grantRoles(string $email, array $roles): void
    {
        $pdo = $this->pdo();
        $insert = $pdo->prepare(
            'INSERT INTO user_role_linker (user_id, role_id, create_datetime)'
            . ' SELECT u.user_id, r.id, NOW() FROM user u JOIN user_role r'
            . ' WHERE u.email = :email AND r.role_id = :role'
            . '   AND NOT EXISTS ('
            . '     SELECT 1 FROM user_role_linker l WHERE l.user_id = u.user_id AND l.role_id = r.id)'
        );
        foreach ($roles as $role) {
            $insert->execute(['email' => $email, 'role' => $role]);
            if (0 === $insert->rowCount()) {
                throw new RuntimeException("could not grant role '$role' to $email — does the role exist?");
            }
        }
    }

    /**
     * Every role in `user_role`, which is what tools/form-regression.php does and for
     * the same reason: some pages are assembled from a dozen independent guard checks,
     * and an account that holds everything is the only one that renders all of it.
     */
    protected function grantEveryRole(string $email): void
    {
        $this->pdo()->prepare(
            'INSERT INTO user_role_linker (user_id, role_id, create_datetime)'
            . ' SELECT u.user_id, r.id, NOW() FROM user u JOIN user_role r'
            . ' WHERE u.email = :email'
            . '   AND NOT EXISTS ('
            . '     SELECT 1 FROM user_role_linker l WHERE l.user_id = u.user_id AND l.role_id = r.id)'
        )->execute(['email' => $email]);
    }

    protected function pdo(): PDO
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

    protected function purgeMail(): void
    {
        $hits = $this->mailpit('GET', '/api/v1/search?query=' . rawurlencode('to:' . $this->emailPrefix()));
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
    protected function purgeAccounts(): void
    {
        $pattern = $this->emailPrefix() . '%' . $this->emailDomain();
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

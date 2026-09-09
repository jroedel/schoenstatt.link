<?php

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\TestCase;

/**
 * Base class for HTTP characterization tests against a RUNNING app instance
 * (the Docker time capsule by default; see SMOKE_BASE_URL in phpunit.xml.dist).
 *
 * These tests record the app's observable behavior as of the production
 * baseline. They must survive the framework migration unchanged — assert on
 * status codes, redirect targets, and stable content markers; never on
 * framework internals or exact markup.
 */
abstract class SmokeTestCase extends TestCase
{
    /** Cookie the GDPR strategy looks for before it lets any auth route run. */
    public const CONSENT_COOKIE = 'EU_COOKIE_LAW_CONSENT';

    /** @var string[] cookie jar files created by newCookieJar(), removed in tearDown() */
    private $cookieJars = [];

    /**
     * @param string|null $cookieJar path from newCookieJar(); carries the session across calls
     * @return array{status: int, redirect: string, body: string, contentType: string}
     */
    protected function get(string $path, bool $followRedirects = false, ?string $cookieJar = null): array
    {
        return $this->request('GET', $path, [], $followRedirects, $cookieJar);
    }

    /**
     * @param string[] $extraHeaders e.g. ['Origin: https://example.org']
     * @param string|null $cookieJar path from newCookieJar(); cookies are read from and
     *                               written back to it, so a series of calls shares one session
     * @param array<string, string>|null $postFields form fields; sent url-encoded
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    protected function request(
        string $method,
        string $path,
        array $extraHeaders = [],
        bool $followRedirects = false,
        ?string $cookieJar = null,
        ?array $postFields = null
    ): array {
        $responseHeaders = [];
        $ch = curl_init($this->baseUrl() . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'schoenstatt-smoke-test',
            // Negotiate and auto-decode compression (the sitemap route always
            // gzips its payload).
            CURLOPT_ENCODING => '',
            // The app sniffs Accept-Language for locale detection; pin it so
            // results don't depend on the environment.
            CURLOPT_HTTPHEADER => array_merge(['Accept-Language: en'], $extraHeaders),
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$responseHeaders): int {
                if (false !== strpos($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            },
        ]);
        if (null !== $cookieJar) {
            // Same file for both options: read the jar before the request,
            // write it back afterwards. That is what keeps PHPSESSID (and with
            // it the CSRF token and the identity) alive across calls.
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        }
        if (null !== $postFields) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        }
        $body = curl_exec($ch);
        if ($body === false) {
            $this->fail(sprintf(
                'HTTP request to %s failed: %s — is the docker environment up?',
                $path,
                curl_error($ch)
            ));
        }
        $result = [
            'status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'redirect' => (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL),
            'body' => (string) $body,
            'contentType' => (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
            'headers' => $responseHeaders,
        ];
        $this->assertNotWedged($method, $path, $result);

        return $result;
    }

    /**
     * A truncated HTML 200 fails the request that made it, whatever the caller went on
     * to assert.
     *
     * This is the **fatal-200 wedge**, and it is checked here rather than left to each
     * test because its whole character is that nothing looks wrong. A throw after the
     * response has been assembled — an unresolved view helper inside a laminas layout, a
     * Twig error inside `render()` — leaves a 200 with `Content-Type: text/html` and a
     * body that stops early, because `display_errors` is off in both environments. So
     * every status assertion in this suite passes and only an assertion about the *body*
     * notices.
     *
     * Both halves have been measured on this application:
     *
     * - **~800 bytes**, laminas side: the wedge docs/laminas-exit.md records, where the
     *   layout begins to render and dies partway.
     * - **0 bytes**, Symfony side: measured 2026-08-13, a Twig comment inside a hash
     *   literal in `movement.html.twig`. `testAModeratorReachesTheModeratorPages`
     *   asserted `200` against it and passed; only the test asserting page content
     *   caught it.
     *
     * The probe is `</html>`, not a length: a length threshold has to guess, and every
     * legitimate HTML page on this site closes its document while neither wedge reaches
     * the closing tag. It applies only to a 200 whose content type is HTML — a 302
     * carries laminas' entire sign-in page in its body and is not a document anyone
     * reads, JSON and the sitemap are not HTML, and a HEAD request has no body at all.
     *
     * A test that genuinely expects a fragment overrides `expectsWholeHtmlDocuments()`.
     * Nothing does today; the hook exists so that adding such a test is a deliberate
     * declaration rather than a reason to delete this check.
     *
     * **Endpoints already wedged when this check was written** are listed in
     * `known-wedged-responses.php` and skipped, on the same "no new gaps" contract
     * `test/Fuzz/known-form-gaps.php` uses: the check has to be un-skippable to be worth
     * anything, and an inventory of one broken endpoint is better than a deleted check.
     * Its first run found exactly one, present on `master` beforehand.
     *
     * @param array{status: int, body: string, contentType: string} $response
     */
    private function assertNotWedged(string $method, string $path, array $response): void
    {
        if (! $this->expectsWholeHtmlDocuments() || 'HEAD' === $method) {
            return;
        }
        if (200 !== $response['status'] || false === strpos($response['contentType'], 'text/html')) {
            return;
        }
        if (str_contains($response['body'], '</html>')) {
            return;
        }
        if (self::isKnownWedged($path)) {
            return;
        }

        $this->fail(sprintf(
            "%s %s answered HTTP 200 text/html with %d bytes and no </html> — the response was "
            . "truncated mid-render.\n"
            . "This is the fatal-200 wedge: a throw after the response was assembled, with "
            . "display_errors off.\n"
            . "Look in data/exceptions and data/logs/ for what threw; a Twig syntax error and an "
            . "unresolved laminas view helper both land here.\n"
            . "Body was: %s",
            $method,
            $path,
            strlen($response['body']),
            '' === $response['body'] ? '(empty)' : substr($response['body'], 0, 400)
        ));
    }

    /**
     * Whether every HTML 200 this test makes should be a complete document. True for
     * every test there is; override to false in one that fetches an HTML *fragment*.
     */
    protected function expectsWholeHtmlDocuments(): bool
    {
        return true;
    }

    /**
     * Is this path one of the already-broken ones?
     *
     * Matched **ignoring any locale prefix**, because the same endpoint is reached as
     * `/api/v1/…` and as `/en/api/v1/…` — the suite fetches both, and following a
     * redirect turns the first into the second.
     */
    private static function isKnownWedged(string $path): bool
    {
        /** @var list<string>|null $known */
        static $known = null;
        $known ??= require __DIR__ . '/known-wedged-responses.php';

        $withoutQuery = strtok($path, '?');
        $bare         = preg_replace('#^/(en|es|de|pt|it)(?=/)#', '', (string) $withoutQuery);

        return in_array($withoutQuery, $known, true) || in_array($bare, $known, true);
    }

    protected function baseUrl(): string
    {
        return rtrim(getenv('SMOKE_BASE_URL') ?: 'http://localhost', '/');
    }

    /**
     * Start a fresh browser-like session: an empty cookie jar, optionally
     * pre-seeded with the GDPR consent cookie.
     *
     * Consent has to be seeded rather than clicked: without it the app strips
     * every Set-Cookie header on the way out, so no session can ever start and
     * the auth routes are rerouted to the "you need cookies" explainer.
     *
     * @return string path to the jar file, to hand to request()/get()
     */
    protected function newCookieJar(bool $withConsent = true): string
    {
        $jar = tempnam(sys_get_temp_dir(), 'smoke-cookies-');
        if (false === $jar) {
            $this->fail('Could not create a cookie jar file');
        }
        $this->cookieJars[] = $jar;

        $lines = "# Netscape HTTP Cookie File\n";
        if ($withConsent) {
            // domain \t include-subdomains \t path \t secure \t expires \t name \t value
            $lines .= implode("\t", [
                (string) parse_url($this->baseUrl(), PHP_URL_HOST),
                'FALSE',
                '/',
                'FALSE',
                '2147483647',
                self::CONSENT_COOKIE,
                'true',
            ]) . "\n";
        }
        file_put_contents($jar, $lines);

        return $jar;
    }

    protected function tearDown(): void
    {
        foreach ($this->cookieJars as $jar) {
            if (is_file($jar)) {
                unlink($jar);
            }
        }
        $this->cookieJars = [];
        parent::tearDown();
    }

    /** Assert a page renders anonymously: 200, HTML, no PHP fatal leaked. */
    protected function assertRendersOk(string $path): array
    {
        $response = $this->get($path, true);
        $this->assertSame(200, $response['status'], "GET $path should render");
        $this->assertStringContainsString('text/html', $response['contentType'], "GET $path content type");
        $this->assertStringNotContainsString('Fatal error', $response['body'], "GET $path leaked a PHP fatal");
        return $response;
    }

    /** Assert an anonymous request is bounced to the login page. */
    protected function assertRequiresLogin(string $path): void
    {
        $response = $this->get($path);
        $this->assertSame(302, $response['status'], "GET $path should redirect anonymous users");
        $this->assertStringContainsString('/user/login', $response['redirect'], "GET $path should redirect to login");
    }
}

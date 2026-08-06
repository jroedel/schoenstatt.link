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
        return $result;
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

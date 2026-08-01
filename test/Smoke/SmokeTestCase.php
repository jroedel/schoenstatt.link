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
    /** @return array{status: int, redirect: string, body: string, contentType: string} */
    protected function get(string $path, bool $followRedirects = false): array
    {
        return $this->request('GET', $path, [], $followRedirects);
    }

    /**
     * @param string[] $extraHeaders e.g. ['Origin: https://example.org']
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    protected function request(
        string $method,
        string $path,
        array $extraHeaders = [],
        bool $followRedirects = false
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
        curl_close($ch);
        return $result;
    }

    protected function baseUrl(): string
    {
        return rtrim(getenv('SMOKE_BASE_URL') ?: 'http://localhost', '/');
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

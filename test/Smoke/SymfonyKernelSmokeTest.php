<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * Characterizes the Symfony front controller (SYMFONY_KERNEL=1, set for the
 * capsule in docker/apache-vhost.conf).
 *
 * The rest of the smoke suite already covers the bridge: with the kernel in
 * front, all 77 of those paths reach laminas-mvc through App\Http\LegacyBridge,
 * so they are the regression test for the conversion. What they cannot see is
 * whether Symfony served anything *itself* — the catch-all route would pass every
 * one of them even if routing and controller resolution were broken. That is what
 * /_health is for.
 */
class SymfonyKernelSmokeTest extends SmokeTestCase
{
    /**
     * Reaching this answer at all exercises the RouteCollection, the UrlMatcher,
     * RouterListener, ContainerControllerResolver and ArgumentResolver. Under the
     * laminas front controller the same URL is a 404 HTML page, so the payload
     * says which of the two handled the request.
     */
    public function testHealthRouteIsServedByTheSymfonyKernel(): void
    {
        $response = $this->get('/_health');

        $this->assertSame(
            200,
            $response['status'],
            'GET /_health — is SYMFONY_KERNEL=1 on this instance? '
            . 'The laminas front controller answers 404 here.'
        );
        $this->assertStringContainsString('application/json', $response['contentType']);

        $payload = json_decode($response['body'], true);
        $this->assertIsArray($payload, 'body was not JSON: ' . $response['body']);
        $this->assertSame('ok', $payload['status'] ?? null);
        $this->assertSame('symfony', $payload['kernel'] ?? null);
    }

    /**
     * Symfony's Response defaults to HTTP/1.0 and writes that into the status
     * line, which makes Apache close the connection. ProtocolVersionListener
     * copies the request's version instead; without it every Symfony-native
     * route loses keep-alive while every bridged one keeps it.
     */
    public function testASymfonyServedRouteAnswersInHttp11(): void
    {
        $curl = curl_init($this->baseUrl() . '/_health');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = (string) curl_exec($curl);
        curl_close($curl);

        $this->assertStringStartsWith('HTTP/1.1', $raw, 'status line: ' . strtok($raw, "\r\n"));
    }

    /**
     * The catch-all has to match the site root, not just deeper paths — hence
     * `.*` and an empty `path` default rather than `.+`. A `.+` requirement
     * leaves "/" unroutable, which is a 404 on the busiest URL there is.
     */
    public function testTheCatchAllStillMatchesTheSiteRoot(): void
    {
        $response = $this->get('/');

        $this->assertSame(302, $response['status'], 'GET / should still redirect to the language root');
        $this->assertStringContainsString('/en/', $response['redirect']);
    }

    /**
     * The conversion must not invent a Cache-Control. ResponseHeaderBag adds
     * "no-cache, private" to any response without one, and since Symfony's
     * sendHeaders() appends rather than replaces, it would arrive as a second,
     * weaker directive next to the one PHP's session cache limiter already sent.
     */
    public function testABridgedPageDoesNotGainAnInventedCacheControl(): void
    {
        $response = $this->get('/en/', true);

        $this->assertSame(200, $response['status']);
        $cacheControl = $response['headers']['cache-control'] ?? '';
        $this->assertStringNotContainsString(
            'private',
            $cacheControl,
            'Cache-Control "' . $cacheControl . '" looks like ResponseHeaderBag\'s default, not the application\'s'
        );
    }

    /**
     * The bridge detaches SendResponseListener so laminas never echoes; if that
     * ever stops working the body arrives twice, which no status-code assertion
     * anywhere else would catch.
     */
    public function testABridgedPageIsNotSentTwice(): void
    {
        $response = $this->get('/en/', true);

        $this->assertSame(200, $response['status']);
        $this->assertSame(
            1,
            substr_count($response['body'], '</html>'),
            'the body appears more than once — SendResponseListener is still attached'
        );
    }
}

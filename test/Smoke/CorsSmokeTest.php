<?php

namespace SchoenstattTest\Smoke;

/**
 * Characterizes the CORS behavior of the public read-only APIs, historically
 * provided by zfr/zfr-cors and since 2026-08 by Application\Listener\CorsListener.
 */
class CorsSmokeTest extends SmokeTestCase
{
    public function testApiPreflightIsAnswered(): void
    {
        $response = $this->request('OPTIONS', '/en/api/v1/literature', ['Origin: https://example.org']);
        $this->assertSame(204, $response['status'], 'preflight should short-circuit with 204');
        $this->assertSame('*', $response['headers']['access-control-allow-origin'] ?? null);
        $this->assertArrayHasKey('access-control-allow-methods', $response['headers']);
        $this->assertStringContainsString(
            'Authorization',
            $response['headers']['access-control-allow-headers'] ?? '',
            'JWT clients must be allowed to send the Authorization header'
        );
    }

    public function testCorsEnabledApiResponseAllowsAnyOrigin(): void
    {
        $response = $this->request('GET', '/en/api/v1/literature', ['Origin: https://example.org']);
        $this->assertSame(200, $response['status']);
        $this->assertSame('*', $response['headers']['access-control-allow-origin'] ?? null);
    }

    public function testNonCorsRouteGetsNoAllowOriginHeader(): void
    {
        $response = $this->request('GET', '/en/', ['Origin: https://example.org']);
        $this->assertSame(200, $response['status']);
        $this->assertArrayNotHasKey('access-control-allow-origin', $response['headers']);
    }
}

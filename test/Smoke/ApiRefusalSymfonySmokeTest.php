<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * The `/api/...` JSON refusal, Symfony-served since it was ported off LegacyBridge
 * (Phase B prep of the laminas-mvc removal, 2026-09-08).
 *
 * `App\Controller\Api\ApiRouteNotFoundController` reproduces
 * `RestApi\Controller\RouteNotFoundController`: 410 Gone plus a successor-version Link for
 * the retired v1/v2, 404 for every other unmatched `/api/` path. It was the last thing
 * besides the admin-only `kernel-switch` that the bridge answered, which is why moving it
 * matters — `LegacyBridge` cannot be deleted while anything real still routes through it.
 *
 * `ApplicationSmokeTest::testUnknownApiUrlIsACleanNotFound` already covers the 404 side
 * following redirects; this pins the 410 side, the narrow retired-version match, and the
 * one behaviour that changed — the refusal now answers directly, with no locale hop.
 */
class ApiRefusalSymfonySmokeTest extends SmokeTestCase
{
    public function testARetiredVersionIsGoneWithASuccessorLink(): void
    {
        //no follow: the point is that this answers 410 directly, where the laminas route
        //302'd to the locale-prefixed form first
        $response = $this->get('/api/v1/associations');

        $this->assertSame(410, $response['status'], 'a retired version is Gone, not Not Found or a redirect');
        $this->assertStringContainsString('json', $response['contentType']);
        $this->assertArrayHasKey('link', array_change_key_case($response['headers']), 'the 410 must advertise its successor');
        $this->assertStringContainsString('/api/v3/schema', $this->headerValue($response, 'link'));
        $this->assertStringContainsString('rel="successor-version"', $this->headerValue($response, 'link'));
        $this->assertStringContainsString('has been retired', $response['body']);
    }

    public function testTheRetiredMatchIsNarrow(): void
    {
        //v3 is live: a typo in it is a 404, never a 410 — telling a caller its endpoint is
        //permanently gone when it misspelled one is a lie it acts on
        $this->assertSame(404, $this->get('/api/v3/phrasez')['status']);
        //v10 was never a version; it is absent, not withdrawn
        $this->assertSame(404, $this->get('/api/v10/associations')['status']);
        //but the OpenAPI document form and the prefixed form of a retired version are gone
        $this->assertSame(410, $this->get('/api/v1.yaml')['status']);
        $this->assertSame(410, $this->get('/de/api/v2/anything')['status']);
    }

    public function testAnUnknownPathIsAJson404ServedDirectly(): void
    {
        $response = $this->get('/api/there-is-no-such-endpoint');

        $this->assertSame(404, $response['status']);
        $this->assertStringContainsString('json', $response['contentType']);
        //the exact envelope the laminas route produced, byte for byte
        $this->assertStringContainsString('{"status":"NOK","result":{"error":"Request Not Found."}}', $response['body']);
        //answered here, not bridged: no 302 locale hop precedes it
        $this->assertSame('', (string) ($response['redirect'] ?? ''), 'the refusal answers directly, it is not bridged');
    }

    public function testBareApiIsAJson404(): void
    {
        $response = $this->get('/api');

        $this->assertSame(404, $response['status']);
        $this->assertStringContainsString('json', $response['contentType']);
    }

    public function testARealV3EndpointStillAnswers(): void
    {
        //the refusal sits below the real routes and must not shadow them
        $this->assertSame(200, $this->get('/api/v3/schema')['status']);
    }

    /** @param array<string, mixed> $response */
    private function headerValue(array $response, string $name): string
    {
        $headers = array_change_key_case($response['headers']);
        $value   = $headers[$name] ?? '';

        return is_array($value) ? implode(', ', $value) : (string) $value;
    }
}

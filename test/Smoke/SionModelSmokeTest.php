<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * SionModel module: the /en/sm maintenance endpoints.
 *
 * The mutating endpoints are gated behind authentication, so their tests
 * only ever exercise the access-control path — no maintenance work is
 * triggered by an anonymous request. cache-status is read-only, so it is
 * also exercised with the capsule's committed dev-only api key.
 */
class SionModelSmokeTest extends SmokeTestCase
{
    /** The capsule-only key from docker/local.docker.php — not a secret. */
    private const DEV_API_KEY = 'local-dev-api-key';

    /**
     * The persistent-cache clear doubles as a deploy hook. It is idempotent,
     * but anonymously it never reaches the controller at all.
     */
    public function testClearPersistentCacheRequiresLogin(): void
    {
        $this->assertRequiresLogin('/en/sm/clear-persistent-cache');
    }

    public function testCacheStatusRequiresApiKey(): void
    {
        $this->assertRequiresLogin('/en/sm/cache-status');
    }

    /**
     * Deploy hooks poll this to see APCu occupancy from inside the web SAPI
     * (a full segment silently degrades the persistent cache to misses).
     */
    public function testCacheStatusReportsApcuOccupancy(): void
    {
        $response = $this->get('/en/sm/cache-status?key=' . self::DEV_API_KEY);

        $this->assertSame(200, $response['status'], 'cache-status should render with the dev api key');
        $this->assertStringContainsString('json', $response['contentType']);

        $status = json_decode($response['body'], true);
        $this->assertIsArray($status, 'cache-status should return JSON');
        $this->assertTrue($status['apcuEnabled'], 'the capsule runs APCu');
        $this->assertIsNumeric($status['percentUsed']);
        $this->assertGreaterThanOrEqual(0, $status['percentUsed']);
        $this->assertLessThanOrEqual(100, $status['percentUsed']);
        $this->assertGreaterThan(0, $status['totalBytes']);
        $this->assertSame($status['totalBytes'], $status['usedBytes'] + $status['availBytes']);
    }

    /**
     * The supported channel for the maintenance key. A query string is written
     * to the web server's access log and kept in shell history, so the deploy
     * sends the key as a header instead — this is the assertion that the server
     * side of that actually works, since the failure mode (falling back to a
     * sign-in redirect) looks identical to a wrong key.
     */
    public function testCacheStatusAcceptsTheApiKeyAsAHeader(): void
    {
        $response = $this->request('GET', '/en/sm/cache-status', ['X-Api-Key: ' . self::DEV_API_KEY]);

        $this->assertSame(200, $response['status'], 'the X-Api-Key header should authenticate the request');
        $this->assertStringContainsString('json', $response['contentType']);

        $status = json_decode($response['body'], true);
        $this->assertIsArray($status, 'cache-status should return JSON');
        $this->assertTrue($status['apcuEnabled'], 'the capsule runs APCu');
    }

    /**
     * A header carrying the wrong value must be no better than no key at all.
     */
    public function testCacheStatusRejectsAWrongHeaderKey(): void
    {
        $response = $this->request('GET', '/en/sm/cache-status', ['X-Api-Key: not-the-key']);

        $this->assertSame(302, $response['status'], 'an unknown key should not reach the controller');
        $this->assertStringContainsString('/user/login', $response['redirect']);
    }

    public function testDataProblemsRequiresLogin(): void
    {
        $this->assertRequiresLogin('/en/sm/data-problems');
    }

    public function testPhpInfoRequiresLogin(): void
    {
        $this->assertRequiresLogin('/en/sm/phpinfo');
    }
}

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

    public function testDataProblemsRequiresLogin(): void
    {
        $this->assertRequiresLogin('/en/sm/data-problems');
    }

    public function testPhpInfoRequiresLogin(): void
    {
        $this->assertRequiresLogin('/en/sm/phpinfo');
    }
}

<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * SionModel module: the /en/sm maintenance endpoints.
 *
 * The mutating endpoints are gated behind a maintenance key, so their tests
 * only ever exercise the access-control path — no maintenance work is
 * triggered by a keyless request. cache-status is read-only, so it is
 * also exercised with the capsule's committed dev-only api key.
 *
 * cache-status and clear-persistent-cache are the two routes ported to the
 * Symfony kernel (docs/strangler.md), so in the capsule these tests run against
 * App\Controller\*, while production still answers the same URLs from
 * SionModelController. That is deliberate and both must keep working; what the
 * two do *not* share is the refusal, see assertRefusesWithoutKey() below.
 */
class SionModelSmokeTest extends SmokeTestCase
{
    /** The capsule-only key from docker/local.docker.php — not a secret. */
    private const DEV_API_KEY = 'local-dev-api-key';

    /**
     * A request with no key is refused with a JSON 401 — not the 302 to
     * /en/user/login these two used to answer, which is what changed when they
     * were ported to the Symfony kernel.
     *
     * The redirect was a latent bug rather than a feature. It came from
     * JUser\View\RedirectionStrategy handling BjyAuthorize's UnAuthorizedException,
     * and these are machine endpoints: a deploy hook that follows the redirect is
     * handed an HTML sign-in page carrying a 200, and reads it as success. The
     * laminas actions still behave the old way for as long as production serves
     * them (SYMFONY_KERNEL unset), which is why this asserts the *new* contract
     * rather than either one — the suite runs against the capsule, and the capsule
     * runs the Symfony kernel.
     *
     * assertRequiresLogin() is untouched and still used by the other tests below:
     * every /en/sm route except these two is still laminas-served and still
     * bounces anonymous callers to the sign-in page.
     */
    private function assertRefusesWithoutKey(string $path): void
    {
        $response = $this->get($path);

        $this->assertSame(401, $response['status'], "GET $path should refuse a keyless caller");
        $this->assertStringContainsString('json', $response['contentType'], "GET $path should refuse in JSON");
        $this->assertSame('', $response['redirect'], "GET $path must not redirect a machine caller to a login page");

        $body = json_decode($response['body'], true);
        $this->assertIsArray($body, "GET $path should refuse with a JSON object");
        $this->assertArrayHasKey('message', $body);
        //names the supported channel; says nothing about whether a key was sent
        $this->assertStringContainsString('X-Api-Key', $body['message']);
    }

    /**
     * The persistent-cache clear doubles as a deploy hook. It is idempotent, but
     * without a key it never reaches the flush at all — which is what keeps this
     * suite from clearing the capsule's cache out from under the other tests.
     */
    public function testClearPersistentCacheRequiresApiKey(): void
    {
        $this->assertRefusesWithoutKey('/en/sm/clear-persistent-cache');
    }

    public function testCacheStatusRequiresApiKey(): void
    {
        $this->assertRefusesWithoutKey('/en/sm/cache-status');
    }

    /**
     * The locale prefix is what every caller uses — tools/smoke-prod.sh, the
     * phploy hooks and `bin/console cache:flush-persistent` all ask for
     * `/en/sm/…` — and under laminas it is SlmLocale that strips it before
     * routing. The Symfony kernel has no such listener, so the prefixed and bare
     * forms are separate declared routes and both are asserted here; a path with
     * some other first segment must keep falling through to laminas rather than
     * being swallowed by the two-segment pattern.
     */
    public function testTheLocalePrefixIsOptionalAndOnlyRealLocalesAreClaimed(): void
    {
        foreach (['/en/sm/cache-status', '/sm/cache-status', '/de/sm/cache-status'] as $path) {
            $response = $this->request('GET', $path, ['X-Api-Key: ' . self::DEV_API_KEY]);
            $this->assertSame(200, $response['status'], "GET $path should answer the same payload");
            $this->assertStringContainsString('json', $response['contentType']);
        }

        //not a configured locale: still laminas', so SlmLocale redirects it
        $response = $this->request('GET', '/xx/sm/cache-status', ['X-Api-Key: ' . self::DEV_API_KEY]);
        $this->assertSame(302, $response['status'], '/xx/ is not a locale and must not match the ported route');
    }

    /**
     * Deploy hooks poll this to see APCu occupancy from inside the web SAPI
     * (a full segment silently degrades the persistent cache to misses).
     */
    public function testCacheStatusReportsApcuOccupancy(): void
    {
        //header, not ?key= — the query-string channel was dropped 2026-08-17
        $response = $this->request('GET', '/en/sm/cache-status', ['X-Api-Key: ' . self::DEV_API_KEY]);

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
     * OPcache occupancy, reported alongside APCu because neither segment can be
     * read from anywhere but the web SAPI that owns it.
     *
     * Asserted here rather than trusted because `tools/smoke-prod.sh` greps these
     * exact key names to decide whether to warn — a rename would silence the
     * production warnings without failing anything.
     */
    public function testCacheStatusReportsOpcacheAlongsideApcu(): void
    {
        $response = $this->request('GET', '/en/sm/cache-status', ['X-Api-Key: ' . self::DEV_API_KEY]);

        $this->assertSame(200, $response['status']);
        $status = json_decode($response['body'], true);
        $this->assertIsArray($status);

        //the APCu keys stay top-level and unprefixed: existing consumers read them
        $this->assertArrayHasKey('apcuEnabled', $status);
        $this->assertArrayHasKey('opcache', $status);
        $this->assertIsArray($status['opcache']);

        $opcache = $status['opcache'];
        $this->assertTrue($opcache['enabled'], 'the capsule runs OPcache, as production does');

        foreach (
            [
                'cacheFull',
                'memoryPercentUsed',
                'keysPercentUsed',
                'cachedScripts',
                'maxCachedKeys',
                'hitRatePercent',
                'oomRestarts',
                'hashRestarts',
                'internedPercentUsed',
                'internedBufferBytes',
                'internedBufferConfiguredMb',
                'startTimeUnix',
                'validateTimestamps',
            ] as $key
        ) {
            $this->assertArrayHasKey($key, $opcache, "smoke-prod.sh greps for \"$key\"");
        }

        $this->assertGreaterThan(0, $opcache['cachedScripts'], 'the request that answered this was itself compiled');
        //the real ceiling is the prime-rounded table size, which is larger than
        //the configured max_accelerated_files — never equal to it
        $this->assertGreaterThanOrEqual(
            $opcache['maxAcceleratedFilesConfigured'],
            $opcache['maxCachedKeys']
        );
        $this->assertLessThanOrEqual(100, $opcache['keysPercentUsed']);

        //The allocated buffer is the configured megabytes, and asserting the
        //relationship rather than either number is what keeps this true after the
        //setting is raised. It is also the one place the two are checked against
        //each other on a live SAPI: the unit suite pins the arithmetic, but only a
        //real opcache_get_status() can show that PHP allocates what the ini asked
        //for rather than clamping it.
        $this->assertSame(
            $opcache['internedBufferConfiguredMb'] * 1024 * 1024,
            $opcache['internedBufferBytes'],
            'OPcache allocated a different interned buffer than opcache.interned_strings_buffer asked for'
        );
        $this->assertSame(
            $opcache['internedUsedBytes'] + $opcache['internedFreeBytes'],
            $opcache['internedBufferBytes'],
            'used + free is the buffer; if that stops holding, the percentage is over the wrong total'
        );

        //The segment identity has to be stable, or tools/opcache-sample.sh counts
        //one pool as several. Two requests in one test cannot prove it survives a
        //pool restart, but they can prove it is not simply the current time.
        $again = json_decode(
            $this->request('GET', '/en/sm/cache-status', ['X-Api-Key: ' . self::DEV_API_KEY])['body'],
            true
        );
        $this->assertSame(
            $opcache['startTimeUnix'],
            $again['opcache']['startTimeUnix'],
            'the capsule runs one pool, so both requests must report the same segment'
        );
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
     * A header carrying the wrong value must be no better than no key at all —
     * indistinguishable, in fact: the refusal says which channel to use and never
     * whether a key was presented.
     */
    public function testCacheStatusRejectsAWrongHeaderKey(): void
    {
        $wrong   = $this->request('GET', '/en/sm/cache-status', ['X-Api-Key: not-the-key']);
        $keyless = $this->get('/en/sm/cache-status');

        $this->assertSame(401, $wrong['status'], 'an unknown key should not reach the controller');
        $this->assertSame($keyless['body'], $wrong['body'], 'a wrong key must look exactly like no key');
    }

    /**
     * Which front controller answered. The rest of the suite reaches laminas-mvc
     * through App\Http\LegacyBridge either way, so nothing else here would notice
     * if the ported routes quietly fell through to the catch-all — the payload is
     * identical by design (SionModel\Cache\CacheStatusPayload builds it for both).
     * The refusal is the one observable difference: laminas answers 302 to the
     * sign-in page, Symfony answers 401 JSON.
     */
    public function testTheMaintenanceEndpointsAreServedBySymfonyInTheCapsule(): void
    {
        foreach (['/en/sm/cache-status', '/en/sm/clear-persistent-cache'] as $path) {
            $response = $this->get($path);
            $this->assertSame(
                401,
                $response['status'],
                "GET $path answered $response[status]: a 302 means the Symfony route stopped matching and the "
                . 'request fell through to laminas-mvc — check config/symfony/routes.php'
            );
        }
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

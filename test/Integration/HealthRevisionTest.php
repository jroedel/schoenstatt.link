<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Controller\HealthController;
use App\Http\MaintenanceKey;
use App\Laminas\ServiceBridge;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * /_health reports the running release to an authenticated caller, and that field
 * is what tools/deploy.sh polls before it runs a post-deploy migration.
 *
 * The check exists because on 2026-08-17 "the symlink points at the new release"
 * and "the new release is executing" were different facts for 40 minutes, across
 * three independent OPcache segments, while a destructive migration ran against
 * code that had already been replaced. See
 * docs/DEPLOY.md.
 *
 * What is pinned here is the contract the deploy depends on:
 *
 *   - a caller WITHOUT a key still gets 200 and the liveness fields, because a
 *     monitor must never be broken to protect a git sha;
 *   - a WRONG key gets exactly the same answer — not a 401, and no hint that a
 *     richer one exists;
 *   - a correct key gets `revision`, trimmed;
 *   - outside a release there is no `.revision`, and that reports as null rather
 *     than as an error, keeping "not a deployment" distinguishable from "a
 *     deployment of something unexpected". Only the second aborts a deploy.
 *
 * An integration test rather than a unit test for one reason: MaintenanceKey is
 * final and reads its keys through the laminas config, so the only honest way to
 * hand it a known key is to build the real thing. It needs no database and no
 * running app — the same ServiceBridge bin/console builds, with the config caches
 * off so a CI runner with no writable data/config still passes.
 */
class HealthRevisionTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/health-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/.revision');
        @rmdir($this->dir);
    }

    private function bridge(): ServiceBridge
    {
        if (null !== self::$bridge) {
            return self::$bridge;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        return self::$bridge = new ServiceBridge($appConfig);
    }

    private function apiKey(): string
    {
        $config = $this->bridge()->get('SionModel\Config');
        self::assertIsArray($config);
        foreach ((array) ($config['api_keys'] ?? []) as $key) {
            if (is_string($key) && '' !== $key) {
                return $key;
            }
        }

        self::markTestSkipped('no sion_model.api_keys configured');
    }

    /** @return array<string, mixed> */
    private function call(?string $presentKey): array
    {
        $server  = null === $presentKey ? [] : ['HTTP_X_API_KEY' => $presentKey];
        $request = Request::create('/_health', 'GET', [], [], [], $server);

        $response = (new HealthController(new MaintenanceKey($this->bridge()), $this->dir))($request);
        self::assertSame(200, $response->getStatusCode(), '/_health must always answer 200');

        $body = json_decode((string) $response->getContent(), true);
        self::assertIsArray($body);

        return $body;
    }

    public function testAnonymousGetsLivenessAndNoRevision(): void
    {
        file_put_contents($this->dir . '/.revision', "abc123\n");

        self::assertSame(['status' => 'ok', 'kernel' => 'symfony'], $this->call(null));
    }

    public function testAWrongKeyIsIndistinguishableFromNoKey(): void
    {
        $this->apiKey(); //skips the test if none is configured
        file_put_contents($this->dir . '/.revision', "abc123\n");

        self::assertSame(
            $this->call(null),
            $this->call('definitely-not-the-key'),
            'a wrong key must not reveal that a revision field exists, nor 401 a liveness probe'
        );
    }

    public function testACorrectKeyGetsTheRevisionTrimmed(): void
    {
        file_put_contents($this->dir . '/.revision', "  abc123def456  \n");

        self::assertSame('abc123def456', $this->call($this->apiKey())['revision'] ?? null);
    }

    public function testRevisionIsNullOutsideARelease(): void
    {
        //no .revision written: a dev checkout, or the capsule
        $body = $this->call($this->apiKey());

        self::assertArrayHasKey('revision', $body, 'the field must still be present, as null');
        self::assertNull($body['revision']);
    }

    public function testAnEmptyRevisionFileReportsNullRatherThanEmptyString(): void
    {
        //a truncated write must not read as a release whose sha is the empty string
        file_put_contents($this->dir . '/.revision', "\n");

        self::assertNull($this->call($this->apiKey())['revision']);
    }
}

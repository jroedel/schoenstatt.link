<?php

namespace SchoenstattTest\Integration;

use App\Controller\CacheStatusController;
use App\Http\MaintenanceKey;
use App\Laminas\ServiceBridge;
use PHPUnit\Framework\TestCase;
use SionModel\Cache\CacheStatusPayload;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * /sm/cache-status refuses correctly, and this application's own cache configuration is
 * current.
 *
 * What is left of CacheStatusParityTest once the laminas `SionModelController` it compared
 * against was deleted (laminas-exit.md, step 0): the structural comparison of the two
 * payloads went with the action. The payload itself is `SionModel\Cache\CacheStatusPayload`,
 * pinned by test/Unit/OpcacheStatusTest against a synthetic status and read live over
 * HTTP by the smoke suite and tools/smoke-prod.sh.
 */
class CacheStatusEndpointTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private static ?array $sionModelConfig = null;
    private static ?ServiceBridge $bridge = null;

    /**
     * The laminas services, built the way bin/console builds them: no bootstrap(),
     * so no MVC listeners, no route stack and no dispatch.
     *
     * The config caches are switched off here and only here. At runtime
     * ServiceBridge wants them — that is what makes per-request module loading
     * affordable — but a test run must not write data/config/, both because CI has
     * no such directory and because a cache file owned by the wrong user next to a
     * real deployment is a worse outcome than a slow test.
     */
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

    /**
     * @return array<string, mixed>
     */
    private function sionModelConfig(): array
    {
        if (null !== self::$sionModelConfig) {
            return self::$sionModelConfig;
        }

        $config = $this->bridge()->get('SionModel\Config');
        self::assertIsArray($config, 'SionModel\Config should resolve to the merged sion_model config');

        return self::$sionModelConfig = $config;
    }

    private function apiKey(): string
    {
        $keys = $this->sionModelConfig()['api_keys'] ?? [];
        foreach ((array) $keys as $key) {
            if (is_string($key) && '' !== $key) {
                return $key;
            }
        }

        self::markTestSkipped('no sion_model.api_keys configured, so neither endpoint can be reached');
    }

    /**
     * The one behaviour that deliberately differs from the laminas path, and the
     * reason it does: there, a missing key throws UnAuthorizedException and
     * JUser\View\RedirectionStrategy answers 302 to the sign-in page — which a
     * deploy hook follows, receiving an HTML login form with a 200 on it.
     */
    public function testTheSymfonyControllerRefusesAKeylessRequestWithJson(): void
    {
        $this->apiKey(); //skip where no key is configured: everything would be refused
        $controller = new CacheStatusController(new MaintenanceKey($this->bridge()), $this->bridge());

        $response = $controller(Request::create('/en/sm/cache-status'));

        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('json', (string) $response->headers->get('Content-Type'));
        $body = json_decode((string) $response->getContent(), true);
        self::assertIsArray($body);
        self::assertArrayHasKey('message', $body);
        self::assertStringContainsString(MaintenanceKey::HEADER, $body['message']);
    }

    /**
     * The inverse of what this asserted until 2026-08-17.
     *
     * It used to require that `?key=` still worked, because the deploy
     * configuration that sent it lived in a gitignored phploy.ini no commit here
     * could change. phploy is retired, every caller sends the header, and the
     * fallback is gone from both gates — so a correct key in the query string must
     * now be refused exactly as a missing one is. Keeping the case rather than
     * deleting it is deliberate: this is the assertion that fails if anyone
     * restores the fallback for convenience.
     */
    public function testAKeyInTheQueryStringIsRefused(): void
    {
        $key        = $this->apiKey();
        $controller = new CacheStatusController(new MaintenanceKey($this->bridge()), $this->bridge());

        $response = $controller(Request::create('/en/sm/cache-status?key=' . urlencode($key)));

        self::assertSame(
            401,
            $response->getStatusCode(),
            'A valid key presented in the query string must still be refused: the access log '
            . 'records it either way, so accepting it is the leak.'
        );
    }

    /**
     * This application's own merged config names no SionModel cache key that
     * SionModel has stopped honouring.
     *
     * `max_items_to_cache` was retired on 2026-08-22 and removed from
     * `local.php.dist`, `docker/local.docker.php` and SionModel's `module.config.php`
     * in the same change. A key put back into any of those would be accepted in
     * silence and do nothing, which is the failure this catches — and it is the
     * *local* half of the check only: `config/autoload/local.php` is gitignored, so
     * the production copy can only be read from the live endpoint, which is why the
     * field exists in the payload and why tools/smoke-prod.sh warns on it.
     */
    public function testNoRetiredSionModelCacheKeyIsConfiguredHere(): void
    {
        $payload = CacheStatusPayload::build($this->sionModelConfig());

        self::assertSame(
            [],
            $payload['sionModel']['retiredConfigKeys'],
            'the merged sion_model config names a key SionModel no longer reads'
        );
        self::assertIsInt(
            $payload['sionModel']['maxCachedItemSize'],
            'max_cached_item_size is the only bound left on a cache write, and this '
            . 'application configures it in SionModel\'s module.config.php — a null here '
            . 'means that default has gone missing, not that the check is off (0 means that)'
        );
    }

}

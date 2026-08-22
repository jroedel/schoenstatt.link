<?php

namespace SchoenstattTest\Integration;

use App\Controller\CacheStatusController;
use App\Http\MaintenanceKey;
use App\Laminas\ServiceBridge;
use Laminas\Http\Request as HttpRequest;
use Laminas\View\Model\JsonModel;
use PHPUnit\Framework\TestCase;
use SionModel\Cache\ApcuStatus;
use SionModel\Cache\CacheStatusPayload;
use SionModel\Cache\OpcacheStatus;
use SionModel\Controller\SionModelController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * /sm/cache-status is answered by two different front controllers, and this pins
 * that they answer the same thing.
 *
 * The laminas action is what production serves (SYMFONY_KERNEL unset);
 * App\Controller\CacheStatusController is what the capsule serves. Both have to
 * stay, until the flag flips, and both are read by name: tools/smoke-prod.sh and
 * test/Smoke/SionModelSmokeTest.php grep individual keys out of the JSON, so a key
 * that exists on one path and not the other silences a production warning instead
 * of failing a test.
 *
 * **This compares the two controllers in one process, not two live front
 * controllers over HTTP** — and it is worth being precise about why, because the
 * weaker claim is the honest one. The capsule serves only the Symfony kernel, and
 * every path that reaches the laminas one is by definition a path Symfony did not
 * claim, so no single URL can exercise both in one run. Probing `/en_US/…` or
 * `/en//…` does not help either: SlmLocale redirects the first to `/en/…` (which
 * Symfony then claims) and the second is a 404. So the equivalence is measured
 * where it is actually decided — the controllers and the encoders — and the
 * remaining gap, the HTTP layer itself, is covered by the smoke suite.
 *
 * Values are not compared, structure is: the two calls read a live APCu segment a
 * few microseconds apart, and its counters move. Which is fine, because a drifting
 * counter is not the failure mode anyone is guarding against — a renamed or
 * reordered key is.
 *
 * One further limit of running under the CLI SAPI, stated rather than papered
 * over: `apc.enable_cli=1` here, so every APCu key really is compared, but OPcache
 * is off for CLI, so the `opcache` block collapses to `{"enabled":false}` and its
 * inner keys are not. Those are pinned by test/Unit/OpcacheStatusTest.php against
 * a synthetic status, and asserted live over HTTP by the smoke suite.
 */
class CacheStatusParityTest extends TestCase
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

    protected function tearDown(): void
    {
        ApcuStatus::$clock    = null;
        OpcacheStatus::$clock = null;
        parent::tearDown();
    }

    /**
     * The payload the laminas action returns, obtained the way the view layer
     * would serialize it.
     */
    private function laminasPayload(string $key): string
    {
        $request = new HttpRequest();
        $request->getHeaders()->addHeaderLine(MaintenanceKey::HEADER, $key);

        $controller = (new ProbeSionModelController(
            ['SionModel\Config' => $this->sionModelConfig()],
            $this->sionModelConfig()
        ))->withRequest($request);

        $model = $controller->cacheStatusAction();
        self::assertInstanceOf(JsonModel::class, $model);

        return $model->serialize();
    }

    private function symfonyPayload(string $key): string
    {
        $controller = new CacheStatusController(new MaintenanceKey($this->bridge()), $this->bridge());
        $response   = $controller(Request::create('/en/sm/cache-status', 'GET', [], [], [], [
            'HTTP_' . str_replace('-', '_', strtoupper(MaintenanceKey::HEADER)) => $key,
        ]));

        self::assertSame(200, $response->getStatusCode(), 'a configured key should reach the payload');

        return (string) $response->getContent();
    }

    /**
     * The assertion that matters: same keys, same order, same nesting, same value
     * types, all the way down.
     */
    public function testBothControllersEmitTheSameDocumentStructure(): void
    {
        $key = $this->apiKey();
        //pin both clocks so uptime cannot be the thing that differs
        ApcuStatus::$clock    = static fn (): int => 1_700_000_000;
        OpcacheStatus::$clock = static fn (): int => 1_700_000_000;

        $laminas = json_decode($this->laminasPayload($key), true);
        $symfony = json_decode($this->symfonyPayload($key), true);

        self::assertIsArray($laminas);
        self::assertIsArray($symfony);

        self::assertSame(
            $this->structure($laminas),
            $this->structure($symfony),
            'the two front controllers no longer describe the same document. Both must build the payload '
            . 'through SionModel\Cache\CacheStatusPayload; a key added to one of them alone would silence a '
            . 'tools/smoke-prod.sh warning rather than fail anything.'
        );

        //values that cannot legitimately drift between two calls in one process
        self::assertSame($laminas['phpVersion'], $symfony['phpVersion']);
        self::assertSame($laminas['apcuEnabled'], $symfony['apcuEnabled']);
        self::assertSame($laminas['opcache']['enabled'], $symfony['opcache']['enabled']);
    }

    /**
     * The other half of "byte-identical", and the half that is not obvious: the
     * two sides hand the same array to two *different* JSON encoders.
     *
     * They agree because Laminas\Json\Json::encode and Symfony's JsonResponse both
     * set exactly JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP — no
     * JSON_UNESCAPED_SLASHES on either side, no JSON_UNESCAPED_UNICODE, no pretty
     * printing. Nothing enforces that but this test, and an APCu key holding a `<`
     * or a `&` (they are cache keys, so it is possible) is where a divergence would
     * first show up.
     */
    public function testTheTwoJsonEncodersAgreeOnTheSamePayload(): void
    {
        $payload = CacheStatusPayload::build($this->sionModelConfig());
        //values only an encoder difference would touch
        $payload['encoderProbe'] = '<tag> & "quote" \'apos\' /slash/ ünïcode';

        self::assertSame(
            (new JsonModel($payload))->serialize(),
            (string) (new JsonResponse($payload))->getContent()
        );
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

    /**
     * Every key, in order, with the type of its leaf value — `largestEntries`
     * collapsed to the type of its contents, since its keys are whatever the live
     * cache happens to hold.
     *
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function structure(array $payload, string $prefix = ''): array
    {
        $out = [];
        foreach ($payload as $name => $value) {
            $path = $prefix . '/' . $name;
            if ('largestEntries' === $name && is_array($value)) {
                $types = array_unique(array_map('gettype', array_values($value)));
                sort($types);
                $out[] = $path . ':map<string,' . implode('|', $types) . '>';
                continue;
            }
            if (is_array($value)) {
                $out[] = $path . ':array';
                $out   = array_merge($out, $this->structure($value, $path));
                continue;
            }
            $out[] = $path . ':' . gettype($value);
        }

        return $out;
    }
}

/**
 * Reaches the action the way a dispatch would, minus the dispatch.
 *
 * AbstractController only ever sets its request from dispatch(), which needs a
 * RouteMatch, an MvcEvent and a plugin manager — none of which this test has any
 * use for. The X-Api-Key header path through MaintenanceKeyTrait needs the request
 * and nothing else, so handing it one directly is the whole of the setup.
 */
class ProbeSionModelController extends SionModelController
{
    public function withRequest(HttpRequest $request): self
    {
        $this->request = $request;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use Laminas\Db\Adapter\Adapter;
use Laminas\Json\Json;
use Locale;
use PHPUnit\Framework\TestCase;
use Schoenstatt\Model\SchoenstattTable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

use function is_array;
use function is_readable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The ported shrine-GeoJSON endpoint encodes the same bytes laminas' JsonModel does.
 *
 * Unlike the shrine *index*, this port copies no logic: the work is in
 * SchoenstattTable::getShrineGeoJson(), a model method both front controllers call, so
 * there is no duplicated array-building to keep in step. What *is* duplicated is the
 * serialization, and that is the only place a difference can hide:
 *
 *   laminas: JsonModel -> Laminas\View\Renderer\JsonRenderer -> Laminas\Json\Json::encode()
 *   symfony: App\Controller\ShrinesGeoJsonController -> Symfony JsonResponse
 *
 * Laminas\Json\Json::encode() passes JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|
 * JSON_HEX_AMP, which is **exactly** Symfony JsonResponse's default — so the ported
 * controller uses JsonResponse and inherits the agreement rather than restating it.
 *
 * That was not obvious and this test is why it is right: the controller first
 * hand-rolled `json_encode()` with no flags, on the belief that laminas passed none,
 * and this test failed on an apostrophe — laminas writes `\u0027`, bare json_encode
 * writes `'`. On today's data no shrine name contains `<`, `>`, `&`, `'` or `"`, so the
 * real payload agreed either way; only the hostile case below disagreed. That is the
 * shape of the risk worth testing for: an agreement that holds on today's rows and
 * breaks the day a shrine is renamed "Our Lady's".
 *
 * The comparison is against a real JsonResponse rather than against a repetition of the
 * flag list, so it stays true if Symfony ever changes that default.
 */
class ShrineGeoJsonParityTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    public static function setUpBeforeClass(): void
    {
        //as App\Http\LocaleListener does for a request: getShrines() indexes its
        //per-locale arrays by this string, and the CLI default is en_US_POSIX
        Locale::setDefault('en_US');
    }

    /**
     * The real payload, both ways. Skips rather than fails without a database, the way
     * ShrineIndexParityTest does — and checks for local.php first, because merely
     * asking the container for the adapter without it raises a warning, which
     * failOnWarning turns into a failure no later catch can undo.
     */
    public function testTheTwoEncodersAgreeOnTheRealPayload(): void
    {
        $payload = $this->realPayload();

        self::assertSame(Json::encode($payload), $this->asTheControllerEncodesIt($payload));
    }

    /**
     * The same claim on a payload containing every character the two encoders' flags
     * disagree about. No database, so this runs everywhere and is the assertion that
     * actually constrains the choice of flags.
     *
     * A shrine name really can contain an apostrophe — "Our Lady's shrine" — so this is
     * not a synthetic worry.
     */
    public function testTheTwoEncodersAgreeOnTheCharactersTheirFlagsDifferOn(): void
    {
        $hostile = [
            'type'     => 'FeatureCollection',
            'features' => [
                [
                    'type'       => 'Feature',
                    'geometry'   => ['type' => 'Point', 'coordinates' => [29.397926, -3.371276]],
                    'properties' => ['name' => 'O\'Higgins <b>&</b> "Sion" / Ünïcode'],
                ],
            ],
        ];

        self::assertSame(
            Json::encode($hostile),
            $this->asTheControllerEncodesIt($hostile),
            'the ported endpoint must encode exactly as Laminas\Json\Json does — if this fails, '
            . 'ShrinesGeoJsonController is no longer using a plain JsonResponse, or Symfony has '
            . 'changed its default encoding flags'
        );
    }

    /**
     * The bytes App\Controller\ShrinesGeoJsonController puts on the wire, produced the
     * same way it produces them. Not a repetition of the flag constants: those are the
     * thing under test, and a test that restates them cannot catch them changing.
     *
     * @param array<string, mixed> $payload
     */
    private function asTheControllerEncodesIt(array $payload): string
    {
        return (string) (new JsonResponse($payload))->getContent();
    }

    /**
     * The payload's shape, so a change to getShrineGeoJson() that empties it cannot let
     * the encoder comparison above pass vacuously on `[]`.
     */
    public function testTheRealPayloadIsAGeoJsonFeatureCollectionWithFeatures(): void
    {
        $payload = $this->realPayload();

        self::assertSame('FeatureCollection', $payload['type'] ?? null);
        self::assertIsArray($payload['features'] ?? null);
        self::assertNotEmpty($payload['features'], 'no features: the encoder comparison would be vacuous');
    }

    /** @return array<string, mixed> */
    private function realPayload(): array
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        try {
            /** @var Adapter $adapter */
            $adapter = $this->bridge()->get(Adapter::class);
            $adapter->getDriver()->getConnection()->connect();
            /** @var SchoenstattTable $table */
            $table = $this->bridge()->get(SchoenstattTable::class);
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }

        $payload = $table->getShrineGeoJson()->jsonSerialize();

        return is_array($payload) ? $payload : [];
    }

    /** Config caches off, as ShrineIndexParityTest does: data/config/ holds a merge an earlier run wrote. */
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
}

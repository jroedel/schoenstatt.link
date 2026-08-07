<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Controller\ShrinesGeoJsonController;
use App\Laminas\ServiceBridge;
use Laminas\Db\Adapter\Adapter;
use Locale;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoenstatt\Controller\AssociationsApiV1Controller;
use Schoenstatt\Controller\AssociationsApiV2Controller;
use Schoenstatt\Model\SchoenstattTable;
use Throwable;

use function is_readable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The shrine GeoJSON feed announces its deprecation from **both** front controllers.
 *
 * Deprecated 2026-08-07: still served, still correct, but carrying `Deprecation: true`
 * (IETF draft header) so a caller finds out without reading the source. No `Sunset`,
 * because no removal date has been decided — see docs/BACKLOG.md.
 *
 * Why this test exists rather than a smoke assertion alone: the header has to be set in
 * **three** places that no single request can check together.
 *
 *   App\Controller\ShrinesGeoJsonController          — what the capsule serves
 *   AssociationsApiV1Controller::shrinesJsonAction() — what production serves
 *   AssociationsApiV2Controller::shrinesJsonAction() — likewise
 *
 * test/Smoke/ShrinesGeoJsonSmokeTest covers the first, because the capsule runs the
 * Symfony kernel; tools/smoke-prod.sh covers the other two, because production does not.
 * Neither can cover both, so a deprecation announced on only one front controller would
 * pass every check while reaching none of the callers that matter — production's. This
 * closes that gap in one process.
 *
 * The laminas actions are driven directly, which works because
 * `AbstractController::getResponse()` constructs an `HttpResponse` on demand rather than
 * requiring one from an MvcEvent. That is the whole of the MVC machinery these two
 * actions need.
 */
class GeoJsonDeprecationTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    public static function setUpBeforeClass(): void
    {
        //getShrines() indexes per-locale arrays by this string; the CLI default is
        //en_US_POSIX and every lookup would miss
        Locale::setDefault('en_US');
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function laminasControllers(): array
    {
        return [
            'v1' => [AssociationsApiV1Controller::class],
            'v2' => [AssociationsApiV2Controller::class],
        ];
    }

    /**
     * The half production serves. Asserted on the response object the action mutates,
     * which is exactly what `sendHeaders()` would put on the wire.
     *
     * @param class-string $controllerClass
     */
    #[DataProvider('laminasControllers')]
    public function testTheLaminasActionSendsTheDeprecationHeader(string $controllerClass): void
    {
        $this->requireDatabase();

        /** @var SchoenstattTable $table */
        $table = $this->bridge()->get(SchoenstattTable::class);
        /** @var array<string, mixed> $config */
        $config = $this->bridge()->config();

        /** @var AssociationsApiV1Controller|AssociationsApiV2Controller $controller */
        $controller = new $controllerClass($table, $config);
        $controller->shrinesJsonAction();

        $header = $controller->getResponse()->getHeaders()->get('Deprecation');

        self::assertNotFalse(
            $header,
            "$controllerClass::shrinesJsonAction() sends no Deprecation header — production serves "
            . 'this action, so a deprecation missing here is a deprecation no caller ever sees'
        );
        self::assertSame('true', $header->getFieldValue());
    }

    /**
     * And the ported half agrees, from its own constants rather than from a repeated
     * string literal — so renaming the header in one place cannot silently desynchronize
     * the two front controllers.
     */
    public function testThePortedControllerDeclaresTheSameHeaderAndValue(): void
    {
        self::assertSame('Deprecation', ShrinesGeoJsonController::DEPRECATION_HEADER);
        self::assertSame('true', ShrinesGeoJsonController::DEPRECATION_VALUE);
    }

    /**
     * Deprecated is not retired. If a later change starts answering 410 or dropping the
     * payload, that is a *removal* and needs the decision in docs/BACKLOG.md made first —
     * plus a Sunset header, which deliberately does not exist yet.
     *
     * @param class-string $controllerClass
     */
    #[DataProvider('laminasControllers')]
    public function testTheDeprecatedEndpointStillReturnsItsPayload(string $controllerClass): void
    {
        $this->requireDatabase();

        /** @var SchoenstattTable $table */
        $table = $this->bridge()->get(SchoenstattTable::class);
        /** @var array<string, mixed> $config */
        $config = $this->bridge()->config();

        /** @var AssociationsApiV1Controller|AssociationsApiV2Controller $controller */
        $controller = new $controllerClass($table, $config);
        $model      = $controller->shrinesJsonAction();

        $payload = $model->getVariables();
        self::assertSame('FeatureCollection', $payload['type'] ?? null);
        self::assertNotEmpty($payload['features'] ?? [], 'the deprecated feed lost its features');
        self::assertSame(200, $controller->getResponse()->getStatusCode(), 'deprecated is not gone');
    }

    private function requireDatabase(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        try {
            /** @var Adapter $adapter */
            $adapter = $this->bridge()->get(Adapter::class);
            $adapter->getDriver()->getConnection()->connect();
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }
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
}

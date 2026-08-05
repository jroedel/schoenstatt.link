<?php

declare(strict_types=1);

namespace App;

use App\Controller\CacheStatusController;
use App\Controller\ClearPersistentCacheController;
use App\Controller\HealthController;
use App\Http\LaminasResponseConverter;
use App\Http\LegacyBridge;
use App\Http\MaintenanceKey;
use App\Http\ProtocolVersionListener;
use App\Laminas\ServiceBridge;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

use function dirname;

/**
 * The Symfony kernel, hand-wired from components.
 *
 * There is no FrameworkBundle here and that is not an oversight: it requires
 * symfony/cache, which requires psr/cache ^2|^3, which laminas-cache 3.14 pins
 * to ^1. laminas-cache 4 lifts that pin, but it needs laminas-servicemanager ^4.5
 * and laminas-mvc requires ^3.20.0 in *every* version it has, 4.0.x-dev included.
 * So the gate is laminas-mvc itself — i.e. finishing this migration — and not, as
 * this said until 2026-08-05, retiring kokspflanze/bjy-authorize: that also caps
 * laminas-cache at ^3 and is also worth retiring, but removing it leaves the pin
 * exactly where it is. The measurement is in docs/php-85.md.
 *
 * The kernel is not gated on any of that: everything below comes from
 * symfony/http-kernel and symfony/routing, which need no psr/cache at all. When
 * the gate does open, this class is what FrameworkBundle replaces; the routes and
 * the LegacyBridge behind it carry over unchanged.
 *
 * Exceptions are left to propagate. HttpKernel catches them and dispatches
 * kernel.exception, but nothing listens, so handleThrowable() rethrows and the
 * throwable reaches SionModel\Error\FatalErrorHandler — registered in
 * public/index.php before any of this exists. One error path for both front
 * controllers is worth more than a second, prettier one.
 */
final class Kernel implements HttpKernelInterface, TerminableInterface
{
    private HttpKernel $httpKernel;
    private ServiceBridge $laminas;

    /**
     * @param array<string, mixed> $appConfig the merged config/application.config.php,
     *                                        handed on to the laminas application
     */
    public function __construct(private readonly array $appConfig)
    {
    }

    public function handle(
        Request $request,
        int $type = HttpKernelInterface::MAIN_REQUEST,
        bool $catch = true
    ): Response {
        return $this->httpKernel()->handle($request, $type, $catch);
    }

    public function terminate(Request $request, Response $response): void
    {
        // Nothing listens to kernel.terminate yet. Calling it anyway means a
        // listener added later actually runs, instead of being mysteriously dead.
        $this->httpKernel()->terminate($request, $response);
    }

    /**
     * Built on first use rather than in the constructor so that a request which
     * never reaches handle() — a 403 from the SAPI guard, say — pays nothing.
     */
    private function httpKernel(): HttpKernel
    {
        if (isset($this->httpKernel)) {
            return $this->httpKernel;
        }

        $requestStack = new RequestStack();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(
            new UrlMatcher($this->routes(), new RequestContext()),
            $requestStack
        ));
        $dispatcher->addListener(KernelEvents::RESPONSE, new ProtocolVersionListener());

        return $this->httpKernel = new HttpKernel(
            $dispatcher,
            new ContainerControllerResolver($this->container()),
            $requestStack,
            new ArgumentResolver()
        );
    }

    private function container(): Container
    {
        return new Container([
            // The converter is stateless, so it is built here rather than given
            // an id of its own: nothing else asks for it, and a service id only
            // an adjacent factory uses is indirection without a reader.
            LegacyBridge::class => fn (): LegacyBridge => new LegacyBridge(
                $this->appConfig,
                new LaminasResponseConverter()
            ),
            HealthController::class => static fn (): HealthController => new HealthController(),
            // The ported maintenance endpoints. They share one ServiceBridge, so
            // a request that reaches either loads the laminas modules once — and
            // a request that reaches neither loads them not at all, because
            // laminas() is only called when a factory actually runs.
            CacheStatusController::class => fn (): CacheStatusController => new CacheStatusController(
                new MaintenanceKey($this->laminas())
            ),
            ClearPersistentCacheController::class
                => fn (): ClearPersistentCacheController => new ClearPersistentCacheController(
                    new MaintenanceKey($this->laminas()),
                    $this->laminas()
                ),
        ]);
    }

    /**
     * Read access to the laminas services for a ported controller, built at most
     * once per request. Kept off App\Container on purpose: the container's own
     * docblock explains why it must be able to resolve LegacyBridge — the thing
     * that *builds* the laminas application — without any laminas involvement.
     */
    private function laminas(): ServiceBridge
    {
        return $this->laminas ??= new ServiceBridge($this->appConfig);
    }

    private function routes(): RouteCollection
    {
        return require dirname(__DIR__) . '/config/symfony/routes.php';
    }
}

<?php

declare(strict_types=1);

namespace App;

use App\Authorization\RouteGuard;
use App\Api\BotIdentity;
use App\Controller\AdminController;
use App\Controller\Api\ApiSchemaController;
use App\Controller\Api\AssociationsV3Controller;
use App\Controller\Api\MethodNotAllowedController;
use App\Controller\Api\PhrasesV3Controller;
use App\Controller\AssociationEditController;
use App\Controller\AssociationsController;
use App\Controller\CacheStatusController;
use App\Controller\ClearPersistentCacheController;
use App\Controller\ContentPageController;
use App\Controller\DataProblemsController;
use App\Controller\DictionaryController;
use App\Controller\HealthController;
use App\Controller\LibrariesController;
use App\Controller\MusicController;
use App\Controller\OneFiftyPreguntasController;
use App\Controller\PhpInfoController;
use App\Controller\RolesController;
use App\Controller\ShrinesController;
use App\Controller\ShrinesGeoJsonController;
use App\Controller\TimelineController;
use App\Controller\ViewChangesController;
use App\Controller\WaysideShrinesController;
use App\Http\AuthorizationListener;
use App\Http\CspListener;
use App\Http\CspNonce;
use App\Http\GdprCookieListener;
use App\Http\InventedCacheControlListener;
use App\Http\LaminasResponseConverter;
use App\Http\LegacyBridge;
use App\Http\LocaleListener;
use App\Http\MaintenanceKey;
use App\Http\PhraseFlushListener;
use App\Http\ProtocolVersionListener;
use App\Http\SessionListener;
use App\Laminas\RouteUrl;
use App\Laminas\PhraseFlush;
use App\Laminas\ServiceBridge;
use App\Laminas\ViewHelpers;
use App\Twig\TwigFactory;
use SionModel\Error\FatalErrorHandler;
use SionModel\Error\RequestContext as ErrorRequestContext;
use SionModel\Service\ErrorHandling;
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
use Throwable;
use Twig\Environment;

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
 * controllers is worth more than a second, prettier one. What handle() adds is
 * the *configured* half of that path, which a ported route otherwise misses
 * entirely: see reportFailuresLikeLaminasDoes().
 */
final class Kernel implements HttpKernelInterface, TerminableInterface
{
    private HttpKernel $httpKernel;
    private ServiceBridge $laminas;

    private PhraseFlush $phrases;
    private RequestStack $requests;
    private CspNonce $cspNonce;
    private Environment $twig;
    private ViewHelpers $viewHelpers;
    private RouteUrl $routeUrl;
    private RouteGuard $routeGuard;

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
        $this->reportFailuresLikeLaminasDoes();

        return $this->httpKernel()->handle($request, $type, $catch);
    }

    /**
     * Give a failure on a *ported* route the same reporting pipeline a laminas-served
     * one gets — notification included.
     *
     * public/index.php installs SionModel\Error\FatalErrorHandler before anything else,
     * and SionModel\Module::onBootstrap() then upgrades it to the fully configured
     * pipeline: the configured store, the request context, and the email. A ported route
     * runs no module's onBootstrap, so until this existed its failures took the
     * container-free fallback — a record in data/exceptions under default settings, and
     * **nobody told**. Survivable while the kernel was cookie-gated; not something to
     * carry into a global flip, where every ported route including the v3 API reports
     * this way.
     *
     * Two properties, both load-bearing:
     *
     * - **Lazy.** The closure runs only once something has already failed, so a healthy
     *   request still builds no ServiceBridge and none of the reporting services —
     *   ExceptionsLogger opens a file handle when constructed and RequestContext pulls in
     *   the acting-user provider. That is the same reason the laminas side passes a
     *   resolver rather than services.
     * - **It cannot make reporting worse.** FatalErrorHandler::report() wraps resolving
     *   *and* reporting in one try/catch whose fallback is a single line in
     *   data/logs/bootstrap-fatal.log, so a resolver that throws costs the whole
     *   ExceptionRecord. That is not hypothetical here: what failed is quite often the
     *   reason the container cannot be built. Returning [null, null] instead makes the
     *   handler take its container-free path, which is exactly what a ported route had
     *   before this method existed.
     *
     * A bridged request boots laminas and onBootstrap replaces this with the real
     * container's resolver, which is the better answer wherever it is available.
     */
    private function reportFailuresLikeLaminasDoes(): void
    {
        FatalErrorHandler::upgrade(function (): array {
            try {
                $laminas = $this->laminas();

                return [$laminas->get(ErrorHandling::class), $laminas->get(ErrorRequestContext::class)];
            } catch (Throwable) {
                return [null, null];
            }
        });
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

        $requestStack = $this->requests();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(
            new UrlMatcher($this->routes(), new RequestContext()),
            $requestStack
        ));
        //default priority, i.e. after RouterListener's 32: the locale it reads is a
        //route attribute, so there is nothing to read until routing has happened
        $dispatcher->addListener(KernelEvents::REQUEST, new LocaleListener());
        //what JUser\Module::onBootstrap() does for every bridged request and nothing
        //did for a ported one: start the session through the laminas manager, so its
        //validators apply, and prune values whose class no longer exists. Without it a
        //visitor holding a pre-Laminas session got an empty 200 from every ported HTML
        //page — the layout's flash_messages() throws on such a value. Above the guard,
        //which reads the identity out of that same session.
        $dispatcher->addListener(
            KernelEvents::REQUEST,
            new SessionListener($this->laminas(...)),
            SessionListener::PRIORITY
        );
        //the route guard BjyAuthorize\Guard\Route cannot be here to run. Below
        //RouterListener because it reads the matched route's own declaration, and
        //below LocaleListener because a 403 renders Twig and would otherwise
        //translate against en_US_POSIX. Lazily resolved: see AuthorizationListener.
        $dispatcher->addListener(
            KernelEvents::REQUEST,
            new AuthorizationListener($this->routeGuard(...)),
            AuthorizationListener::PRIORITY
        );
        $dispatcher->addListener(KernelEvents::RESPONSE, new ProtocolVersionListener());
        //all three only ever act on a Symfony-served route: on a bridged one
        //laminas-mvc's own listeners and LaminasResponseConverter have already done
        //the equivalent
        $dispatcher->addListener(KernelEvents::RESPONSE, new CspListener($this->laminas(), $this->cspNonce()));
        $dispatcher->addListener(KernelEvents::RESPONSE, new GdprCookieListener());
        $dispatcher->addListener(KernelEvents::RESPONSE, new InventedCacheControlListener());
        //After the response is sent: the write is bookkeeping and the visitor has no
        //reason to wait for it. This is the counterpart of the MvcEvent::EVENT_FINISH
        //listener a Symfony-served route never reaches, and without it no phrase a
        //ported page discovers is ever written. See App\Laminas\PhraseFlush.
        $dispatcher->addListener(KernelEvents::TERMINATE, new PhraseFlushListener($this->phraseFlush()));

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
            // The first ported HTML route, and the reason the Twig layer exists.
            // twig() is lazy for the same reason laminas() is: a request that
            // renders no template — /_health, the maintenance endpoints, anything
            // bridged — never builds any of it.
            ShrinesController::class => fn (): ShrinesController => new ShrinesController(
                $this->laminas(),
                $this->twig(),
                $this->routeUrl()
            ),
            // The same page over the wayside-shrine associations. Identical
            // dependencies because it is the same page: what differs is one table
            // method and one template header block, not the wiring.
            WaysideShrinesController::class => fn (): WaysideShrinesController => new WaysideShrinesController(
                $this->laminas(),
                $this->twig(),
                $this->routeUrl()
            ),
            // The five static content pages, behind one controller. It is the first
            // ported controller that needs *neither* laminas services nor the merged
            // config: a template, and RouteUrl for its breadcrumb and locale-redirect
            // links. RouteUrl does reach laminas for the router, but only when a link
            // is actually assembled.
            ContentPageController::class => fn (): ContentPageController => new ContentPageController(
                $this->twig(),
                $this->routeUrl()
            ),
            // Both shrine GeoJSON versions, one controller: the two laminas actions
            // are byte-identical. No Twig — it answers JSON — so this is the only
            // ported HTML-era route that builds no template environment.
            ShrinesGeoJsonController::class => fn (): ShrinesGeoJsonController => new ShrinesGeoJsonController(
                $this->laminas(),
                $this->routeUrl()
            ),
            // The most tightly guarded page ported so far: sch_administrator only, a
            // role with no descendants. No ServiceBridge of its own — phpinfo() needs
            // nothing from laminas, and RouteUrl reaches it lazily for links.
            PhpInfoController::class => fn (): PhpInfoController => new PhpInfoController(
                $this->twig(),
                $this->routeUrl()
            ),
            // The data-problems list. Needs the laminas container for ProblemService
            // *and* Twig, and its template reaches back for formatEntity through
            // App\Laminas\EntityFormatter.
            DataProblemsController::class => fn (): DataProblemsController => new DataProblemsController(
                $this->laminas(),
                $this->twig(),
                $this->routeUrl()
            ),
            // The changes log. Reads the merged sion_model config to decide how much of
            // the database to read, so it needs the bridge as well as Twig.
            ViewChangesController::class => fn (): ViewChangesController => new ViewChangesController(
                $this->laminas(),
                $this->twig(),
                $this->routeUrl()
            ),
            // Batch 4. Every one of these is the same three dependencies — the
            // laminas services for its table, Twig for its template, RouteUrl for
            // its links — because that is what a read-only index page needs and
            // nothing more. Where a controller needs none of the three it says so.
            TimelineController::class => fn (): TimelineController => new TimelineController(
                $this->laminas(),
                $this->twig(),
                $this->routeUrl()
            ),
            MusicController::class => fn (): MusicController => new MusicController(
                $this->laminas(),
                $this->twig(),
                $this->routeUrl()
            ),
            DictionaryController::class => fn (): DictionaryController => new DictionaryController(
                $this->laminas(),
                $this->twig(),
                $this->routeUrl()
            ),
            // The one batch-4 controller that needs no laminas services: its content is
            // a file on disk, and RouteUrl reaches laminas only when a link is
            // assembled.
            OneFiftyPreguntasController::class
                => fn (): OneFiftyPreguntasController => new OneFiftyPreguntasController(
                    $this->twig(),
                    $this->routeUrl()
                ),
            AssociationsController::class => fn (): AssociationsController => new AssociationsController(
                $this->laminas(),
                $this->twig(),
                $this->routeUrl()
            ),
            // The v3 API. BotIdentity and the controller share one ServiceBridge like
            // everything else here, so a request that never reaches them loads no
            // laminas modules — /api/v3/schema is the only one of the three that
            // touches the database at all before authenticating, and it is public.
            AssociationsV3Controller::class => fn (): AssociationsV3Controller => new AssociationsV3Controller(
                $this->laminas(),
                new BotIdentity($this->laminas())
            ),
            // The translation API. It takes RouteUrl as well as the bridge, because a
            // phrase's only context is the route it was first seen on and turning that
            // into a URL an agent can fetch is exactly what RouteUrl does.
            PhrasesV3Controller::class => fn (): PhrasesV3Controller => new PhrasesV3Controller(
                $this->laminas(),
                $this->routeUrl(),
                new BotIdentity($this->laminas())
            ),
            ApiSchemaController::class => fn (): ApiSchemaController => new ApiSchemaController($this->laminas()),
            MethodNotAllowedController::class
                => static fn (): MethodNotAllowedController => new MethodNotAllowedController(),
            // The first form route. Same three dependencies as every other ported
            // HTML page — the form itself comes from the laminas container through
            // the bridge, so nothing new is wired here.
            AssociationEditController::class => fn (): AssociationEditController => new AssociationEditController(
                $this->laminas(),
                $this->twig(),
                $this->routeUrl()
            ),
            RolesController::class => fn (): RolesController => new RolesController(
                $this->laminas(),
                $this->twig(),
                $this->routeUrl()
            ),
            LibrariesController::class => fn (): LibrariesController => new LibrariesController(
                $this->laminas(),
                $this->twig(),
                $this->routeUrl()
            ),
            // The first restricted page, and the only reason to trust
            // App\Authorization\RouteGuard: a bridge no guarded route exercises
            // proves nothing. Same three dependencies as the shrines port — the
            // authorization is entirely in the route declaration, not here.
            AdminController::class => fn (): AdminController => new AdminController(
                $this->laminas(),
                $this->twig(),
                $this->routeUrl()
            ),
        ]);
    }

    /**
     * The authorization check for Symfony-served routes, built at most once per
     * request and only when a request actually reaches the listener.
     *
     * Not built where the listener is registered, which happens before the request
     * exists: routeUrl() reads the request's base URL and would memoize an empty one
     * for the whole request, quietly stripping the prefix off every link on every
     * ported page. Twig is handed over as a closure for a smaller version of the same
     * argument — only the 403 branch renders anything, and /_health should not pay
     * TwigFactory's cache-writability probe to be told it is public.
     */
    private function routeGuard(): RouteGuard
    {
        return $this->routeGuard ??= new RouteGuard(
            $this->laminas(),
            $this->routeUrl(),
            $this->twig(...)
        );
    }

    /**
     * The Twig environment every ported HTML route renders through, and the two
     * extensions that give its templates a way back to laminas. Read
     * templates/layout.html.twig and App\Twig\LaminasExtension before adding a
     * third: the interesting constraint is which laminas view helpers can be
     * reached at all.
     */
    private function twig(): Environment
    {
        return $this->twig ??= (new TwigFactory())->create(
            $this->laminas(),
            $this->viewHelpers(),
            $this->routeUrl(),
            $this->requests(),
            $this->cspNonce()
        );
    }

    private function viewHelpers(): ViewHelpers
    {
        return $this->viewHelpers ??= new ViewHelpers($this->laminas());
    }

    /**
     * The laminas router, base URL already pointed at the request's locale prefix.
     * Shared, because setting that base URL twice on the same router service would
     * double the prefix.
     */
    private function routeUrl(): RouteUrl
    {
        return $this->routeUrl ??= new RouteUrl(
            $this->laminas(),
            $this->requests()->getMainRequest()?->getBaseUrl() ?? ''
        );
    }

    private function cspNonce(): CspNonce
    {
        return $this->cspNonce ??= new CspNonce();
    }

    private function requests(): RequestStack
    {
        return $this->requests ??= new RequestStack();
    }

    /**
     * Read access to the laminas services for a ported controller, built at most
     * once per request. Kept off App\Container on purpose: the container's own
     * docblock explains why it must be able to resolve LegacyBridge — the thing
     * that *builds* the laminas application — without any laminas involvement.
     */
    private function laminas(): ServiceBridge
    {
        return $this->laminas ??= new ServiceBridge($this->appConfig, $this->phraseFlush());
    }

    /**
     * The end-of-request phrase flush, shared between the ServiceBridge that arms it
     * and the listener that runs it. Built eagerly because it is a single nullable
     * property — it touches no laminas service until something translates.
     */
    private function phraseFlush(): PhraseFlush
    {
        return $this->phrases ??= new PhraseFlush();
    }

    private function routes(): RouteCollection
    {
        return require dirname(__DIR__) . '/config/symfony/routes.php';
    }
}

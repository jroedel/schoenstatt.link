<?php

declare(strict_types=1);

namespace App\Http;

use Laminas\Mvc\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_string;
use function str_contains;

/**
 * The strangler seam: a Symfony controller that runs the laminas-mvc application.
 *
 * Every path the Symfony router has not claimed reaches this class, so for now
 * that is nearly the whole site. Porting a route means declaring it ahead of the
 * catch-all in config/symfony/routes.php; nothing here needs to change as that
 * happens, and this class is deleted when the last route has moved.
 *
 * Inbound needs no translation at all, which is the part that makes this
 * tractable: Symfony's Request is built *from* the superglobals and never
 * mutates them, so Laminas\Http\PhpEnvironment\Request still reads the real
 * $_SERVER, $_GET, $_POST and php://input. Only the response comes back the
 * other way, through LaminasResponseConverter.
 */
final class LegacyBridge
{
    private const NOT_FOUND_TEMPLATE = 'error/404.html.twig';

    /** @param array<string, mixed> $appConfig the merged config/application.config.php */
    public function __construct(
        private readonly array $appConfig,
        private readonly LaminasResponseConverter $converter,
        private readonly Environment $twig
    ) {
    }

    /**
     * The Request is accepted and ignored on purpose — see the class docblock:
     * the Laminas application reads the superglobals itself. Declaring it keeps
     * the signature honest about what Symfony hands a controller, and gives a
     * ported route something to copy.
     */
    public function __invoke(Request $request): Response
    {
        $application = Application::init($this->appConfig);

        // Take the emitter out of the pipeline, and nothing else. Routing,
        // dispatch, the view, and every MvcEvent::FINISH listener still run
        // exactly as they do today — including the ones that write headers
        // straight to the SAPI — but the response is handed back instead of
        // echoed. bootstrap() attached this same shared instance to this same
        // event manager, so detaching removes precisely the listener it added.
        $application->getServiceManager()
            ->get('SendResponseListener')
            ->detach($application->getEventManager());

        // run() populates the response on every path it can take, the
        // dispatch.error ones included, and returns $this.
        $application->run();

        $response = ($this->converter)($application->getResponse());

        return $this->isHtmlNotFound($response) ? $this->notFound() : $response;
    }

    /**
     * Whether the bridged response is an **HTML** 404 — the laminas "A 404 error occurred"
     * page for a URL neither router matched.
     *
     * The content-type guard is the whole of the safety here, and it is a **deny-list, not an
     * allow-list**, for a reason measured rather than assumed. `RestApi`'s
     * `api-route-not-found` returns a JSON 404 (and a 410 for retired versions, which is not
     * a 404 at all) with `Content-Type: application/json` set explicitly on the response, so
     * that survives. But laminas sets **no** Content-Type on its HTML error page — PHP's
     * SAPI default fills in `text/html` only when the response is sent, which is *after* this
     * runs — so at this point the generic 404 carries a null content type. An allow-list on
     * `text/html` therefore matched nothing and the substitution silently never happened
     * (measured 2026-09-08). Treat "404 and not JSON" as the HTML error page; a future
     * bridged 404 that is neither HTML nor JSON (there is none today) would need adding here.
     */
    private function isHtmlNotFound(Response $response): bool
    {
        if (Response::HTTP_NOT_FOUND !== $response->getStatusCode()) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type');

        return ! is_string($contentType) || ! str_contains($contentType, 'json');
    }

    /**
     * The Symfony-rendered 404, in the shared layout, replacing the laminas view script.
     *
     * Phase A of the laminas-mvc removal: after this, the bridge renders no HTML page to a
     * visitor. `error/404.html.twig` reproduces what the .phtml drew in production. The
     * status is set explicitly — `Environment::render()` returns a string with no status of
     * its own — and the 404 is preserved.
     */
    private function notFound(): Response
    {
        return new Response(
            //`page_title => ''` is the layout's "no prefix" case — the title is the site name
            //alone, which is what the .phtml gives by never calling headTitle(). Omitting it
            //is the fatal-200 wedge: strict_variables makes a missing page_title a RuntimeError
            //and the visitor gets a blank 200. Measured 2026-09-08.
            $this->twig->render(self::NOT_FOUND_TEMPLATE, ['page_title' => '']),
            Response::HTTP_NOT_FOUND,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }
}

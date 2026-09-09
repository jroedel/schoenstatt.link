<?php

declare(strict_types=1);

namespace App\Http;

use App\Laminas\ServiceBridge;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

use function is_array;
use function is_string;
use function str_contains;
use function str_replace;

/**
 * Sends the same Content-Security-Policy on a Symfony-served HTML page that
 * SionModel\Mvc\CspListener sends on a laminas-served one.
 *
 * docs/laminas-exit.md records the CSP as one of the things a ported route loses,
 * and for the two maintenance endpoints that was true and harmless — they answer
 * JSON. It stops being harmless the moment an HTML page moves: the shrines page
 * carries an inline <script> that sizes the progress bars, so the policy and the
 * nonce that whitelists that one script have to travel with it, or the first
 * ported page is also the first page on the site with no CSP at all.
 *
 * Two deliberate differences from the laminas listener:
 *
 * 1. The header goes on the Symfony Response instead of through `header()`.
 *    Response::sendHeaders() emits it either way, and putting it on the object
 *    keeps it visible to anything else listening on kernel.response.
 * 2. Only HTML responses get it, which also keeps /_health and the maintenance
 *    endpoints from paying for the module load this listener's config read needs:
 *    the ServiceBridge is lazy, and a JSON response returns before it is touched.
 *
 * Bridged requests are skipped — SionModel's listener runs inside
 * Application::run() and has already done this, at MvcEvent::EVENT_RENDER.
 */
final class CspListener
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly CspNonce $nonce
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (! $event->isMainRequest() || ! SymfonyRoute::isPorted($event->getRequest())) {
            return;
        }

        //an unset Content-Type counts as HTML: Symfony's Response only fills that
        //header in during prepare(), which is not called here (see
        //App\Http\LaminasResponseConverter for why), so a plain HTML Response
        //carries none and PHP's default_mimetype sends text/html for it
        $response = $event->getResponse();
        $type     = $response->headers->get('Content-Type');
        if (null !== $type && ! str_contains($type, 'text/html')) {
            return;
        }

        $policy = $this->policy();
        if (null === $policy) {
            return;
        }

        $response->headers->set('Content-Security-Policy', str_replace('{:nonce}', $this->nonce->value(), $policy));
    }

    /**
     * Null when the application has switched the policy off, which it expresses by
     * omitting `csp_string` or `inject_headers_event` — the same two keys
     * SionModel\Mvc\CspListener::attach() checks before it attaches at all.
     */
    private function policy(): ?string
    {
        $config = $this->laminas->config()['sion_model'] ?? null;
        if (! is_array($config) || ! is_array($config['csp_config'] ?? null)) {
            return null;
        }

        $csp = $config['csp_config'];
        if (! isset($csp['inject_headers_event']) || ! is_string($csp['csp_string'] ?? null)) {
            return null;
        }

        return $csp['csp_string'];
    }
}

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
 * Sends the Content-Security-Policy on every HTML page, with the per-request nonce that
 * whitelists the page's own inline scripts.
 *
 * The policy is `sion_model.csp_config.csp_string` with `{:nonce}` replaced; omitting
 * that key switches the header off. Only HTML responses get it, which also keeps
 * /_health and the maintenance endpoints from paying for the module load this
 * listener's config read needs: the ServiceBridge is lazy, and a JSON response returns
 * before it is touched. The header goes on the Symfony Response rather than through
 * `header()`, so anything else listening on kernel.response can see it.
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
     * omitting `sion_model.csp_config.csp_string`.
     */
    private function policy(): ?string
    {
        $config = $this->laminas->config()['sion_model'] ?? null;
        if (! is_array($config) || ! is_array($config['csp_config'] ?? null)) {
            return null;
        }

        $csp = $config['csp_config'];
        if (! is_string($csp['csp_string'] ?? null)) {
            return null;
        }

        return $csp['csp_string'];
    }
}

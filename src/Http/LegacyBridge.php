<?php

declare(strict_types=1);

namespace App\Http;

use Laminas\Mvc\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
    /** @param array<string, mixed> $appConfig the merged config/application.config.php */
    public function __construct(
        private readonly array $appConfig,
        private readonly LaminasResponseConverter $converter
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

        return ($this->converter)($application->getResponse());
    }
}

<?php

namespace Application\View\Helper;

use App\View\ServingNote as Note;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Helper\AbstractHelper;

/**
 * The footer's "how was this page served" line, laminas side.
 *
 * The Twig layout gets the same string from App\Twig\ChromeExtension::servingNote();
 * both delegate to App\View\ServingNote so the two renderings cannot describe a page
 * differently. Since the SYMFONY_KERNEL canary was retired (2026-09-08) the note reports
 * only the renderer — a ported Twig template versus a `.phtml` bridged through
 * App\Http\LegacyBridge — plus the route and handler; this helper is the note for a
 * bridged `.phtml` page, and stays until LegacyBridge itself is deleted.
 *
 * The MvcEvent is where the route match lives, and it is read at *render* time
 * rather than stored from it — on an error page there may be no match at all, and a
 * note that throws on the one page that is already failing would be worse than
 * useless.
 */
class ServingNote extends AbstractHelper
{
    public function __construct(private readonly MvcEvent $event)
    {
    }

    public function __invoke(): string
    {
        $match = $this->event->getRouteMatch();

        return Note::describe(
            Note::RENDERER_PHTML,
            null === $match ? null : $match->getMatchedRouteName(),
            $this->handler(),
            $_COOKIE
        );
    }

    /**
     * `Controller\Class::action`, the way the dispatcher resolved it.
     *
     * ModuleRouteListener has already rewritten the `controller` param to the FQCN by
     * the time anything renders, so this is the class that actually ran — which is the
     * useful thing when a page looks wrong and the route name looks right.
     */
    private function handler(): ?string
    {
        $match = $this->event->getRouteMatch();
        if (null === $match) {
            return null;
        }

        $controller = $match->getParam('controller');
        if (! is_string($controller) || '' === $controller) {
            return null;
        }

        $action = $match->getParam('action');

        return is_string($action) && '' !== $action
            ? $controller . '::' . $action
            : $controller;
    }
}

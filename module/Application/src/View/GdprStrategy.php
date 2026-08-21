<?php

/**
 * BjyAuthorize Module (https://github.com/bjyoungblood/BjyAuthorize)
 *
 * @link https://github.com/bjyoungblood/BjyAuthorize for the canonical source repository
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\View;

use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\MvcEvent;
use Laminas\Stdlib\ResponseInterface as Response;
use Laminas\Session\SessionManager;
use Laminas\View\Model\ViewModel;

class GdprStrategy implements ListenerAggregateInterface
{
    /**
     * @var string
     */
    protected $template;

    /**
     * @var callable[] An array with callback functions or methods.
     */
    protected $listeners = [];

    /**
     * @param string $template name of the template to use on unauthorized requests
     */
    public function __construct($template)
    {
        $this->template = (string)$template;
    }

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        //**Only `onFinish()` since 2026-08-21.** `onRoute()` swapped the route match of
        //`zfcuser/login` and `zfcuser/verify` for a cookie explainer when the visitor had
        //not consented — and JUser's `LoginController`, the only thing that ever served
        //those paths under laminas, is gone. The gate lives in `JUser\Page\SignIn` and
        //`JUser\Page\CookieExplainer` now, which answer at the requested URL with a 200
        //exactly as the swap did.
        //
        //Worth knowing what the swap *was*, because it is the reason the page it served had
        //no route: it ran at priority -5000, i.e. after BjyAuthorize's guard had already
        //approved a *different* route, and built its RouteMatch by hand. So the page it
        //swapped in rendered without ever being authorized, and `/sign-in-no-cookies` — a
        //real route with no guard entry, therefore denied to everyone — was unreachable for
        //the whole of its existence.
        $this->listeners[] = $events->attach(MvcEvent::EVENT_FINISH, [$this, 'onFinish'], 5000);
    }

    /**
     * {@inheritDoc}
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    /**
     * @param string $template
     */
    public function setTemplate($template)
    {
        $this->template = (string)$template;
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    public function onFinish(MvcEvent $event)
    {
        $app = $event->getApplication();
        $sm = $app->getServiceManager();
        $hasConsented = isset($_COOKIE['EU_COOKIE_LAW_CONSENT']) && 'true' === $_COOKIE['EU_COOKIE_LAW_CONSENT'];
        if (! $hasConsented) {
            header_remove('Set-Cookie');
            /** @var \Laminas\Session\ManagerInterface $sessionManager */
            $sessionManager = $sm->get(SessionManager::class);
            //expire session
            $sessionManager->expireSessionCookie();
            //delete slm_locale cookie
            setcookie(
                'slm_locale', // session name
                '', // value
                $_SERVER['REQUEST_TIME'] - 42000,
                '/'
            );
        }
    }
}

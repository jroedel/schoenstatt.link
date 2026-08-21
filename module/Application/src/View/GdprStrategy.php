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
use Application\Controller\IndexController;
use Laminas\Router\Http\RouteMatch;

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
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoute'], -5000);
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

    /**
     * Callback used when a dispatch error occurs. Modifies the
     * response object with an according error if the application
     * event contains an exception related with authorization.
     *
     * @param MvcEvent $event
     *
     * @return void
     */
    public function onRoute(MvcEvent $event)
    {
        $hasConsented = isset($_COOKIE['EU_COOKIE_LAW_CONSENT']) && 'true' === $_COOKIE['EU_COOKIE_LAW_CONSENT'];
        $route = $event->getRouteMatch();
        //all auth entry points need cookies (session + CSRF); without consent,
        //onFinish() strips Set-Cookie, so sign-in would silently fail. Show the
        //explainer instead. Magic-link tokens are only consumed on successful
        //redemption, so the emailed link still works after consenting.
        //`zfcuser/register` was in this list until 2026-08-20, when the route was
        //retired: registering and signing in are one request under magic links.
        //Both of these are Symfony-served since batch 13 (2026-08-21), where the gate is
        //App\JUser\CookieExplainer, so this listener no longer fires in normal traffic —
        //the laminas application is not entered for a ported route at all. It is kept for
        //the rollback path: removing the four `zfcuser/*` declarations from
        //config/symfony/routes.php hands these paths back to LoginController, and without
        //this swap an unconsented visitor would get a form whose session cookie onFinish()
        //then strips, i.e. a sign-in that fails with nothing to show them. Goes with
        //LoginController.
        //
        //The route `sign-in-no-cookies` was deleted the same day and this does not need it:
        //the RouteMatch below is built by hand and setMatchedRouteName() only labels it.
        $authRoutes = ['zfcuser/login', 'zfcuser/verify'];
        if (! $hasConsented && in_array($route->getMatchedRouteName(), $authRoutes, true)) {
            $newMatch = new RouteMatch(['controller' => IndexController::class, 'action' => 'sign-in-no-cookies']);
            $newMatch->setMatchedRouteName('sign-in-no-cookies');
            $event->setRouteMatch($newMatch);
        }
        return $event;
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

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
use ZfSnapGeoip\Service\Geoip;
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
        $authRoutes = ['zfcuser/login', 'zfcuser/register', 'zfcuser/verify'];
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

    protected static function isGDPRCountry($countryCode)
    {
        static $countries;
        if (! isset($countries)) {
            $countries = [
                'BE' => 'Belgium',
                'BG' => 'Bulgaria',
                'CZ' => 'Czech Republic',
                'DK' => 'Denmark',
                'DE' => 'Germany',
                'EE' => 'Estonia',
                'IE' => 'Ireland',
                'GR' => 'Greece',
                'ES' => 'Spain',
                'FR' => 'France',
                'HR' => 'Croatia',
                'IT' => 'Italy',
                'CY' => 'Cyprus',
                'LV' => 'Latvia',
                'LT' => 'Lithuania',
                'LU' => 'Luxembourg',
                'HU' => 'Hungary',
                'MT' => 'Malta',
                'NL' => 'Netherlands',
                'AT' => 'Austria',
                'PL' => 'Poland',
                'PT' => 'Portugal',
                'RO' => 'Romania',
                'SI' => 'Slovenia',
                'SK' => 'Slovakia',
                'FI' => 'Finland',
                'SE' => 'Sweden',
                'GB' => 'United Kingdom'
            ];
        }
        return isset($countries[$countryCode]);
    }
}

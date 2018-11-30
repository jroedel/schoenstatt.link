<?php
/**
 * BjyAuthorize Module (https://github.com/bjyoungblood/BjyAuthorize)
 *
 * @link https://github.com/bjyoungblood/BjyAuthorize for the canonical source repository
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\View;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Http\Response as HttpResponse;
use Zend\Mvc\MvcEvent;
use Zend\Stdlib\ResponseInterface as Response;
use ZfSnapGeoip\Service\Geoip;
use Zend\Session\SessionManager;

class GdprStrategy implements ListenerAggregateInterface
{
    /**
     * @var string
     */
    protected $template;

    /**
     * @var callable[] An array with callback functions or methods.
     */
    protected $listeners = array();

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
        //2018-11-29 disable European blocker
//         $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, array($this, 'onRoute'), -5000);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_FINISH, array($this, 'onFinish'), 5000);
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
        $app = $event->getApplication();
        $sm = $app->getServiceManager();
        $config = $sm->get('Config');
        $ip = $_SERVER['REMOTE_ADDR'];
        $exceptions = isset($config['schoenstatt']['gdpr_ip_address_exceptions'])
            ? $config['schoenstatt']['gdpr_ip_address_exceptions'] : [];
        if (in_array($ip, $exceptions)) {
            return; //don't think about blocking
        } elseif (false !== strstr($ip, '90.44.')) {
            return;
        }
        /** @var Geoip $geoip */
        $geoip = $sm->get(Geoip::class);
        $addressRecord = $geoip->lookup($ip);
        $countryCode = $addressRecord->getCountryCode();
        if ($this->isGDPRCountry($countryCode)) {
            /** @var \Zend\Http\PhpEnvironment\Response $response */
            $response = $event->getResponse();
            $response->setStatusCode(403);
            $response->setContent("Sorry, we haven't yet implemented GDPR standards for schoenstatt.link. Please email webmaster@schoenstatt.link if you have any questions. Sorry for the inconvienence.");
            return $response;
        }
        return;
        // Do nothing if the result is a response object
        $result = $event->getResult();
        $response = $event->getResponse();

        if ($result instanceof Response || ($response && !$response instanceof HttpResponse)) {
            return;
        }
    }
    
    public function onFinish(MvcEvent $event)
    {
        $app = $event->getApplication();
        $sm = $app->getServiceManager();
        if (!isset($_COOKIE['EU_COOKIE_LAW_CONSENT']) || 'true' !== $_COOKIE['EU_COOKIE_LAW_CONSENT']) {
            header_remove('Set-Cookie');
            /** @var \Zend\Session\ManagerInterface $sessionManager */
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
        if (!isset($countries)) {
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

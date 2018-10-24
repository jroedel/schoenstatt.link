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
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\Stdlib\ResponseInterface as Response;
use Zend\View\Model\ViewModel;
use ZfSnapGeoip\Service\Geoip;

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
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, array($this, 'onRoute'), -5000);
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
        /** @var Geoip $geoip */
        $geoip = $sm->get(Geoip::class);
        $ip = $_SERVER['REMOTE_ADDR'];
        $addressRecord = $geoip->lookup($ip);
        if ($this->isGDPRCountry($addressRecord->getCountryCode())) {
//             $result = $event->getResult();
            $response = $event->getResponse(); //@todo create a new response so there aren't any cookies
            // Common view variables
//             $viewVariables = array(
//                 'error' => $event->getParam('error'),
//                 'identity' => $event->getParam('identity'),
//             );
            
//             $model = new ViewModel($viewVariables);
//             $response = $response ?: new HttpResponse();
            
//             $model->setTemplate($this->getTemplate());
//             $event->getViewModel()->addChild($model);
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

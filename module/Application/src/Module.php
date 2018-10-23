<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Session\ManagerInterface;
use ZfSnapGeoip\Service\Geoip;
use Zend\Http\Response;
use Zend\View\Model\ViewModel;
use Application\View\GdprStrategy;

class Module
{
    public function onBootstrap(MvcEvent $e)
    {
        $app = $e->getApplication();
        $sm = $app->getServiceManager();
        $strategy = $sm->get(GdprStrategy::class);
        $strategy->attach($app->getEventManager());
    }
    
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}

<?php
namespace Books;

use Zend\Mvc\MvcEvent;

class Module
{
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function onBootstrap(MvcEvent $e)
    {
        //auto-set text domain for all view scripts
        $e->getApplication()->getEventManager()->getSharedManager()
        ->attach('Zend\Mvc\Controller\AbstractActionController', 'dispatch', function($e) {
            $routeMatch = $e->getRouteMatch();
            if (isset($routeMatch)) {
                $libraryId = $routeMatch->getParam('library_id');
                if (isset($libraryId)) {
                    $table = $e->getApplication()->getServiceManager()->get('Books\Model\LibraryTable');
                    $table->setLibraryId($libraryId);
                }
            }
        }, 100);
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
}
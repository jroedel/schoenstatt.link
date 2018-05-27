<?php
namespace Books;

use Zend\Mvc\MvcEvent;
use Zend\Mvc\Controller\AbstractActionController;
use Books\Model\LibraryTable;

class Module
{
    public function onBootstrap(MvcEvent $e)
    {
        //auto-set text domain for all view scripts
        $e->getApplication()->getEventManager()->getSharedManager()
        ->attach(AbstractActionController::class, 'dispatch', function($e) {
            $routeMatch = $e->getRouteMatch();
            if (isset($routeMatch)) {
                $libraryId = $routeMatch->getParam('library_id');
                if (isset($libraryId)) {
                    $table = $e->getApplication()->getServiceManager()->get(LibraryTable::class);
                    $table->setLibraryId($libraryId);
                }
            }
        }, 100);
    }
    
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}
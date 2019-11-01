<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Model\LibraryTable;
use Books\Form\CollectionForm;

/**
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class CollectionFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $form = new CollectionForm();
        
        /** @var LibraryTable $table **/
        $table = $container->get(LibraryTable::class);
        
        /** @var \Zend\Router\RouteMatch $routeMatch */
        $routeMatch = $container->get('Application')->getMvcEvent()->getRouteMatch();
        $libraryId = $routeMatch->getParam('library_id');
        if (isset($libraryId)) {
            $table->setLibraryId($libraryId);
            $form->get('libraryId')->setValue($libraryId);
        } else {
            throw new \Exception('A CollectionForm instance can only be formed with reference to a particular library.');
        }
        return $form;
    }
}

<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Model\LibraryTable;
use Books\View\Helper\LibraryInfo;

/**
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class LibraryInfoFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var LibraryTable $table **/
        $table = $container->get(LibraryTable::class);

        $routeMatch = $container->get('application')->getMvcEvent()->getRouteMatch();

        $helper = new LibraryInfo();
        $helper->setLibraryTable($table);
        $helper->setRouteMatch($routeMatch);
        return $helper;
    }
}

<?php
namespace Bible\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Bible\View\Helper\FormatBibleVerse;

/**
 * Factory responsible of constructing the FormatEntity view helper
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class FormatBibleVerseFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return FormatBibleVerse
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $parentLocator  = $serviceLocator->getServiceLocator();
        $entityService  = $parentLocator->get ( 'SionModel\Service\EntitiesService' );
        $table          = $parentLocator->get ( 'Bible\BibleTable' );
        $viewHelper = new FormatBibleVerse($table, $entityService);
		return $viewHelper;
    }
}

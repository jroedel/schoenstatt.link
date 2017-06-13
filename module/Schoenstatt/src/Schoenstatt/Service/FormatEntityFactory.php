<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Schoenstatt\View\Helper\FormatEntity;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class FormatEntityFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $parentLocator = $serviceLocator->getServiceLocator();
        /** @var AssociationKindsService $kindsService */
        $kindsService = $parentLocator->get('Schoenstatt\AssociationKindsService');
        $valueOptions= $kindsService->getValueOptions();

        $entityService = $parentLocator->get('SionModel\Service\EntitiesService');
        $viewHelper = new FormatEntity($entityService, $valueOptions);
        return $viewHelper;
    }
}

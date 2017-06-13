<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory responsible of constructing the central collection of AssociationKind specs
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class AssociationKindsServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get('Schoenstatt\Config');
        if (!key_exists('association_kinds', $config)) {
            throw new \Exception('No association configuration defined!');
        }

        $kindsConfig = $config['association_kinds'];
        $translator = $serviceLocator->get('translator');
        $kindsService = new AssociationKindsService($kindsConfig, $translator);
        return $kindsService;
    }
}

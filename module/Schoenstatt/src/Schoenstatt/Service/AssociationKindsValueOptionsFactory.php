<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class AssociationKindsValueOptionsFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get('Config')['schoenstatt'];

        $associationTypeLabels = [];
        foreach ($config['association_kinds'] as $key => $value) {
            if (is_array($value) && key_exists('label', $value)) {
                $associationTypeLabels[$key] = $value['label'];
            }
        }
        return $associationTypeLabels;
    }
}

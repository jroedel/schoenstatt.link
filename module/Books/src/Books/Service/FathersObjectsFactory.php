<?php
namespace Books\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory responsible of retrieving an array containing the Books configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class FathersObjectsFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $fathers = $serviceLocator->get('Schoenstatt\FathersValueOptions');

        $objects = [];
        foreach ($fathers as $personId => $name) {
            $objects[] = [
                'id' => $personId,
                'name' => $name,
            ];
        }
        return $objects;
    }
}

<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class FathersValueOptionsService implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /** @var \Schoenstatt\Service\PatresGateway $gateway */
        $gateway = $serviceLocator->get('PatresGateway');
        $persons = $gateway->getPersonList();
        return $persons;
    }
}

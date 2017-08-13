<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class PatresGatewayFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /** @var \Schoenstatt\Form\PersonForm $personForm */
        $personForm = $serviceLocator->get('Schoenstatt\Form\PersonForm');
        $config = $serviceLocator->get('Schoenstatt\Config');
        $table = $serviceLocator->get('Schoenstatt\Model\SchoenstattTable');

        $patresGateway = new PatresGateway();

        $inputFilter = clone $personForm->getInputFilter();
        $inputFilter->remove('security');
        $patresGateway->setPersonInputFilter($inputFilter);
        $patresGateway->setSchoenstattConfig($config);
        $patresGateway->setSchoenstattTable($table);
        return $patresGateway;
    }
}

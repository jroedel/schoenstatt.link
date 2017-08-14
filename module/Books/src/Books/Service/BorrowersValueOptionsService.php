<?php
namespace Books\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Schoenstatt\Model\SchoenstattTable;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class BorrowersValueOptionsService implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /** @var SchoenstattTable $table */
        $table = $serviceLocator->get('Schoenstatt\Model\SchoenstattTable');
        $persons = $table->getUnlinkedPersons();
        $valueOptions = [];
        foreach ($persons as $personId => $person) {
            if ($person['isBorrower']) {
                $valueOptions[$personId] = $person['fullName'];
            }
        }
        return $valueOptions;
    }
}

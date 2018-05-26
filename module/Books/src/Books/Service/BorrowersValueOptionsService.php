<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Schoenstatt\Model\SchoenstattTable;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class BorrowersValueOptionsService implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var SchoenstattTable $table */
        $table = $container->get('Schoenstatt\Model\SchoenstattTable');
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

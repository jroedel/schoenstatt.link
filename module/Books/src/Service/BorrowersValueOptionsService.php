<?php
namespace Books\Service;

use Psr\Container\ContainerInterface;
use Schoenstatt\Model\SchoenstattTable;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class BorrowersValueOptionsService
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var SchoenstattTable $table */
        $table = $container->get(SchoenstattTable::class);
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

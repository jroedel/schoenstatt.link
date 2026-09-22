<?php
namespace Books\Service;

use Psr\Container\ContainerInterface;

/**
 * Factory responsible of retrieving an array containing the Books configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class FathersObjectsFactory
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $fathers = $container->get('Schoenstatt\FathersValueOptions');

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

<?php
namespace Books\Service;

use Psr\Container\ContainerInterface;

/**
 * Factory responsible of retrieving an array containing the Books configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class ConfigServiceFactory
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('Config');

        if (isset($config['books'])) {
            return $config['books'];
        }
        return [];
    }
}

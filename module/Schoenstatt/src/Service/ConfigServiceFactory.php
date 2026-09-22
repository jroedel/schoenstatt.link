<?php
namespace Schoenstatt\Service;

use Psr\Container\ContainerInterface;

class ConfigServiceFactory
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('Config');

        if (isset($config['schoenstatt'])) {
            return $config['schoenstatt'];
        }
        return [];
    }
}

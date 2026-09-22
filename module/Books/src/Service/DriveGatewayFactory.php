<?php
namespace Books\Service;

use Psr\Container\ContainerInterface;

/**
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class DriveGatewayFactory
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('config');
        $cache = $container->get('Books\Cache');

        $gateway = new DriveGateway();
        $gateway->setConfig($config);
        $gateway->setCache($cache);
        return $gateway;
    }
}

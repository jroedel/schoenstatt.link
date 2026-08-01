<?php
namespace Schoenstatt\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class FathersValueOptionsService implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var \Schoenstatt\Service\PatresGateway $gateway */
        $gateway = $container->get(PatresGateway::class);
        try {
            $persons = $gateway->getPersonList();
        } catch (\Exception $e) {
            $persons = []; //fail silently
        }
        return $persons;
    }
}

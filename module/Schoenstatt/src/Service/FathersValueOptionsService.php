<?php
namespace Schoenstatt\Service;

use Psr\Container\ContainerInterface;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class FathersValueOptionsService
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
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

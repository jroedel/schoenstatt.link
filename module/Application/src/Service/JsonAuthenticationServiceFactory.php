<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Authentication\Storage\NonPersistent;
use Application\Authentication\Adapter\JsonPost;

class JsonAuthenticationServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        return new \Laminas\Authentication\AuthenticationService(
            $serviceLocator->get(NonPersistent::class),
            $serviceLocator->get(JsonPost::class)
        );
    }

    /**
     * Create service
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return $this->__invoke($serviceLocator, null);
    }
}

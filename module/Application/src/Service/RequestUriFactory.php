<?php

namespace Application\Service;

use Application\View\Helper\RequestUri;
use Laminas\Http\Request;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class RequestUriFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var Request $request */
        $request = $container->get('Request');
        return new RequestUri($request);
    }
}

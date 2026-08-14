<?php

declare(strict_types=1);

namespace RestApi\Controller;

use Psr\Container\ContainerInterface;

use function is_array;

/**
 * Hands RouteNotFoundController the ApiRequest.responseFormat config.
 *
 * The controller was invokable until /api/v1 was retired: it inherited the config
 * from ApiController's dispatch listener, and that class is gone. Reading it here
 * keeps config/autoload/restapi.global.php's responseFormat block load-bearing —
 * without this the block would be dead config that still looks live, which is the
 * failure mode this whole retirement is trying to reduce.
 */
class RouteNotFoundControllerFactory
{
    public function __invoke(ContainerInterface $container): RouteNotFoundController
    {
        /** @var array<string, mixed> $config */
        $config = $container->get('Config');
        $format = $config['ApiRequest']['responseFormat'] ?? [];

        return new RouteNotFoundController(is_array($format) ? $format : []);
    }
}

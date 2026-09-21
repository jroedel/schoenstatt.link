<?php

declare(strict_types=1);

namespace Application\Service;

use App\Session\HttpSession;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function is_array;

/**
 * The request's session, built from the merged `session_config` block.
 *
 * One per container, which is what makes the Symfony kernel and the laminas container
 * share a single session and therefore a single identity — `App\JUser\Host\Session` and
 * `App\Http\SessionListener` both resolve this id.
 *
 * Replaced `Laminas\Session\Service\SessionManagerFactory` and its
 * `SessionConfigFactory` on 2026-09-21, which JUser's module config registered. The
 * `session_config` key keeps its name and its values; what changed is that applying them
 * can no longer throw.
 */
class HttpSessionFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): HttpSession
    {
        /** @var array<string, mixed> $config */
        $config  = $container->get('Config');
        $session = $config['session_config'] ?? [];

        return new HttpSession(is_array($session) ? $session : []);
    }
}

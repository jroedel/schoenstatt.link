<?php

declare(strict_types=1);

namespace Application\Service;

use App\JUser\Host\Session as JUserSession;
use App\Session\HttpSession;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * The session JUser reads and writes, as its own host contract rather than a laminas
 * Container.
 *
 * Registered in the laminas container because that is where JUser's own
 * `Host\IdentityInterface` factory looks for it — and because one registration is what
 * makes the Symfony kernel and the laminas container share a single adapter. Two would
 * each memoize their own identity, and a magic-link redemption through one would leave
 * the other anonymous.
 *
 * A named factory rather than the closure it was until 2026-09-21: a closure in a module
 * config can only be cached by an exporter that can write one back out, which is the only
 * thing `brick/varexporter` — and with it `laminas/laminas-modulemanager` — was doing for
 * this application. {@see \SchoenstattTest\Integration\MergedConfigIsPlainDataTest}
 */
class JUserSessionFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): JUserSession
    {
        /** @var HttpSession $session */
        $session = $container->get(HttpSession::class);

        return new JUserSession($session);
    }
}

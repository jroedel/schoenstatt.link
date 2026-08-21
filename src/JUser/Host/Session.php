<?php

declare(strict_types=1);

namespace App\JUser\Host;

use App\Laminas\ServiceBridge;
use JUser\Host\SessionInterface;
use Laminas\Session\Container;
use Laminas\Session\ManagerInterface;
use Laminas\Session\SessionManager;

/**
 * `JUser\Host\SessionInterface` over laminas-session.
 *
 * The session is already started by the time any of this runs — `App\Http\SessionListener`
 * does it — so a `Laminas\Session\Container` built here reads and writes the same session
 * laminas-mvc would. That is what lets a POST served by one front controller hand a value to
 * a GET served by the other, which matters for the release in which both are live.
 *
 * ## The concrete SessionManager, not the interface, and the narrowing is load-bearing
 *
 * `ManagerInterface::regenerateId()` declares **no parameters**, so `regenerateId(true)` —
 * delete the old session server-side, which is the whole point at a privilege change — does
 * not type-check against it. `Laminas\Session\Service\SessionManagerFactory` answers that
 * service id with a `SessionManager`, so this is a narrowing of a declared type to the real
 * one rather than a cast and rather than a hope.
 *
 * ## Containers are cached per namespace
 *
 * Not for speed: `new Container($ns, $manager)` on every read is fine. It is that a
 * container is an `ArrayObject` over the session storage and holding one per namespace keeps
 * "two writes to one namespace" obviously a single object's business.
 */
final class Session implements SessionInterface
{
    /** @var array<string, Container<string, mixed>> */
    private array $containers = [];

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    public function get(string $namespace, string $key): mixed
    {
        //array access, not `->$key`: Container resolves properties through ArrayObject's
        //magic, which PHPStan level 8 reads as an undefined property
        return $this->container($namespace)[$key] ?? null;
    }

    public function set(string $namespace, string $key, mixed $value): void
    {
        $this->container($namespace)[$key] = $value;
    }

    public function remove(string $namespace, string $key): void
    {
        $container = $this->container($namespace);
        unset($container[$key]);
    }

    public function regenerateId(bool $destroyOld = true): void
    {
        $this->manager()->regenerateId($destroyOld);
    }

    public function forgetMe(): void
    {
        $this->manager()->forgetMe();
    }

    /** @return Container<string, mixed> */
    private function container(string $namespace): Container
    {
        return $this->containers[$namespace] ??= new Container($namespace, $this->manager());
    }

    private function manager(): SessionManager
    {
        /** @var SessionManager $manager */
        $manager = $this->laminas->get(ManagerInterface::class);

        return $manager;
    }
}

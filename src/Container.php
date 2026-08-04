<?php

declare(strict_types=1);

namespace App;

use Psr\Container\ContainerInterface;

use function array_keys;

/**
 * The Symfony kernel's own container: an array of lazy factories, nothing more.
 *
 * Deliberately not the application's Laminas ServiceManager. The one service
 * that must exist before anything Laminas does is LegacyBridge — it is what
 * *builds* the Laminas application — so resolving it through that container
 * would be circular. Keeping a separate container also means a Symfony-served
 * route costs no module loading and no config merge: the whole point of moving
 * routes across.
 *
 * It is a PSR-11 container because ContainerControllerResolver only asks for
 * that much, so when symfony/dependency-injection eventually arrives this class
 * is deleted and the resolver keeps working untouched.
 */
final class Container implements ContainerInterface
{
    /** @var array<string, object> resolved singletons */
    private array $instances = [];

    /**
     * @param array<string, callable(self): object> $factories service id => factory
     */
    public function __construct(private readonly array $factories)
    {
    }

    public function get(string $id): object
    {
        if (! isset($this->factories[$id])) {
            throw new ServiceNotFound($id, array_keys($this->factories));
        }

        return $this->instances[$id] ??= ($this->factories[$id])($this);
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}

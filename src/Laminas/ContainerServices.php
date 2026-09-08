<?php

declare(strict_types=1);

namespace App\Laminas;

use Psr\Container\ContainerInterface;

/**
 * {@see LaminasServices} over an existing PSR container — the laminas ServiceManager itself.
 *
 * Used where the code already runs *inside* that container and must not build a second one:
 * a view-helper factory registered in laminas config receives the app ServiceManager as its
 * argument (see the note in App\Laminas\ViewHelpers about factory identity), so the
 * `isAllowed` helper is assembled with `new ContainerServices($container)` rather than a
 * fresh `ServiceBridge`.
 */
final class ContainerServices implements LaminasServices
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        /** @var array<string, mixed> $config */
        $config = $this->container->get('config');

        return $config;
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    public function get(string $id): mixed
    {
        return $this->container->get($id);
    }
}

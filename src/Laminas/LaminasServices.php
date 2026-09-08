<?php

declare(strict_types=1);

namespace App\Laminas;

/**
 * The narrow slice of a laminas service container the `App\Acl` engine needs: the merged
 * config, and service resolution. {@see ServiceBridge} is the usual implementation (it
 * builds a ServiceManager the way `bin/console` does), but the ACL is also assembled from
 * *inside* a laminas view-helper factory, where the container already exists and building a
 * second ServiceManager would be wrong — {@see ContainerServices} adapts that container to
 * this interface.
 *
 * Depending on this rather than the concrete `ServiceBridge` is also a step out of
 * laminas-mvc: `App\Acl` names none of laminas here, only these three methods.
 */
interface LaminasServices
{
    /**
     * The merged application config.
     *
     * @return array<string, mixed>
     */
    public function config(): array;

    public function has(string $id): bool;

    public function get(string $id): mixed;
}

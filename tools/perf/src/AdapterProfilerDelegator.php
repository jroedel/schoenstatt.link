<?php

declare(strict_types=1);

namespace SchoenstattPerf;

use SionModel\Db\Connection;
use Psr\Container\ContainerInterface;

/**
 * Attaches {@see DbProfiler} to a database adapter as it leaves the container.
 *
 * A delegator rather than a replacement factory, so the application's own adapter
 * configuration is untouched and the harness cannot change what it is measuring.
 */
final class AdapterProfilerDelegator
{
    /**
     * @param array<string, mixed>|null $options
     */
    public function __invoke(
        ContainerInterface $container,
        string $name,
        callable $callback,
        ?array $options = null
    ): mixed {
        Collector::boot();

        $adapter = $callback();
        if ($adapter instanceof Connection) {
            $adapter->watch(new DbProfiler());
        }

        return $adapter;
    }
}

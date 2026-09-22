<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/**
 * The container knows the name but the factory could not produce the service.
 *
 * `Laminas\ServiceManager\Exception\ServiceNotCreatedException` under a name of ours since
 * 2026-09-21. Two factories throw it deliberately — `Application\Service\DbAdapterServiceFactory`
 * when the database configuration is unusable — and the container itself wraps anything a
 * factory throws that is not already a container exception, so the name of the service
 * that failed reaches the reader.
 */
final class ServiceNotCreated extends RuntimeException implements ContainerExceptionInterface
{
}

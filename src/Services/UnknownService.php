<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * The container has no name like that.
 *
 * Distinct from {@see \App\ServiceNotFound}, which the kernel's own five-service container
 * throws and whose message lists every id it knows — a helpful answer for a typo in a route's
 * `_controller`, and an unreadable one for the 103 names in here.
 */
final class UnknownService extends RuntimeException implements NotFoundExceptionInterface
{
}

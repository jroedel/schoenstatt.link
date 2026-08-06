<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;
use Psr\Container\NotFoundExceptionInterface;

use function implode;
use function sprintf;

/**
 * Thrown when Container is asked for a service it has no factory for.
 *
 * Names the known ids in the message: with a hand-written container the cause
 * is almost always a typo in a route's _controller, and the list turns a
 * "not found" into an answer.
 */
final class ServiceNotFound extends InvalidArgumentException implements NotFoundExceptionInterface
{
    /** @param string[] $known ids the container does have */
    public function __construct(string $id, array $known)
    {
        parent::__construct(sprintf(
            'The kernel container has no service "%s". It knows: %s.',
            $id,
            $known === [] ? '(nothing)' : implode(', ', $known)
        ));
    }
}

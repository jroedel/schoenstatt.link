<?php

declare(strict_types=1);

namespace App\JUser;

use RuntimeException;

use function sprintf;

/**
 * `juser.person_provider` names a service that is not a
 * `JUser\Model\PersonValueOptionsProviderInterface`.
 *
 * Its own class rather than a bare `InvalidArgumentException` for the reason
 * `App\Authorization\UndeclaredRouteAccess` has one: this is a configuration mistake with
 * exactly one cause, and a named type is what lets a test assert on it without matching a
 * message.
 */
final class MisconfiguredPersonProvider extends RuntimeException
{
    public function __construct(string $serviceId)
    {
        parent::__construct(sprintf(
            "`juser.person_provider` names '%s', which does not implement"
            . ' JUser\Model\PersonValueOptionsProviderInterface.',
            $serviceId
        ));
    }
}

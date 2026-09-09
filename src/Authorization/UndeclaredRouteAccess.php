<?php

declare(strict_types=1);

namespace App\Authorization;

use LogicException;

use function sprintf;

/**
 * A Symfony-served route reached the authorization check without declaring what
 * governs it.
 *
 * This is a programming mistake and is raised as one. The alternative — treat
 * silence as "deny" — would be safe for visitors and terrible for the migration:
 * a route added without a declaration would answer 403 to everyone including its
 * author, who would then read it as an ACL problem and go looking in
 * `acl.global.php`. Naming the route in the message puts the fix in the first line
 * of the stack trace.
 *
 * Treating silence as "allow" is of course the failure this whole class of code
 * exists to prevent, and is what a Symfony-served guarded route did before the
 * bridge existed.
 *
 * It should never reach production, and two things beyond this class make sure of
 * it: `test/Integration/SymfonyRouteAuthorizationTest` walks every route in the
 * collection and fails on an undeclared one, and `tools/acl-table.php` reports it
 * as a warning in `docs/acl-baseline.json`.
 */
final class UndeclaredRouteAccess extends LogicException
{
    public static function forRoute(string $route, string $path): self
    {
        return new self(sprintf(
            'The Symfony route "%s" (%s) declares no authorization. Every route in '
            . 'config/symfony/routes.php must pass an App\Authorization\RouteAccess: either '
            . 'RouteAccess::guardedBy(\'route/<laminas-route-name>\') to check the same ACL resource the '
            . 'laminas guard uses, or RouteAccess::openToEveryone(\'<why>\') to state deliberately that '
            . 'anyone may reach it. Defaulting either way would be wrong — see docs/laminas-exit.md.',
            $route,
            $path
        ));
    }
}

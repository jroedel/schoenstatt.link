<?php

declare(strict_types=1);

namespace App\Acl;

/**
 * The drop-in replacement for `BjyAuthorize\View\Helper\IsAllowed`, returned by
 * `App\Laminas\ViewHelpers::isAllowed()`.
 *
 * Every Twig `is_allowed()` call and every ported controller's private `isAllowed()` wrapper
 * reaches authorization through that one accessor and invokes what it returns as
 * `->__invoke($resource, $privilege)`. Swapping the accessor's return from BjyAuthorize's
 * helper to this moves all of them onto {@see App\Acl\Authorizer} at once, with an identical
 * call signature and — proven by test/Integration/AclParityTest — an identical decision.
 *
 * It is the ambient question ("may the *current visitor* …?"), so it delegates straight to
 * {@see AclProvider::isAllowed()}, which resolves the visitor's roles once per request.
 */
final class IsAllowed
{
    public function __construct(private readonly AclProvider $acl)
    {
    }

    public function __invoke(?string $resource = null, ?string $privilege = null): bool
    {
        return $this->acl->isAllowed($resource, $privilege);
    }
}

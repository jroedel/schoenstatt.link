<?php

declare(strict_types=1);

namespace App\Acl;

use App\Laminas\LaminasServices;

/**
 * The per-request seam every live authorization consumer shares, and the replacement for
 * what `BjyAuthorize\Service\Authorize` was to the Symfony front controller.
 *
 * It does the two things `Authorize::load()` did, and memoizes each the same way — **once
 * per request**:
 *
 *  - **Assembles the model.** `authorizer()` builds {@see AclData} through {@see AclAssembler}
 *    and wraps it in an {@see Authorizer} once. Measured ~0.45 ms warm — as cheap as reading
 *    BjyAuthorize's *cached* `Acl` back was (0.37 ms), because `AclData` is plain arrays, not
 *    a `Laminas\Permissions\Acl` object graph. So this needs no cross-request APCu cache the
 *    way BjyAuthorize did; per-request memoization is enough, which is why the ACL-cache
 *    invalidation this once needed (App\Acl\AclCacheInvalidator) is gone.
 *
 *  - **Resolves the current identity's roles.** `currentRoles()` delegates to
 *    {@see IdentityRoles}, which reproduces what BjyAuthorize's identity provider returned
 *    (the account's role *names*, or `[default_role]` when anonymous) without loading it, and
 *    memoizes the answer. BjyAuthorize baked those into a synthetic `bjyauthorize-identity`
 *    role at the first `isAllowed()` call of the request; this resolves them at the first
 *    `currentRoles()` call, which is the same first-touch, so it reproduces that behaviour
 *    including the one place it bites (see `App\JUser\Host\Access`, which is why
 *    `userMayReachRoute()` takes an explicit user rather than trusting the ambient identity
 *    right after sign-in).
 *
 * `isAllowed()` is the ambient question — "may the current visitor …?" — that the Twig
 * `is_allowed()` function, `visitorMayReachRoute()`, and every ported controller's private
 * `isAllowed()` wrapper ask. Callers that must reason about a *specific* role set instead
 * (`userMayReachRoute()` with an explicit user, the sitemap's guest-only check) take
 * {@see authorizer()} and pass the roles themselves.
 *
 * Self-contained on purpose: it takes only the `ServiceBridge` every consumer already holds,
 * so wiring it needed no constructor change to `RouteGuard`, `ViewHelpers`, `Access` or
 * `GuestAccess` — each builds its own, memoized behind that consumer's own per-request reuse.
 */
final class AclProvider
{
    private ?Authorizer $authorizer = null;

    /** @var list<string>|null */
    private ?array $currentRoles = null;

    public function __construct(private readonly LaminasServices $laminas)
    {
    }

    /** The assembled engine, built once per request. */
    public function authorizer(): Authorizer
    {
        return $this->authorizer ??= new Authorizer((new AclAssembler($this->laminas))->assemble());
    }

    /**
     * The current visitor's role names — the account's roles, or the default role when
     * anonymous — resolved once per request. See the class docblock for why first-touch
     * timing matches BjyAuthorize.
     *
     * @return list<string>
     */
    public function currentRoles(): array
    {
        return $this->currentRoles ??= $this->resolveCurrentRoles();
    }

    /** May the current visitor do `$privilege` on `$resource`? Null privilege = the resource generally. */
    public function isAllowed(?string $resource, ?string $privilege = null): bool
    {
        if (null === $resource || '' === $resource) {
            return false;
        }

        return $this->authorizer()->isAllowedForRoles($this->currentRoles(), $resource, $privilege);
    }

    /** @return list<string> */
    private function resolveCurrentRoles(): array
    {
        return (new IdentityRoles($this->laminas))->current();
    }
}

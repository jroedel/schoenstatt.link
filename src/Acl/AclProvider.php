<?php

declare(strict_types=1);

namespace App\Acl;

use App\Laminas\ServiceBridge;

use function is_array;
use function is_object;
use function is_string;
use function method_exists;

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
 *    way BjyAuthorize did; per-request memoization is enough. (`App\Acl\AclCacheInvalidator`
 *    still exists for BjyAuthorize's own cache while that package is installed.)
 *
 *  - **Resolves the current identity's roles.** `currentRoles()` asks the same identity
 *    provider BjyAuthorize asked — `getIdentityRoles()`, which returns the account's role
 *    *names* (or `[default_role]` when anonymous) — and memoizes the answer. BjyAuthorize
 *    baked those into a synthetic `bjyauthorize-identity` role at the first `isAllowed()`
 *    call of the request; this resolves them at the first `currentRoles()` call, which is the
 *    same first-touch, so it reproduces that behaviour including the one place it bites (see
 *    `App\JUser\Host\Access`, which is why `userMayReachRoute()` takes an explicit user
 *    rather than trusting the ambient identity right after sign-in).
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
    /** The registered identity-provider service id — BjyAuthorize's, JUser's implementation. */
    private const IDENTITY_PROVIDER = 'BjyAuthorize\Provider\Identity\ProviderInterface';

    private ?Authorizer $authorizer = null;

    /** @var list<string>|null */
    private ?array $currentRoles = null;

    public function __construct(private readonly ServiceBridge $laminas)
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
        if (! $this->laminas->has(self::IDENTITY_PROVIDER)) {
            return [];
        }
        $provider = $this->laminas->get(self::IDENTITY_PROVIDER);
        if (! is_object($provider) || ! method_exists($provider, 'getIdentityRoles')) {
            return [];
        }

        /** @var mixed $roles */
        $roles = $provider->getIdentityRoles();
        if (! is_array($roles)) {
            return [];
        }

        $names = [];
        foreach ($roles as $role) {
            if (is_string($role) && '' !== $role) {
                $names[] = $role;
            }
        }

        return $names;
    }
}

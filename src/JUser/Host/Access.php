<?php

declare(strict_types=1);

namespace App\JUser\Host;

use App\Laminas\ServiceBridge;
use BjyAuthorize\Service\Authorize;
use JUser\Host\AccessInterface;
use JUser\Model\User;
use JUser\Model\UserTable;
use Laminas\Permissions\Acl\Acl;
use Throwable;

use function is_array;
use function is_string;

/**
 * `JUser\Host\AccessInterface` over BjyAuthorize and the ACL both front controllers build.
 *
 * The two methods reach authorization by two different routes, and that is not an
 * inconsistency — it is the whole reason the interface has two.
 *
 * ## `visitorMayReachRoute()` is the ordinary question
 *
 * `Authorize::isAllowed('route/<name>')`, which is exactly what the `is_allowed()` Twig
 * function and every `.phtml` on the site ask, so a link JUser draws appears under precisely
 * the same conditions as the same link drawn anywhere else. `isAllowed()` also swallows the
 * registry's `InvalidArgumentException` for an unknown resource and answers false, which is
 * the right direction for a typo.
 *
 * ## `userMayReachRoute()` cannot use it
 *
 * `Authorize::load()` runs once per request and bakes the identity's roles into the ACL as it
 * goes — and on the request that asks this, the route guard already triggered that load while
 * the visitor was still anonymous. So `isAllowed()` answers for `guest` no matter who just
 * signed in, and the answer is "no" for every guarded page: the visitor lands on the home
 * page with no explanation. Measured; it is why this method exists at all.
 *
 * So it asks about the account's own roles against the ACL directly. Three details, all
 * transcribed from the class this replaces rather than rediscovered, and each of which
 * silently produces an empty role list — which the caller reads as "refuse the destination":
 *
 * **A rule naming the null role means genuinely public**, and is asked first so a public
 * route does not depend on the account holding a role the ACL knows.
 *
 * **`linkUser()`, not `getUser()`.** The latter serves rows out of the cached
 * `all-linked-users` map and, on a miss, falls through to `queryObjects('user', …)`, which
 * does not link roles at all. A freshly registered account is exactly what a first sign-in
 * produces and exactly what is not in that cache.
 *
 * **The link row's `name`, not `rolesList`.** Despite the name, `rolesList` is
 * `array_keys($user['roles'])` — numeric `user_role.id` values, `[7, 16, 23, 40]` for an
 * account holding the four default roles. The ACL is keyed on names.
 *
 * ## Default deny
 *
 * An unguarded route is reachable by nobody, which is how `BjyAuthorize\Guard\Route` treats a
 * missing entry, and an ACL that throws is a route nothing says yes about. Both answer false.
 */
final class Access implements AccessInterface
{
    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    public function visitorMayReachRoute(string $route): bool
    {
        return (bool) $this->authorize()->isAllowed('route/' . $route);
    }

    public function userMayReachRoute(User $user, string $route): bool
    {
        $acl      = $this->authorize()->getAcl();
        $resource = 'route/' . $route;

        if (! $acl->hasResource($resource)) {
            return false;
        }

        if ($this->allows($acl, null, $resource)) {
            return true;
        }

        foreach ($this->aclRoles($user) as $role) {
            if ($acl->hasRole($role) && $this->allows($acl, $role, $resource)) {
                return true;
            }
        }

        return false;
    }

    /**
     * isAllowed() without the exceptions — laminas throws for an unknown role or resource,
     * and either one means "no rule says yes", which is the same answer as false.
     */
    private function allows(Acl $acl, ?string $role, string $resource): bool
    {
        try {
            return (bool) $acl->isAllowed($role, $resource);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Every role *name* the ACL might hold a rule for, for this account. See the class
     * docblock for the two traps in here.
     *
     * @return list<string>
     */
    private function aclRoles(User $user): array
    {
        $roles = [];
        $row   = ['userId' => (int) $user->getId(), 'roles' => []];
        $this->userTable()->linkUser($row);

        /** @var mixed $links */
        $links = $row['roles'] ?? null;
        if (is_array($links)) {
            foreach ($links as $link) {
                /** @var mixed $name */
                $name = is_array($link) ? ($link['name'] ?? null) : null;
                if (is_string($name) && '' !== $name) {
                    $roles[] = $name;
                }
            }
        }

        //the per-user role JUser\Provider\Role\UserIdRoles contributes
        $roles[] = 'user_' . (int) $user->getId();

        return $roles;
    }

    private function authorize(): Authorize
    {
        /** @var Authorize $authorize */
        $authorize = $this->laminas->get(Authorize::class);

        return $authorize;
    }

    private function userTable(): UserTable
    {
        /** @var UserTable $table */
        $table = $this->laminas->get(UserTable::class);

        return $table;
    }
}

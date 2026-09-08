<?php

declare(strict_types=1);

namespace App\JUser\Host;

use App\Acl\AclProvider;
use App\Laminas\ServiceBridge;
use JUser\Host\AccessInterface;
use JUser\Model\User;
use JUser\Model\UserTable;

use function is_array;
use function is_string;

/**
 * `JUser\Host\AccessInterface` over `App\Acl\Authorizer` (via `App\Acl\AclProvider`), the
 * engine that replaced BjyAuthorize.
 *
 * The two methods reach authorization by two different routes, and that is not an
 * inconsistency — it is the whole reason the interface has two.
 *
 * ## `visitorMayReachRoute()` is the ordinary question
 *
 * `AclProvider::isAllowed('route/<name>')` — the ambient check, exactly what the
 * `is_allowed()` Twig function asks, so a link JUser draws appears under precisely the same
 * conditions as the same link drawn anywhere else. An unknown resource answers false, the
 * right direction for a typo.
 *
 * ## `userMayReachRoute()` cannot use it
 *
 * `AclProvider` resolves the current identity's roles once per request, at the first
 * authorization query — and on the request that asks this, the route guard already triggered
 * that resolution while the visitor was still anonymous (the same first-touch BjyAuthorize
 * baked at). So the ambient check answers for `guest` no matter who just signed in, and the
 * answer is "no" for every guarded page: the visitor lands on the home page with no
 * explanation. Measured against the old engine; it is why this method exists, and why it
 * takes an explicit user and asks the engine about *that* account's roles instead.
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
    private ?AclProvider $acl = null;

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    public function visitorMayReachRoute(string $route): bool
    {
        return $this->acl()->isAllowed('route/' . $route);
    }

    public function userMayReachRoute(User $user, string $route): bool
    {
        $authorizer = $this->acl()->authorizer();
        $resource   = 'route/' . $route;

        // An undeclared route is reachable by nobody — the same default deny
        // BjyAuthorize\Guard\Route applies to a missing entry.
        if (! $authorizer->hasResource($resource)) {
            return false;
        }

        // The account's own role names, not the ambient identity. A rule naming the null
        // role (a genuinely public route) is honoured by the engine's "all roles" handling
        // even for an account holding no role the ACL knows.
        return $authorizer->isAllowedForRoles($this->aclRoles($user), $resource);
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

        return $roles;
    }

    private function acl(): AclProvider
    {
        return $this->acl ??= new AclProvider($this->laminas);
    }

    private function userTable(): UserTable
    {
        /** @var UserTable $table */
        $table = $this->laminas->get(UserTable::class);

        return $table;
    }
}

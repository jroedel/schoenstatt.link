<?php

declare(strict_types=1);

namespace App\Acl;

use App\Laminas\LaminasServices;
use JUser\Host\IdentityInterface;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\ResultSetInterface;

use function is_array;
use function is_object;
use function is_string;

/**
 * The current visitor's role names, resolved app-side — the replacement for asking
 * BjyAuthorize's identity provider (`ZfcUserZendDbPlusSelfAsRole`, once in JUser).
 *
 * That class implemented a bjy interface, so keeping it meant keeping the bjy package.
 * Reproducing its two-line logic here is what let this application drop `bjy-authorize`
 * entirely; the class itself was then deleted from JUser (2026-09-09).
 *
 * The behaviour is transcribed from `getIdentityRoles()`, faithfully, because a divergence
 * would silently change who can reach what:
 *
 *   - **No identity → `[default_role]`** (the configured `bjyauthorize.default_role`, i.e.
 *     `guest`). This is the anonymous crawler and the signed-out visitor.
 *   - **An identity → the account's role *names*** from `user_role_linker` joined to
 *     `user_role`, exactly the query the provider ran (`user_role.role_id` is the name; the
 *     numeric key is `user_role.id`). An account with no role links yields `[]`, same as the
 *     provider — the caller then relies on default-deny plus any "all roles" rule.
 *
 * `test/Integration/AclIdentityRolesParityTest` pins the query against the provider's own
 * SQL for every account, and the anonymous answer against the configured default role.
 */
final class IdentityRoles
{
    private const DEFAULT_ROLE_FALLBACK = 'guest';

    public function __construct(private readonly LaminasServices $laminas)
    {
    }

    /** @return list<string> */
    public function current(): array
    {
        $user = $this->identity()?->current();

        return null === $user
            ? [$this->defaultRole()]
            : $this->rolesForUser((int) $user->getId());
    }

    /**
     * The role names linked to one account. Exposed for the parity test, which compares it to
     * the provider's own SQL for every account.
     *
     * @return list<string>
     */
    public function rolesForUser(int $userId): array
    {
        $db = $this->laminas->get(Adapter::class);
        if (! $db instanceof Adapter) {
            return [];
        }

        $result = $db->query(
            'SELECT ur.role_id AS role
               FROM user_role_linker l
               INNER JOIN user_role ur ON ur.id = l.role_id
              WHERE l.user_id = ?',
            [$userId]
        );

        $roles = [];
        if ($result instanceof ResultSetInterface) {
            foreach ($result as $row) {
                $name = is_array($row) ? ($row['role'] ?? null) : (is_object($row) ? ($row->role ?? null) : null);
                if (is_string($name) && '' !== $name) {
                    $roles[] = $name;
                }
            }
        }

        return $roles;
    }

    /** The configured anonymous role — `bjyauthorize.default_role`, else `guest`. */
    public function defaultRole(): string
    {
        /** @var mixed $bjy */
        $bjy = $this->laminas->config()['bjyauthorize'] ?? null;
        /** @var mixed $role */
        $role = is_array($bjy) ? ($bjy['default_role'] ?? null) : null;

        return is_string($role) && '' !== $role ? $role : self::DEFAULT_ROLE_FALLBACK;
    }

    /**
     * Null when the host has registered no identity at all, which is a console process
     * rather than an anonymous visitor. Both answer with the default role, and the caller
     * cannot tell them apart because nothing downstream should.
     */
    private function identity(): ?IdentityInterface
    {
        if (! $this->laminas->has(IdentityInterface::class)) {
            return null;
        }
        $identity = $this->laminas->get(IdentityInterface::class);

        return $identity instanceof IdentityInterface ? $identity : null;
    }
}

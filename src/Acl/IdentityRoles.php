<?php

declare(strict_types=1);

namespace App\Acl;

use App\Laminas\LaminasServices;
use Laminas\Authentication\AuthenticationService;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\ResultSetInterface;

use function is_array;
use function is_object;
use function is_string;
use function method_exists;

/**
 * The current visitor's role names, resolved app-side — the replacement for asking
 * BjyAuthorize's identity provider (`JUser\Bridge\Laminas\ZfcUserZendDbPlusSelfAsRole`).
 *
 * That class `implements BjyAuthorize\Provider\Identity\ProviderInterface`, so merely
 * loading it requires the bjy package. Reproducing its two-line logic here instead is what
 * lets this application stop resolving any `BjyAuthorize\*` service — and therefore drop the
 * package — while JUser keeps its bridge for patres, which is still on laminas.
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
    private const AUTH_SERVICE          = 'JUser\AuthService';

    public function __construct(private readonly LaminasServices $laminas)
    {
    }

    /** @return list<string> */
    public function current(): array
    {
        $auth = $this->authService();
        if (! $auth instanceof AuthenticationService || ! $auth->hasIdentity()) {
            return [$this->defaultRole()];
        }

        $identity = $auth->getIdentity();
        if (! is_object($identity) || ! method_exists($identity, 'getId')) {
            return [$this->defaultRole()];
        }

        return $this->rolesForUser((int) $identity->getId());
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

    private function authService(): ?AuthenticationService
    {
        if (! $this->laminas->has(self::AUTH_SERVICE)) {
            return null;
        }
        $auth = $this->laminas->get(self::AUTH_SERVICE);

        return $auth instanceof AuthenticationService ? $auth : null;
    }
}

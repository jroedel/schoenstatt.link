<?php

declare(strict_types=1);

namespace App\Acl;

use App\Laminas\LaminasServices;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\ResultSetInterface;

use function is_array;
use function is_string;

/**
 * Assembles {@see AclData} from the same sources BjyAuthorize reads, so the two produce
 * identical decisions — which test/Integration/AclParityTest proves before anything is cut
 * over. It reaches laminas-db and the merged config (neither is laminas-*mvc*), and calls
 * the application's own dynamic rule providers directly.
 *
 * The three rule sources, all normalised to the one shape `allow(roles, resource, privileges)`:
 *
 *  1. **Static config** — `bjyauthorize.rule_providers['BjyAuthorize\Provider\Rule\Config'].allow`,
 *     entries `[[roles], resource, privilege?]` where the privilege may be absent (all),
 *     a string, or a list.
 *  2. **Route guards** — `bjyauthorize.guards['BjyAuthorize\Guard\Route']`, entries
 *     `{route, roles}`, each an all-privileges grant on the resource `route/<route>`. A
 *     route declared twice takes its **last** entry, which is how BjyAuthorize's guard
 *     behaves (it assigns, it does not merge — see docs/acl-rules.md).
 *  3. **Dynamic providers** — every other key under `rule_providers` is a service id
 *     implementing `getRules()` (today `Books\Model\LibraryTable`, which emits the
 *     per-library `library_<id>` rules). Called the same way BjyAuthorize calls them.
 *
 * Roles and their inheritance come from `user_role` (`role_id`, `parent_id`): one parent
 * per role, the chain giving the ancestor closure.
 *
 * @phpstan-import-type RulesByResource from AclData
 */
final class AclAssembler
{
    private const CONFIG_RULE_PROVIDER = 'BjyAuthorize\Provider\Rule\Config';
    private const ROUTE_GUARD          = 'BjyAuthorize\Guard\Route';

    public function __construct(private readonly LaminasServices $laminas)
    {
    }

    public function assemble(): AclData
    {
        return new AclData($this->roleHierarchy(), $this->rulesByResource());
    }

    /** @return array<string, string> role => direct parent role */
    private function roleHierarchy(): array
    {
        /** @var Adapter $db */
        $db = $this->laminas->get(Adapter::class);
        $rows = $db->query(
            'SELECT r.role_id AS role, p.role_id AS parent
               FROM user_role r LEFT JOIN user_role p ON p.id = r.parent_id',
            Adapter::QUERY_MODE_EXECUTE
        );

        $hierarchy = [];
        if (! $rows instanceof ResultSetInterface) {
            return $hierarchy;
        }
        foreach ($rows as $row) {
            $role = is_string($row['role'] ?? null) ? $row['role'] : null;
            if (null === $role) {
                continue;
            }
            $parent = is_string($row['parent'] ?? null) ? $row['parent'] : null;
            if (null !== $parent) {
                $hierarchy[$role] = $parent;
            }
        }

        return $hierarchy;
    }

    /** @return RulesByResource */
    private function rulesByResource(): array
    {
        /** @var array<string, mixed> $bjy */
        $bjy = $this->laminas->config()['bjyauthorize'] ?? [];

        $byResource = [];

        // 1. static config rules + 3. dynamic provider rules, both under rule_providers
        /** @var array<string, mixed> $ruleProviders */
        $ruleProviders = is_array($bjy['rule_providers'] ?? null) ? $bjy['rule_providers'] : [];
        foreach ($ruleProviders as $providerId => $providerConfig) {
            $allow = self::CONFIG_RULE_PROVIDER === $providerId
                ? (is_array($providerConfig['allow'] ?? null) ? $providerConfig['allow'] : [])
                : $this->dynamicAllow((string) $providerId);

            foreach ($allow as $entry) {
                $this->addRule($byResource, $entry);
            }
        }

        // 2. route guards → all-privileges grant on route/<route>, last declaration wins
        /** @var array<int, mixed> $guards */
        $guards = is_array($bjy['guards'][self::ROUTE_GUARD] ?? null) ? $bjy['guards'][self::ROUTE_GUARD] : [];
        $routeRoles = [];
        foreach ($guards as $guard) {
            if (! is_array($guard) || ! is_string($guard['route'] ?? null)) {
                continue;
            }
            //last declaration wins, matching BjyAuthorize's guard (it assigns, not merges)
            $routeRoles[$guard['route']] = self::roleMatcher($guard['roles'] ?? []);
        }
        foreach ($routeRoles as $route => $match) {
            $byResource['route/' . $route][] = [
                'roles'      => $match['roles'],
                'privileges' => [],
                'allRoles'   => $match['all'],
            ];
        }

        return $byResource;
    }

    /**
     * A dynamic provider's `getRules()['allow']`, in the same `[[roles], resource, priv?]`
     * shape as the config rules.
     *
     * @return array<int, mixed>
     */
    private function dynamicAllow(string $serviceId): array
    {
        if (! $this->laminas->has($serviceId)) {
            return [];
        }
        $provider = $this->laminas->get($serviceId);
        if (! is_object($provider) || ! method_exists($provider, 'getRules')) {
            return [];
        }
        /** @var mixed $rules */
        $rules = $provider->getRules();

        return is_array($rules) && is_array($rules['allow'] ?? null) ? $rules['allow'] : [];
    }

    /**
     * @param RulesByResource $byResource
     * @param mixed           $entry      `[[roles], resource, privilege?]`
     */
    private function addRule(array &$byResource, mixed $entry): void
    {
        if (! is_array($entry) || ! array_key_exists(0, $entry) || ! isset($entry[1]) || ! is_string($entry[1])) {
            return;
        }
        $match      = self::roleMatcher($entry[0]);
        $resource   = $entry[1];
        $privileges = array_key_exists(2, $entry) ? self::stringList($entry[2]) : [];

        $byResource[$resource][] = [
            'roles'      => $match['roles'],
            'privileges' => $privileges,
            'allRoles'   => $match['all'],
        ];
    }

    /**
     * Normalises a rule's role operand the way Laminas\Permissions\Acl does: a `null`
     * anywhere in it — a bare `null`, or a `null` element in the list — grants the rule to
     * **every** role (the route guards here declare `['user', 'guest', null]`, and that
     * `null` is why parentless roles like `lib_administrator` and `translator` reach those
     * routes). Any other value is the set of named roles.
     *
     * @return array{roles: list<string>, all: bool}
     */
    private static function roleMatcher(mixed $value): array
    {
        if (null === $value) {
            return ['roles' => [], 'all' => true];
        }
        if (! is_array($value)) {
            return ['roles' => self::stringList($value), 'all' => false];
        }
        $all = false;
        $roles = [];
        foreach ($value as $v) {
            if (null === $v) {
                $all = true;
                continue;
            }
            if (is_string($v) && '' !== $v) {
                $roles[] = $v;
            }
        }

        return ['roles' => $roles, 'all' => $all];
    }

    /**
     * A scalar or list of scalars, as a list of non-empty strings. A single privilege is
     * written as a bare string in the config; a role list is already a list.
     *
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            return '' === $value ? [] : [$value];
        }
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $v) {
            if (is_string($v) && '' !== $v) {
                $out[] = $v;
            }
        }

        return $out;
    }
}

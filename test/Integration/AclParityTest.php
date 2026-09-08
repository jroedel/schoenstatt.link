<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Acl\AclAssembler;
use App\Acl\Authorizer;
use App\Laminas\ServiceBridge;
use BjyAuthorize\Service\Authorize;
use Laminas\Db\Adapter\Adapter;
use Laminas\Permissions\Acl\Acl;
use PHPUnit\Framework\TestCase;
use Throwable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The safety net for replacing BjyAuthorize + Laminas\Permissions\Acl with
 * symfony/security-core: the new engine ({@see AclAssembler} → {@see Authorizer}) must
 * return the **same** decision as the live BjyAuthorize ACL for every role, on every
 * resource, for every privilege — before anything is cut over to it.
 *
 * The oracle is the assembled Laminas ACL itself, not a snapshot, so this cannot pass
 * against a stale `docs/acl-baseline.json`. It sweeps the ACL's own role and resource sets
 * (the resource set is the superset — BjyAuthorize registers resource-only providers like
 * EventTextTable that carry no rules; the new engine denies those, and so does Laminas, so
 * the decisions still match) against every privilege any rule names, plus the null
 * "resource generally" privilege the route guard asks.
 *
 * One role in the ACL is **not** a real role and is excluded from the named sweep:
 * `bjyauthorize-identity`, the synthetic per-request holder BjyAuthorize adds in
 * `Authorize::load()` whose parents are the *current* identity's roles (see
 * `BjyAuthorize\Service\Authorize::getIdentity()`). The new engine has no such role — it
 * queries the identity's real role *names* directly — so reproducing it means querying the
 * anonymous identity's role set (the configured `default_role`). That equivalence is what
 * `assertAnonymousIdentityMatches()` proves, turning the exclusion into a positive check.
 *
 * Needs a database (roles and the per-library rules) and APCu (BjyAuthorize's ACL cache);
 * skips without them, like AclCacheTest.
 */
class AclParityTest extends TestCase
{
    /** BjyAuthorize's synthetic per-request identity role — see Authorize::getIdentity(). */
    private const IDENTITY_ROLE = 'bjyauthorize-identity';

    private static ?ServiceBridge $bridge = null;

    public function testTheNewEngineAgreesWithBjyAuthorizeEverywhere(): void
    {
        $bridge = $this->bridge();

        try {
            /** @var Adapter $adapter */
            $adapter = $bridge->get(Adapter::class);
            $adapter->query('SELECT 1', Adapter::QUERY_MODE_EXECUTE);
        } catch (Throwable $e) {
            self::markTestSkipped('no reachable database: ' . $e->getMessage());
        }

        /** @var Authorize $authorize */
        $authorize = $bridge->get(Authorize::class);
        $authorize->load();
        $acl = $authorize->getAcl();
        self::assertInstanceOf(Acl::class, $acl);

        $authorizer = new Authorizer((new AclAssembler($bridge))->assemble());

        $resources  = $this->names($acl->getResources());
        $privileges = [...$authorizer->privilegesForTest($bridge), null];

        // bjyauthorize-identity is BjyAuthorize's synthetic identity holder, not a real
        // role; exclude it from the named sweep and verify it separately below.
        $roles = array_values(array_filter(
            $this->names($acl->getRoles()),
            static fn (string $role): bool => self::IDENTITY_ROLE !== $role
        ));

        self::assertNotEmpty($roles, 'the ACL registered no roles');
        self::assertNotEmpty($resources, 'the ACL registered no resources');

        $checked = 0;
        $mismatches = [];
        foreach ($roles as $role) {
            foreach ($resources as $resource) {
                foreach ($privileges as $privilege) {
                    $mine = $authorizer->isAllowedForRoles([$role], $resource, $privilege);
                    $theirs = $acl->isAllowed($role, $resource, $privilege);
                    $checked++;
                    if ($mine !== $theirs) {
                        $mismatches[] = sprintf(
                            '%s / %s / %s : new=%s bjy=%s',
                            $role,
                            $resource,
                            $privilege ?? '(null)',
                            $mine ? 'allow' : 'deny',
                            $theirs ? 'allow' : 'deny'
                        );
                    }
                }
            }
        }

        self::assertSame(
            [],
            array_slice($mismatches, 0, 40),
            $checked . ' decisions compared; ' . count($mismatches) . ' diverge from BjyAuthorize'
        );

        $this->assertAnonymousIdentityMatches($acl, $authorizer, $resources, $privileges, $bridge);
        $this->assertNoDenyRules($bridge);
        $this->assertMultiRoleIsOrOfSingles($authorizer, $roles, $resources, $privileges);
    }

    /**
     * The whole engine treats authorization as pure allow + default-deny, which is what
     * lets a multi-role identity decision be the OR of its single-role decisions (see
     * assertMultiRoleIsOrOfSingles) and what the RouteGuard cutover relies on. A `deny`
     * rule would break that composition silently, so pin its absence: BjyAuthorize's
     * config here declares only `allow`, and AclAssembler reads only `allow`.
     */
    private function assertNoDenyRules(ServiceBridge $bridge): void
    {
        $bjy = $bridge->config()['bjyauthorize'] ?? [];
        $deny = [];
        $walk = static function (array $node, string $path) use (&$walk, &$deny): void {
            foreach ($node as $key => $value) {
                if ('deny' === $key && is_array($value) && [] !== $value) {
                    $deny[] = $path . '/deny';
                }
                if (is_array($value)) {
                    $walk($value, $path . '/' . $key);
                }
            }
        };
        if (is_array($bjy)) {
            $walk($bjy, 'bjyauthorize');
        }

        self::assertSame([], $deny, 'the ACL now has deny rules; the new engine only models allow');
    }

    /**
     * The RouteGuard cutover asks `isAllowedForRoles($identityRoles, ...)` with the whole
     * role set an account holds — never one role at a time. With no deny rules a set's
     * decision must be the OR of its members' decisions (which single-role parity above has
     * already matched to BjyAuthorize), so this pins the voter's role-union logic directly.
     *
     * @param list<string>      $roles
     * @param list<string>      $resources
     * @param list<string|null> $privileges
     */
    private function assertMultiRoleIsOrOfSingles(
        Authorizer $authorizer,
        array $roles,
        array $resources,
        array $privileges
    ): void {
        // a handful of representative sets, including the widest (every role at once)
        $sets = [
            $roles,
            array_slice($roles, 0, 3),
            ['lib_patres', 'sch_patres', 'texts_administrator'],
        ];

        $mismatches = [];
        foreach ($sets as $set) {
            $set = array_values(array_intersect($set, $roles));
            if ([] === $set) {
                continue;
            }
            foreach ($resources as $resource) {
                foreach ($privileges as $privilege) {
                    $set_allows = $authorizer->isAllowedForRoles($set, $resource, $privilege);
                    $or = false;
                    foreach ($set as $one) {
                        if ($authorizer->isAllowedForRoles([$one], $resource, $privilege)) {
                            $or = true;
                            break;
                        }
                    }
                    if ($set_allows !== $or) {
                        $mismatches[] = sprintf(
                            '{%s} / %s / %s : set=%s or-of-singles=%s',
                            implode(',', $set),
                            $resource,
                            $privilege ?? '(null)',
                            $set_allows ? 'allow' : 'deny',
                            $or ? 'allow' : 'deny'
                        );
                    }
                }
            }
        }

        self::assertSame(
            [],
            array_slice($mismatches, 0, 40),
            count($mismatches) . ' multi-role decisions are not the OR of their single-role decisions'
        );
    }

    /**
     * The synthetic `bjyauthorize-identity` role inherits the current identity's roles; in
     * this headless run that is the anonymous identity, i.e. the configured `default_role`.
     * Prove the new engine reproduces BjyAuthorize's identity decision by querying that
     * real role set directly — this is the query the cutover will actually make.
     *
     * @param list<string>      $resources
     * @param list<string|null> $privileges
     */
    private function assertAnonymousIdentityMatches(
        Acl $acl,
        Authorizer $authorizer,
        array $resources,
        array $privileges,
        ServiceBridge $bridge
    ): void {
        $defaultRole = $bridge->config()['bjyauthorize']['default_role'] ?? null;
        self::assertIsString($defaultRole, 'no bjyauthorize.default_role configured');

        $mismatches = [];
        foreach ($resources as $resource) {
            foreach ($privileges as $privilege) {
                $mine   = $authorizer->isAllowedForRoles([$defaultRole], $resource, $privilege);
                $theirs = $acl->isAllowed(self::IDENTITY_ROLE, $resource, $privilege);
                if ($mine !== $theirs) {
                    $mismatches[] = sprintf(
                        '%s(as anon) / %s / %s : new=%s bjy=%s',
                        $defaultRole,
                        $resource,
                        $privilege ?? '(null)',
                        $mine ? 'allow' : 'deny',
                        $theirs ? 'allow' : 'deny'
                    );
                }
            }
        }

        self::assertSame(
            [],
            array_slice($mismatches, 0, 40),
            count($mismatches) . ' anonymous-identity decisions diverge from BjyAuthorize'
        );
    }

    /**
     * Laminas ACL getRoles()/getResources() return id strings (or objects that stringify
     * to their id); normalise to a plain string list.
     *
     * @param iterable<mixed> $items
     * @return list<string>
     */
    private function names(iterable $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $out[] = is_object($item) && method_exists($item, 'getRoleId')
                ? $item->getRoleId()
                : (is_object($item) && method_exists($item, 'getResourceId') ? $item->getResourceId() : (string) $item);
        }

        return $out;
    }

    private function bridge(): ServiceBridge
    {
        if (null !== self::$bridge) {
            return self::$bridge;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        return self::$bridge = new ServiceBridge($appConfig);
    }
}

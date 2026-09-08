<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Acl\IdentityRoles;
use App\Laminas\ServiceBridge;
use JUser\Bridge\Laminas\ZfcUserZendDbPlusSelfAsRole;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\NonPersistent;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\TableGateway;
use PHPUnit\Framework\TestCase;
use Throwable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * `App\Acl\IdentityRoles` reproduces what BjyAuthorize's identity provider
 * (`JUser\Bridge\Laminas\ZfcUserZendDbPlusSelfAsRole`) returned, so this application can stop
 * loading that class — it `implements` a bjy interface — and drop the package while JUser
 * keeps its bridge for patres. A divergence here would silently change who can reach what.
 *
 * The oracle is the provider's OWN code, run for real: the actual `ZfcUserZendDbPlusSelfAsRole`
 * built on the real `user_role_linker` gateway, given a stub auth service that reports a chosen
 * user id. For every account, `IdentityRoles::rolesForUser()` must equal `getIdentityRoles()`;
 * and the anonymous answer must be `[default_role]` for both.
 *
 * Needs a database; skips without one, and is baselined in test/known-ci-skips.txt.
 */
class AclIdentityRolesParityTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    public function testIdentityRolesMatchesTheBjyProviderForEveryAccount(): void
    {
        $bridge = $this->bridge();

        try {
            /** @var Adapter $adapter */
            $adapter = $bridge->get(Adapter::class);
            $adapter->query('SELECT 1', Adapter::QUERY_MODE_EXECUTE);
        } catch (Throwable $e) {
            self::markTestSkipped('no reachable database: ' . $e->getMessage());
        }

        $identityRoles = new IdentityRoles($bridge);

        // Anonymous parity: both answer [default_role].
        $anonProvider = new ZfcUserZendDbPlusSelfAsRole(
            new TableGateway('user_role_linker', $adapter),
            new AuthenticationService(new NonPersistent())
        );
        $anonProvider->setDefaultRole($identityRoles->defaultRole());
        self::assertSame(
            $this->normalise($anonProvider->getIdentityRoles()),
            $this->normalise($identityRoles->current()),
            'anonymous role set diverged'
        );

        // Per-account parity: every user id that has role links, and a few that do not.
        $userIds = [];
        foreach (
            $adapter->query(
                'SELECT DISTINCT user_id FROM user_role_linker ORDER BY user_id',
                Adapter::QUERY_MODE_EXECUTE
            ) as $row
        ) {
            $userIds[] = (int) $row['user_id'];
        }
        self::assertNotEmpty($userIds, 'no user_role_linker rows to compare');

        $mismatches = [];
        foreach ($userIds as $userId) {
            $provider = new ZfcUserZendDbPlusSelfAsRole(
                new TableGateway('user_role_linker', $adapter),
                new AuthenticationService($this->storageFor($userId))
            );
            $provider->setDefaultRole('guest');

            $theirs = $this->normalise($provider->getIdentityRoles());
            $mine   = $this->normalise($identityRoles->rolesForUser($userId));
            if ($mine !== $theirs) {
                $mismatches[] = sprintf('user %d: new=[%s] bjy=[%s]', $userId, implode(',', $mine), implode(',', $theirs));
            }
        }

        self::assertSame(
            [],
            array_slice($mismatches, 0, 40),
            count($mismatches) . ' of ' . count($userIds) . ' accounts diverge from the BjyAuthorize provider'
        );
    }

    /** A storage whose identity is an object answering getId() with $userId. */
    private function storageFor(int $userId): NonPersistent
    {
        $storage = new NonPersistent();
        $storage->write(new class ($userId) {
            public function __construct(private readonly int $id)
            {
            }

            public function getId(): int
            {
                return $this->id;
            }
        });

        return $storage;
    }

    /**
     * @param iterable<mixed> $roles
     * @return list<string>
     */
    private function normalise(iterable $roles): array
    {
        $out = [];
        foreach ($roles as $role) {
            $out[] = (string) $role;
        }
        sort($out);

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

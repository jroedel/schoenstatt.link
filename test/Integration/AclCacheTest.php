<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Acl\AclCacheInvalidator;
use App\Laminas\ServiceBridge;
use BjyAuthorize\Service\Authorize;
use JUser\Model\UserTable;
use Laminas\Db\Adapter\Adapter;
use Laminas\Cache\Storage\StorageInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use SionModel\Cache\EntityChangeListeners;
use Throwable;

/**
 * The assembled BjyAuthorize ACL is cached, and something expires it.
 *
 * ## What this is protecting
 *
 * Assembling the ACL cost ~6 ms on every request, anonymous ones included — measured
 * in the web SAPI on 2026-08-22, half the render time of a cheap page. It is now read
 * back from APCu instead. The document is identical for every visitor because
 * `Authorize::load()` stores it *before* adding the identity's roles, and that ordering
 * is a property of vendor code rather than of ours, so it is asserted here.
 *
 * The dangerous half is invalidation, and it is dangerous in a way that does not look
 * like a caching bug. `Laminas\Permissions\Acl\Acl::addRole()` throws on a parent role
 * the ACL has never heard of, and `load()` adds the identity's roles as exactly that.
 * An ACL cached before a role was created is therefore a **500 on every request by
 * whoever was granted that role**, not a stale page — which is why invalidation hangs
 * off `SionCacheTrait::removeDependentCacheItems()`, the one point every write in every
 * module passes through, rather than off the four write paths that exist today.
 */
class AclCacheTest extends TestCase
{
    use RequiresApcu;

    private static ?ServiceBridge $bridge = null;

    /**
     * Module loading the way bin/console does, with the config caches off — CI has no
     * writable data/config, and a cache file owned by the wrong user beside a real
     * deployment is worse than a slow test.
     */
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

    /** @return array<string, mixed> */
    private function bjyConfig(): array
    {
        /** @var array<string, mixed> $config */
        $config = $this->bridge()->get('BjyAuthorize\Config');

        return $config;
    }

    public function testCachingIsOnAndBackedBySomethingThatOutlivesTheRequest(): void
    {
        $config = $this->bjyConfig();

        self::assertTrue($config['cache_enabled'], 'the assembled ACL should be cached');
        self::assertSame(
            'apcu',
            $config['cache_options']['adapter']['name'],
            "BjyAuthorize's own default is the `memory` adapter, which caches nothing beyond the "
            . 'request — cache_enabled alone would look like a change and do nothing'
        );
    }

    /**
     * The TTL and namespace must sit at `cache_options.options`, which is *not* where
     * every other cache block in this application puts them.
     *
     * `BjyAuthorize\Service\CacheFactory` reads `$cacheOptions['options']`, ignoring
     * anything under `adapter`. Nesting them the familiar way — as sionmodel.global.php
     * does — is accepted in silence and leaves the ACL cached in the default namespace
     * with no expiry at all, which is the one configuration this change must not ship.
     */
    public function testTheAdapterOptionsAreWhereBjyAuthorizeLooksForThem(): void
    {
        $options = $this->bjyConfig()['cache_options']['options'] ?? null;

        self::assertIsArray($options, 'cache_options.options is where CacheFactory reads from');
        self::assertGreaterThan(0, $options['ttl'] ?? 0, 'the backstop TTL must be set');
        self::assertLessThanOrEqual(3600, $options['ttl'], 'a long TTL stops being a backstop');
        self::assertSame('bjyauthorize', $options['namespace'] ?? null);
    }

    /**
     * A cached document must be identity-free, or one visitor's roles would answer for
     * the next. `Authorize::load()` calls `setItem()` and only then `addRole($identity)`,
     * so this holds — but it holds because of vendor ordering nobody here controls.
     */
    public function testTheStoredAclCarriesNoIdentity(): void
    {
        $this->requireApcu();

        $storage = $this->aclStorage();
        $key     = (string) ($this->bjyConfig()['cache_key'] ?? 'acl');

        //force an assembly and a write
        $this->authorize()->isAllowed('route/home');

        $success = false;
        $stored  = $storage->getItem($key, $success);
        if (! $success) {
            self::markTestSkipped('APCu is not available to this process, so nothing was stored');
        }

        self::assertInstanceOf(\Laminas\Permissions\Acl\Acl::class, $stored);
        foreach ($stored->getRoles() as $role) {
            self::assertDoesNotMatchRegularExpression(
                '/^\d+$/',
                (string) $role,
                'a numeric role is a user id, i.e. an identity that was stored with the document'
            );
        }
    }

    /**
     * The 294 `user_<id>` roles are gone, and staying gone is the point.
     *
     * They were one ACL role per account, from a provider deleted on 2026-08-22 because
     * nothing in the life of this database ever wrote a rule naming one. Their real cost
     * was making the ACL depend on the `user` table — and an account is created on every
     * first-time sign-in, so re-adding them would invalidate this cache continuously and
     * quietly undo the whole change.
     */
    public function testTheAclHoldsNoPerUserRoles(): void
    {
        $this->requireApcu();

        $acl   = $this->authorize()->getAcl();
        $roles = array_map('strval', $acl->getRoles());

        $perUser = array_values(array_filter(
            $roles,
            static fn (string $role): bool => 1 === preg_match('/^user_\d+$/', $role)
        ));

        self::assertSame([], $perUser, 'a per-user ACL role is back; see JUser config/module.config.php');
        self::assertNotEmpty($roles, 'sanity: the ACL should still hold the real roles');
    }

    /**
     * Every account's roles must exist in the ACL, because `load()` hands them to
     * `addRole()` as parent roles and that throws on one it does not know. This is the
     * assertion that would have caught the crash mode by inspection rather than by outage.
     */
    public function testEveryRoleAnAccountCanHoldExistsInTheAcl(): void
    {
        //Before the try below, and not inside it: that catch only wraps the query, and it
        //would report an unbuildable cache as "no reachable database" — which is how this
        //one hid behind the DB skip on a runner that has neither.
        $this->requireApcu();

        try {
            /** @var Adapter $adapter */
            $adapter = $this->bridge()->get(Adapter::class);
            $rows    = $adapter->query('SELECT role_id FROM user_role', Adapter::QUERY_MODE_EXECUTE);
        } catch (Throwable $e) {
            self::markTestSkipped('no reachable database: ' . $e->getMessage());
        }

        //Read live and compared against an ACL that may have come out of the cache, which
        //is the whole value of the assertion: it is the staleness a wrong invalidation
        //produces, seen from the direction that hurts.
        $acl     = $this->authorize()->getAcl();
        $checked = 0;
        foreach ($rows as $row) {
            $roleId = (string) $row['role_id'];
            self::assertTrue(
                $acl->hasRole($roleId),
                sprintf(
                    'role "%s" can be granted to an account but the ACL does not know it — '
                    . 'Acl::addRole() throws on an unknown parent role, so this is a 500 for '
                    . 'whoever holds it, not a missing permission',
                    $roleId
                )
            );
            $checked++;
        }
        self::assertGreaterThan(0, $checked, 'sanity: user_role should not be empty');
    }

    public function testTheListenerRegistryIsRegisteredAndCarriesTheAclInvalidator(): void
    {
        $this->requireApcu();

        self::assertTrue(
            $this->bridge()->has(EntityChangeListeners::class),
            'without this in the container SionTableWiring wires nothing and no write ever expires the ACL'
        );

        /** @var EntityChangeListeners $registry */
        $registry  = $this->bridge()->get(EntityChangeListeners::class);
        $listeners = (new ReflectionProperty($registry, 'listeners'))->getValue($registry);

        self::assertIsArray($listeners);
        $kinds = array_map(static fn (object $l): string => $l::class, $listeners);
        self::assertContains(AclCacheInvalidator::class, $kinds);
    }

    /**
     * A table built by the container is handed the registry. Without this the class
     * exists, is registered, and is never called by anything.
     */
    public function testATableIsWiredToTheRegistry(): void
    {
        $this->requireApcu();

        /** @var UserTable $users */
        $users = $this->bridge()->get(UserTable::class);
        $wired = (new ReflectionProperty($users, 'entityChangeListeners'))->getValue($users);

        self::assertInstanceOf(EntityChangeListeners::class, $wired);
    }

    /** @return array<int, array{string, bool}> */
    public static function entities(): array
    {
        return [
            'a role definition' => ['user-role', true],
            'a library'         => ['library', true],
            'a text'            => ['text', true],
            //changes constantly and is not in the document: which roles an account holds
            //is read per request and added to a copy
            'a role grant'      => ['user-role-link', false],
            'an account'        => ['user', false],
            'a book'            => ['book', false],
        ];
    }

    #[DataProvider('entities')]
    public function testTheInvalidatorExpiresExactlyTheEntitiesTheAclIsBuiltFrom(
        string $entity,
        bool $shouldExpire
    ): void {
        $removed = [];
        $cache   = $this->createStub(StorageInterface::class);
        $cache->method('removeItem')->willReturnCallback(
            static function ($key) use (&$removed): bool {
                $removed[] = (string) $key;

                return true;
            }
        );

        (new AclCacheInvalidator($cache, 'acl'))->entityChanged($entity);

        self::assertSame($shouldExpire ? ['acl'] : [], $removed);
    }

    /**
     * The listener runs inside the write that triggered it, so an unreachable cache must
     * cost a slow next request and never the write itself. A laminas cache adapter throws
     * when its backing store refuses, which on APCu mostly means a full segment.
     */
    public function testAFailingCacheDoesNotTakeTheWriteWithIt(): void
    {
        $cache = $this->createStub(StorageInterface::class);
        $cache->method('removeItem')->willThrowException(new RuntimeException('segment unreachable'));

        (new AclCacheInvalidator($cache, 'acl'))->entityChanged('user-role');

        $this->addToAssertionCount(1);
    }

    private function authorize(): Authorize
    {
        /** @var Authorize $authorize */
        $authorize = $this->bridge()->get(Authorize::class);

        return $authorize;
    }

    private function aclStorage(): StorageInterface
    {
        /** @var StorageInterface $storage */
        $storage = $this->bridge()->get('BjyAuthorize\Cache');

        return $storage;
    }
}

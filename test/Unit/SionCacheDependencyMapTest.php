<?php

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;

//Required at parse time, like the sibling budget test: the host class below
//pulls the trait in as this file is parsed, before any fixture could run.
require_once __DIR__ . '/../../module/SionModel/src/Db/Model/SionCacheTrait.php';

/**
 * The invariants that keep SionModel's cache from serving data it can no longer
 * invalidate.
 *
 * A cached item lives under its own key; what it depends on lives in a separate
 * map. Invalidation reads the map, so an item the map does not name is an item
 * nothing can ever remove — it keeps answering with whatever the database said
 * when it was written, until its own TTL runs out.
 *
 * On 2026-08-09 that is exactly what happened to the production user list. An
 * admin created an account, the row landed in the database, and the list went on
 * showing the world without it. No race, no expunge, no full segment: the map
 * had simply expired underneath items that were still being refreshed, because
 * a cache miss rewrote the item (pushing its TTL out another day) while the map
 * was only rewritten when it learned a key it did not already know.
 * testTheProductionStalenessScenario reproduces that sequence step by step.
 *
 * The tests are grouped by the property each defends:
 *
 *  - **refresh** — the map is written whenever an item is, so it cannot expire
 *    first;
 *  - **fail closed** — an item with no dependency record is not served, so a map
 *    lost some other way costs a query rather than correctness;
 *  - **merge** — two requests registering different keys both end up on record;
 *  - **generation** — a snapshot a concurrent change has overtaken is dropped
 *    rather than written back over the removal.
 *
 * The trait file is required directly: test/bootstrap.php avoids
 * vendor/autoload.php so this suite stays valid while vendor/ is mid-migration.
 * Every Laminas import in the trait belongs to a method these tests do not call.
 */
class SionCacheDependencyMapTest extends TestCase
{
    private const MAP = 'maphost-cachedependencies';

    private function host(FakeCacheStore $store, $logger = null): DependencyMapHost
    {
        return new DependencyMapHost($store, $logger);
    }

    // ------------------------------------------------------------- refresh

    /**
     * The root cause, stated as a property: writing an item writes the map,
     * every time. Previously the map was persisted only when it learned a new
     * key, so an item refreshed daily sat behind a map that never was.
     */
    public function testTheDependencyMapIsRewrittenEvenWhenItLearnsNothingNew(): void
    {
        $store = new FakeCacheStore();
        $host = $this->host($store);

        $host->cacheObjects('users', ['ann'], ['user']);
        $host->cacheObjects('users', ['ann', 'bob'], ['user']);

        $this->assertSame(
            2,
            $store->writeCount(self::MAP),
            'the map must be rewritten on every cache write, or its TTL falls behind the items it governs'
        );
    }

    public function testRegisteringADependencyRecordsTheEntitiesItDependsOn(): void
    {
        $store = new FakeCacheStore();
        $this->host($store)->cacheObjects('users', ['ann'], ['user', 'user-role']);

        $this->assertSame(
            ['user', 'user-role'],
            $store->read(self::MAP)['maphost-users']
        );
    }

    // ---------------------------------------------------------- fail closed

    /**
     * The production incident, replayed. Each step is something that really
     * happens; only the sequence is contrived.
     */
    public function testTheProductionStalenessScenario(): void
    {
        $store = new FakeCacheStore();

        //A visitor loads the user list. The item and the map are both stored.
        $visitor = $this->host($store);
        $visitor->cacheObjects('all-linked-users', ['ann'], ['user']);
        $visitor->onFinishWriteCache();
        $this->assertTrue($store->has('maphost-all-linked-users'));

        //Time passes. The map's day runs out; the item's has been pushed
        //forward by later misses, so it is still there.
        $store->expire(self::MAP);

        //An admin creates an account. This is the request that must invalidate.
        $admin = $this->host($store);
        $admin->removeDependentCacheItems('user');

        //And then looks at the list.
        $reader = $this->host($store);
        $success = null;
        $served = $reader->fetch('all-linked-users', $success);

        $this->assertFalse($success, 'a list we could not invalidate must not be served as if we had');
        $this->assertNull($served);
    }

    public function testAnItemWithNoDependencyRecordIsNotServed(): void
    {
        $store = new FakeCacheStore();
        $store->setItem('maphost-users', ['ann']);

        $success = null;
        $served = $this->host($store)->fetch('users', $success);

        $this->assertFalse($success);
        $this->assertNull($served);
    }

    public function testAnItemWithADependencyRecordIsServed(): void
    {
        $store = new FakeCacheStore();
        $writer = $this->host($store);
        $writer->cacheObjects('users', ['ann'], ['user']);
        $writer->onFinishWriteCache();

        $success = null;
        $served = $this->host($store)->fetch('users', $success);

        $this->assertTrue($success, 'an intact map must not cause false refusals');
        $this->assertSame(['ann'], $served);
    }

    /**
     * Refusing is not a dead end: the caller queries, caches again, and the key
     * is back on record — so the cache repairs itself in one request instead of
     * staying wrong until a TTL saves it.
     */
    public function testARefusedItemIsBackOnRecordAfterTheCallerCachesAgain(): void
    {
        $store = new FakeCacheStore();
        $store->setItem('maphost-users', ['stale']);

        $host = $this->host($store);
        $success = null;
        $host->fetch('users', $success);
        $this->assertFalse($success);

        //what the caller does on a miss
        $host->cacheObjects('users', ['ann', 'bob'], ['user']);
        $host->onFinishWriteCache();

        $host->removeDependentCacheItems('user');
        $this->assertFalse($store->has('maphost-users'), 'the re-registered key must now be invalidatable');
    }

    public function testRefusingAnOrphanIsLogged(): void
    {
        $store = new FakeCacheStore();
        $store->setItem('maphost-users', ['stale']);
        $logger = new CollectingLogger();

        $success = null;
        $this->host($store, $logger)->fetch('users', $success);

        $notices = $logger->messages('notice');
        $this->assertCount(1, $notices);
        $this->assertSame('maphost-users', $notices[0]['context']['cacheKey']);
    }

    /**
     * The key may have been registered by a request that started after ours, so
     * one re-read of the stored map is worth trying before refusing.
     */
    public function testAKeyRegisteredByAnotherRequestIsPickedUpOnReRead(): void
    {
        $store = new FakeCacheStore();
        $reader = $this->host($store);

        //…after $reader loaded its (empty) map
        $other = $this->host($store);
        $other->cacheObjects('users', ['ann'], ['user']);
        $other->onFinishWriteCache();

        $success = null;
        $served = $reader->fetch('users', $success);

        $this->assertTrue($success);
        $this->assertSame(['ann'], $served);
    }

    /**
     * JUser's factory replaces the application-wide cache with its own APCu
     * namespace after SionTable's constructor has already read a map from the
     * first one. That map names keys in a namespace this instance no longer
     * touches, so it must not be allowed to vouch for anything.
     */
    public function testReplacingTheStorageDiscardsTheMapReadFromTheOldOne(): void
    {
        $shared = new FakeCacheStore();
        $namespaced = new FakeCacheStore();

        $registrar = $this->host($shared);
        $registrar->cacheObjects('users', ['ann'], ['user']);
        $registrar->onFinishWriteCache();

        //constructed against the shared store, then switched — the JUser sequence
        $host = $this->host($shared);
        $host->setPersistentCache($namespaced);
        $namespaced->setItem('maphost-users', ['whatever this namespace happens to hold']);

        $success = null;
        $host->fetch('users', $success);

        $this->assertFalse($success);
    }

    // ---------------------------------------------------------------- merge

    /**
     * Both requests read the map before either wrote it. Overwriting would leave
     * the loser's item orphaned — the same end state as an expiry, reached in
     * milliseconds instead of a day.
     */
    public function testConcurrentRegistrationsBothSurvive(): void
    {
        $store = new FakeCacheStore();
        $first = $this->host($store);
        $second = $this->host($store);

        $first->cacheObjects('users', ['ann'], ['user']);
        $second->cacheObjects('roles', ['admin'], ['user-role']);

        $map = $store->read(self::MAP);
        $this->assertArrayHasKey('maphost-users', $map);
        $this->assertArrayHasKey('maphost-roles', $map);
    }

    /**
     * Invalidation must cover keys this instance never registered: they belong
     * to the same class and depend on the same entity.
     */
    public function testInvalidationRemovesAKeyRegisteredByAnotherRequest(): void
    {
        $store = new FakeCacheStore();
        $admin = $this->host($store);

        $visitor = $this->host($store);
        $visitor->cacheObjects('users', ['ann'], ['user']);
        $visitor->onFinishWriteCache();
        $this->assertTrue($store->has('maphost-users'));

        $admin->removeDependentCacheItems('user');

        $this->assertFalse($store->has('maphost-users'));
    }

    public function testInvalidationLeavesKeysThatDependOnOtherEntities(): void
    {
        $store = new FakeCacheStore();
        $host = $this->host($store);
        $host->cacheObjects('roles', ['admin'], ['user-role']);
        $host->onFinishWriteCache();

        $host->removeDependentCacheItems('user');

        $this->assertTrue($store->has('maphost-roles'));
    }

    // ----------------------------------------------------------- generation

    /**
     * Items are written at the end of the request, long after they were read. A
     * write that lands after someone else's invalidation would put pre-change
     * data back on top of the removal, where it would sit for a full TTL.
     */
    public function testASnapshotOvertakenByAChangeIsNotWritten(): void
    {
        $store = new FakeCacheStore();

        $reader = $this->host($store);
        $reader->cacheObjects('users', ['ann'], ['user']);

        $writer = $this->host($store);
        $writer->removeDependentCacheItems('user');

        $reader->onFinishWriteCache();

        $this->assertFalse(
            $store->has('maphost-users'),
            'the removal must not be undone by a snapshot taken before it'
        );
    }

    public function testAnUnovertakenSnapshotIsWritten(): void
    {
        $store = new FakeCacheStore();
        $reader = $this->host($store);
        $reader->cacheObjects('users', ['ann'], ['user']);

        $reader->onFinishWriteCache();

        $this->assertTrue($store->has('maphost-users'));
    }

    public function testDiscardingAnOvertakenSnapshotIsLogged(): void
    {
        $store = new FakeCacheStore();
        $logger = new CollectingLogger();

        $reader = $this->host($store, $logger);
        $reader->cacheObjects('users', ['ann'], ['user']);
        $this->host($store)->removeDependentCacheItems('user');
        $reader->onFinishWriteCache();

        $discarded = $logger->messages('info');
        $this->assertCount(1, $discarded);
        $this->assertSame('maphost-users', $discarded[0]['context']['cacheKey']);
    }

    public function testInvalidationAdvancesTheGenerationCounter(): void
    {
        $store = new FakeCacheStore();
        $host = $this->host($store);

        $host->removeDependentCacheItems('user');
        $host->removeDependentCacheItems('user');

        $this->assertSame(2, $store->read('maphost-cachegeneration'));
    }

    // ------------------------------------------------------------ no silent caps

    /**
     * max_items_to_cache is 2 in production, so a request touching four cached
     * queries silently persisted none of the last two. Silence there reads
     * afterwards as "everything is cached", which is how an item that is
     * re-queried on every request goes unnoticed.
     */
    public function testWritesSkippedForTheItemLimitAreLogged(): void
    {
        $store = new FakeCacheStore();
        $logger = new CollectingLogger();
        $host = $this->host($store, $logger);
        $host->setMaxItemsToCache(1);

        $host->cacheObjects('users', ['ann'], ['user']);
        $host->cacheObjects('roles', ['admin'], ['user-role']);
        $host->onFinishWriteCache();

        $skipped = $logger->messages('info');
        $this->assertCount(1, $skipped);
        $this->assertSame(['maphost-roles'], $skipped[0]['context']['skipped']);
    }

    // ------------------------------------------------------------ contract

    /**
     * The memory-cache branch used to leave $success untouched, so a caller that
     * passed it read whatever it happened to hold. Every other branch sets it.
     */
    public function testAMemoryCacheHitReportsSuccess(): void
    {
        $store = new FakeCacheStore();
        $host = $this->host($store);
        $host->cacheObjects('users', ['ann'], ['user']);

        $success = null;
        $served = $host->fetch('users', $success);

        $this->assertTrue($success);
        $this->assertSame(['ann'], $served);
    }
}

/**
 * An APCu-shaped store: the handful of operations the trait performs, plus the
 * two the tests need — a write counter, and expire(), which models a TTL lapsing
 * rather than an explicit removal. They do the same thing; naming them apart is
 * what lets the scenario test read like the incident.
 */
class FakeCacheStore
{
    /** @var array<string, mixed> */
    private $items = [];

    /** @var array<string, int> */
    private $writes = [];

    public function getItem($key, &$success = null, &$casToken = null)
    {
        $success = array_key_exists($key, $this->items);
        return $success ? $this->items[$key] : null;
    }

    public function setItem($key, $value)
    {
        $this->items[$key] = $value;
        $this->writes[$key] = ($this->writes[$key] ?? 0) + 1;
        return true;
    }

    public function removeItem($key)
    {
        unset($this->items[$key]);
        return true;
    }

    public function incrementItem($key, $value)
    {
        $this->items[$key] = (int) ($this->items[$key] ?? 0) + (int) $value;
        return $this->items[$key];
    }

    /** A TTL running out. Indistinguishable from removeItem() to the caller. */
    public function expire(string $key): void
    {
        unset($this->items[$key]);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function read(string $key)
    {
        return $this->items[$key] ?? null;
    }

    public function writeCount(string $key): int
    {
        return $this->writes[$key] ?? 0;
    }
}

/**
 * Duck-typed PSR-3 logger. The interface is deliberately not implemented: this
 * suite requires the class under test directly, with no vendor autoload.
 */
class CollectingLogger
{
    /** @var array<string, array[]> */
    private $records = [];

    public function debug($message, $context = [])
    {
        $this->record('debug', $message, $context);
    }

    public function info($message, $context = [])
    {
        $this->record('info', $message, $context);
    }

    public function notice($message, $context = [])
    {
        $this->record('notice', $message, $context);
    }

    public function warning($message, $context = [])
    {
        $this->record('warning', $message, $context);
    }

    public function error($message, $context = [])
    {
        $this->record('error', $message, $context);
    }

    /** @return array[] */
    public function messages(string $level): array
    {
        return $this->records[$level] ?? [];
    }

    private function record(string $level, $message, $context): void
    {
        $this->records[$level][] = ['message' => $message, 'context' => $context];
    }
}

/**
 * Hosts the trait with the wiring a real SionTable gets from the framework.
 * classIdentifier is assigned before setPersistentCache() so
 * getClassIdentifier() never reaches for Laminas' FilterChain.
 */
class DependencyMapHost
{
    use \SionModel\Db\Model\SionCacheTrait;

    public $logger;

    public function __construct($cache, $logger = null)
    {
        $this->classIdentifier = 'maphost';
        $this->logger = $logger;
        $this->setPersistentCache($cache);
    }

    /** cacheEntityObjects() takes its objects by reference, so they need a variable. */
    public function cacheObjects(string $key, array $objects, array $dependencies)
    {
        return $this->cacheEntityObjects($key, $objects, $dependencies);
    }

    public function fetch(string $key, &$success = null)
    {
        return $this->fetchCachedEntityObjects($key, $success);
    }
}

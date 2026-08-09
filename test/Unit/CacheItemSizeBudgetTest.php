<?php

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

//Loaded here rather than in setUpBeforeClass(), unlike the sibling tests: the
//host class below pulls the trait in at declaration time, which happens while
//this file is being parsed — long before any fixture method could run.
require_once __DIR__ . '/../../module/SionModel/src/Db/Model/SionCacheTrait.php';

/**
 * Pins the size budget SionModel\Db\Model\SionCacheTrait applies before it
 * hands anything to the persistent cache.
 *
 * The behaviour under test exists because production APCu is a fixed 32 MiB
 * segment with apc.ttl=0: an item too big to allocate makes APCu clear the
 * *whole* cache, and the Laminas APCu adapter then throws. Two properties
 * follow from that and both are regressions waiting to happen, so they are
 * pinned here:
 *
 *  - an oversized item is refused locally rather than attempted, and
 *  - one failed or refused write never abandons the rest of the queue.
 *
 * The trait file is required directly: test/bootstrap.php deliberately avoids
 * vendor/autoload.php so the suite stays valid even when vendor/ is
 * mid-migration. The trait's Laminas imports are only resolved by methods this
 * test does not call (wireOnFinishTrigger, getClassIdentifier), so no
 * framework code is needed here.
 */
class CacheItemSizeBudgetTest extends TestCase
{
    private function host($budget = 1024, $maxItems = 10): CacheBudgetHost
    {
        $host = new CacheBudgetHost(new RecordingCache(), new RecordingLogger());
        $host->setMaxItemSize($budget);
        $host->setMaxItemsToCache($maxItems);
        return $host;
    }

    /** A value whose serialize() length comfortably exceeds $bytes. */
    private function payloadLargerThan(int $bytes): array
    {
        return ['blob' => str_repeat('x', $bytes)];
    }

    public function testWritesAnItemThatFitsTheBudget(): void
    {
        $host = $this->host(1024);
        $host->queue('small', ['a' => 1]);

        $host->onFinishWriteCache();

        $this->assertSame(['small'], $host->cache()->writtenKeys());
    }

    public function testRefusesAnItemLargerThanTheBudget(): void
    {
        $host = $this->host(1024);
        $host->queue('huge', $this->payloadLargerThan(4096));

        $host->onFinishWriteCache();

        $this->assertSame([], $host->cache()->writtenKeys());
    }

    /**
     * The regression that mattered in production: the oversized item used to
     * throw from the adapter, and the catch block then abandoned every
     * remaining queued item, so the cache stayed cold request after request.
     */
    public function testAnOversizedItemDoesNotBlockTheItemsQueuedBehindIt(): void
    {
        $host = $this->host(1024);
        $host->queue('huge', $this->payloadLargerThan(4096));
        $host->queue('small', ['a' => 1]);

        $host->onFinishWriteCache();

        $this->assertSame(['small'], $host->cache()->writtenKeys());
    }

    /** Same guarantee when the cache itself throws rather than us refusing. */
    public function testAFailedWriteDoesNotBlockTheItemsQueuedBehindIt(): void
    {
        $host = $this->host(1024);
        $host->cache()->failOn('poison');
        $host->queue('poison', ['a' => 1]);
        $host->queue('small', ['b' => 2]);

        $host->onFinishWriteCache();

        $this->assertSame(['small'], $host->cache()->writtenKeys());
    }

    /**
     * unset() on the property would destroy it, sending later reads through
     * AbstractTableGateway::__get() and fatalling on every subsequent request
     * until APCu was cleared. That was the production fatal-200 bug; a failed
     * write must leave the property an array.
     */
    public function testMemoryCacheRemainsAnArrayAfterAFailedWrite(): void
    {
        $host = $this->host(1024);
        $host->cache()->failOn('poison');
        $host->queue('poison', ['a' => 1]);

        $host->onFinishWriteCache();

        $this->assertIsArray($host->memoryCacheValue());
    }

    public function testRefusedItemsDoNotConsumeAWriteSlot(): void
    {
        $host = $this->host(1024, 2);
        $host->queue('huge', $this->payloadLargerThan(4096));
        $host->queue('one', ['a' => 1]);
        $host->queue('two', ['b' => 2]);

        $host->onFinishWriteCache();

        $this->assertSame(['one', 'two'], $host->cache()->writtenKeys());
    }

    public function testStopsAfterMaxItemsToCacheSuccessfulWrites(): void
    {
        $host = $this->host(1024, 2);
        $host->queue('one', ['a' => 1]);
        $host->queue('two', ['b' => 2]);
        $host->queue('three', ['c' => 3]);

        $host->onFinishWriteCache();

        $this->assertSame(['one', 'two'], $host->cache()->writtenKeys());
    }

    public function testAZeroBudgetDisablesTheSizeCheck(): void
    {
        $host = $this->host(0);
        $host->queue('huge', $this->payloadLargerThan(4096));

        $host->onFinishWriteCache();

        $this->assertSame(['huge'], $host->cache()->writtenKeys());
    }

    public function testRefusingAnItemIsLoggedWithItsSizeAndBudget(): void
    {
        $host = $this->host(1024);
        $host->queue('huge', $this->payloadLargerThan(4096));

        $host->onFinishWriteCache();

        $warnings = $host->logger()->warnings();
        $this->assertCount(1, $warnings);
        $this->assertSame('huge', $warnings[0]['extra']['cacheKey']);
        $this->assertSame(1024, $warnings[0]['extra']['budget']);
        $this->assertGreaterThan(1024, $warnings[0]['extra']['size']);
    }

    public function testDoesNothingWithoutAPersistentCache(): void
    {
        $host = new CacheBudgetHost(null, new RecordingLogger());
        $host->queue('small', ['a' => 1]);

        $host->onFinishWriteCache();

        $this->assertIsArray($host->memoryCacheValue());
    }
}

/**
 * Minimal stand-in for a Laminas cache storage adapter. setItem() is what this
 * file asserts on; getItem() and incrementItem() are here because the trait
 * reads its generation counter through them on the write path. The trait
 * type-hints nothing here, so no interface is needed.
 */
class RecordingCache
{
    /** @var string[] */
    private $written = [];

    /** @var string[] */
    private $failing = [];

    /** @var array<string, mixed> */
    private $items = [];

    public function failOn(string $key): void
    {
        $this->failing[] = $key;
    }

    public function getItem($key, &$success = null, &$casToken = null)
    {
        $success = array_key_exists($key, $this->items);
        return $success ? $this->items[$key] : null;
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

    public function setItem($key, $value)
    {
        if (in_array($key, $this->failing, true)) {
            //what Laminas\Cache\Storage\Adapter\Apcu does when apcu_store() fails
            throw new RuntimeException("apcu_store('{$key}', <array>, 0) failed");
        }
        $this->items[$key] = $value;
        $this->written[] = $key;
        return true;
    }

    /** @return string[] */
    public function writtenKeys(): array
    {
        return $this->written;
    }
}

/**
 * A duck-typed PSR-3 logger: method names match Psr\Log\LoggerInterface, but the
 * interface is deliberately not implemented — this suite requires the class under
 * test directly, with no vendor autoload, so it stays valid while vendor/ is
 * mid-migration.
 */
class RecordingLogger
{
    /** @var array[] */
    private $warnings = [];

    /** @var array[] */
    private $errors = [];

    public function debug($message, $context = [])
    {
    }

    public function info($message, $context = [])
    {
    }

    public function notice($message, $context = [])
    {
    }

    public function warning($message, $context = [])
    {
        $this->warnings[] = ['message' => $message, 'extra' => $context];
    }

    public function error($message, $context = [])
    {
        $this->errors[] = ['message' => $message, 'extra' => $context];
    }

    /** @return array[] */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return array[] */
    public function errors(): array
    {
        return $this->errors;
    }
}

/**
 * Hosts the trait and exposes the bits a real SionTable sets up through
 * framework wiring. classIdentifier is assigned directly so getClassIdentifier()
 * never reaches for Laminas' FilterChain.
 */
class CacheBudgetHost
{
    use \SionModel\Db\Model\SionCacheTrait;

    public $logger;

    public function __construct($cache, $logger = null)
    {
        $this->persistentCache = $cache;
        $this->classIdentifier = 'cachebudgethost';
        $this->logger = $logger;
    }

    public function queue(string $key, $value): void
    {
        $this->memoryCache[$key] = $value;
        $this->newPersistentCacheItems[] = $key;
    }

    public function cache(): RecordingCache
    {
        return $this->persistentCache;
    }

    public function logger(): RecordingLogger
    {
        return $this->logger;
    }

    public function memoryCacheValue()
    {
        return $this->memoryCache;
    }
}

<?php

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;
use SionModel\Cache\ApcuStatus;

require_once __DIR__ . '/../../module/SionModel/src/Cache/ApcuStatus.php';

/**
 * Pins the APCu half of the /sm/cache-status payload.
 *
 * These values used to be computed inside SionModelController, where nothing
 * could reach them without an HTTP request; they moved into a class of their own
 * when a second front controller started answering the same URL (see
 * SionModel\Cache\CacheStatusPayload). Worth pinning rather than eyeballing for
 * the same reason as OpcacheStatus: every number here is a subtraction or a ratio
 * with a plausible-looking wrong answer, and the endpoint is what
 * tools/smoke-prod.sh warns on.
 *
 * The class touches no framework code and no extension, so this lives in the
 * vendor-free unit suite and requires the file directly.
 */
class ApcuStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        ApcuStatus::$clock = null;
        parent::tearDown();
    }

    /** Shape of a live apcu_sma_info(true) — a 256 MB single segment. */
    private function sma(array $overrides = []): array
    {
        return $overrides + [
            'num_seg' => 1,
            'seg_size' => 268_435_336,
            'avail_mem' => 249_346_112,
        ];
    }

    /** Shape of a live apcu_cache_info(true): counters, no entry list. */
    private function cache(array $overrides = []): array
    {
        return $overrides + [
            'num_entries' => 56,
            'num_hits' => 26_649,
            'num_misses' => 6_511,
            'expunges' => 0,
            'start_time' => 1_000_000,
        ];
    }

    /** Shape of a live apcu_cache_info(): the same, plus every entry. */
    private function entryList(array $entries): array
    {
        return ['cache_list' => $entries];
    }

    /**
     * APCu is absent rather than merely empty: apcu_sma_info() answers false, and
     * the payload has to say so in one key instead of reporting zeroes that look
     * like a healthy idle cache.
     */
    public function testReportsDisabledWhenTheExtensionAnswersFalse(): void
    {
        $this->assertSame(['apcuEnabled' => false], ApcuStatus::summarize(false, false));
        $this->assertSame(['apcuEnabled' => false], ApcuStatus::summarize(null, null));
        //one call failing is enough: the arithmetic needs both
        $this->assertSame(['apcuEnabled' => false], ApcuStatus::summarize($this->sma(), false));
    }

    /**
     * The segment total is num_seg × seg_size and *used* is what is left over
     * after avail_mem — APCu reports no used figure of its own. Getting the
     * subtraction backwards would report a nearly-full cache as nearly empty,
     * which is precisely the condition this endpoint exists to catch.
     */
    public function testOccupancyIsTotalMinusAvailableAcrossEverySegment(): void
    {
        $out = ApcuStatus::summarize(
            $this->sma(['num_seg' => 2, 'avail_mem' => 400_000_000]),
            $this->cache()
        );

        $this->assertSame(536_870_672, $out['totalBytes']);
        $this->assertSame(400_000_000, $out['availBytes']);
        $this->assertSame(136_870_672, $out['usedBytes']);
        $this->assertSame(25.5, $out['percentUsed']);
        //the identity the smoke test asserts against the live endpoint
        $this->assertSame($out['totalBytes'], $out['usedBytes'] + $out['availBytes']);
    }

    /**
     * A segment size of zero is not reachable with APCu loaded, but the payload
     * must not be a division by zero if it ever is.
     */
    public function testPercentUsedIsNullRatherThanDividingByZero(): void
    {
        $out = ApcuStatus::summarize($this->sma(['seg_size' => 0, 'avail_mem' => 0]), $this->cache());

        $this->assertSame(0, $out['totalBytes']);
        $this->assertNull($out['percentUsed']);
    }

    /**
     * expunges is the single most important number here. With apc.ttl=0 a failed
     * allocation clears the *whole* segment rather than evicting the oldest
     * entries, so any non-zero value means the cache has already been thrown away
     * — see docs/caching.md and the 2026-08-03 incident.
     */
    public function testCountersArePassedThroughAsIntegers(): void
    {
        $out = ApcuStatus::summarize($this->sma(), $this->cache(['expunges' => 2]));

        $this->assertSame(56, $out['entries']);
        $this->assertSame(26_649, $out['hits']);
        $this->assertSame(6_511, $out['misses']);
        $this->assertSame(2, $out['expunges']);
    }

    /**
     * A counter APCu did not report is null, not zero: "no data" and "zero
     * expunges" are opposite conclusions for a monitor to draw.
     */
    public function testMissingCountersAreNullRatherThanZero(): void
    {
        $out = ApcuStatus::summarize($this->sma(), ['num_entries' => 3]);

        $this->assertSame(3, $out['entries']);
        $this->assertNull($out['hits']);
        $this->assertNull($out['misses']);
        $this->assertNull($out['expunges']);
        $this->assertNull($out['uptimeSeconds']);
    }

    public function testUptimeIsMeasuredFromTheSegmentStartTime(): void
    {
        ApcuStatus::$clock = static fn (): int => 1_003_600;

        $out = ApcuStatus::summarize($this->sma(), $this->cache());

        $this->assertSame(3600, $out['uptimeSeconds']);
    }

    /**
     * Which key filled the segment is the whole point of this list: aggregate
     * occupancy says the cache is full, only this says what to fix. Largest
     * first, and capped, because the live segment holds hundreds of entries.
     */
    public function testLargestEntriesAreSortedByAllocatedSizeAndCapped(): void
    {
        $entries = [];
        foreach (range(1, 20) as $i) {
            $entries[] = ['info' => 'key-' . $i, 'mem_size' => $i * 1000];
        }

        $out = ApcuStatus::summarize($this->sma(), $this->cache(), $this->entryList($entries), 3);

        $this->assertSame(
            ['key-20' => 20_000, 'key-19' => 19_000, 'key-18' => 18_000],
            $out['largestEntries']
        );
    }

    /**
     * The default cap is what the endpoint has always reported; a change here
     * changes what tools/smoke-prod.sh sees.
     */
    public function testTheDefaultCapIsFifteen(): void
    {
        $entries = [];
        foreach (range(1, 40) as $i) {
            $entries[] = ['info' => 'key-' . $i, 'mem_size' => $i];
        }

        $out = ApcuStatus::summarize($this->sma(), $this->cache(), $this->entryList($entries));

        $this->assertCount(15, $out['largestEntries']);
        $this->assertSame(ApcuStatus::LARGEST_ENTRIES, count($out['largestEntries']));
    }

    /**
     * apcu_cache_info() is the expensive call — it walks every entry — so the
     * payload has to survive a caller that did not make it, and an entry list
     * without the fields it reads.
     */
    public function testAnAbsentOrMalformedEntryListYieldsAnEmptyList(): void
    {
        $this->assertSame([], ApcuStatus::summarize($this->sma(), $this->cache())['largestEntries']);
        $this->assertSame([], ApcuStatus::summarize($this->sma(), $this->cache(), false)['largestEntries']);
        $this->assertSame([], ApcuStatus::summarize($this->sma(), $this->cache(), ['cache_list' => 'nope'])
            ['largestEntries']);

        $out = ApcuStatus::summarize(
            $this->sma(),
            $this->cache(),
            $this->entryList([['no_info_key' => 1], ['info' => 'sized-nowhere']])
        );
        $this->assertSame(['sized-nowhere' => 0], $out['largestEntries']);
    }

    /**
     * Key order is part of the contract: two front controllers now serve this
     * document (laminas-mvc and the Symfony kernel), and the way to check they
     * agree is to diff the two responses. Reordering would make that diff lie
     * about a change nobody made.
     */
    public function testKeyOrderIsFixed(): void
    {
        $out = ApcuStatus::summarize($this->sma(), $this->cache(), $this->entryList([]));

        $this->assertSame(
            [
                'apcuEnabled',
                'totalBytes',
                'usedBytes',
                'availBytes',
                'percentUsed',
                'entries',
                'hits',
                'misses',
                'expunges',
                'uptimeSeconds',
                'largestEntries',
            ],
            array_keys($out)
        );
    }
}

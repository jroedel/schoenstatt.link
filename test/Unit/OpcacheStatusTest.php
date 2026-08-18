<?php

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;
use SionModel\Cache\OpcacheStatus;

require_once __DIR__ . '/../../module/SionModel/src/Cache/OpcacheStatus.php';

/**
 * Pins the arithmetic behind the `opcache` block of /sm/cache-status.
 *
 * Worth testing rather than eyeballing because the endpoint is what
 * tools/smoke-prod.sh warns on, and every value here is a ratio with a
 * plausible-looking wrong answer: a percentage against the wrong denominator, or
 * a division by a zero that only occurs when OPcache is cold.
 *
 * The class touches no framework code, so this lives in the vendor-free unit
 * suite and requires the file directly.
 */
class OpcacheStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        OpcacheStatus::$clock = null;
    }

    /** Shape of a live opcache_get_status(false), trimmed to what is read. */
    private function rawStatus(array $overrides = []): array
    {
        return array_replace_recursive([
            'opcache_enabled' => true,
            'cache_full' => false,
            'restart_pending' => false,
            'restart_in_progress' => false,
            'memory_usage' => [
                'used_memory' => 30_000_000,
                'free_memory' => 90_000_000,
                'wasted_memory' => 10_000_000,
                'current_wasted_percentage' => 7.4567,
            ],
            'interned_strings_usage' => [
                'buffer_size' => 8_388_608,
                'used_memory' => 6_291_456,
                'free_memory' => 2_097_152,
                'number_of_strings' => 74_545,
            ],
            'opcache_statistics' => [
                'num_cached_scripts' => 1273,
                'num_cached_keys' => 2427,
                'max_cached_keys' => 16229,
                'hits' => 990,
                'misses' => 10,
                'start_time' => 1_000_000,
                'oom_restarts' => 0,
                'hash_restarts' => 0,
                'manual_restarts' => 0,
            ],
            'jit' => ['on' => false],
        ], $overrides);
    }

    private function ini(array $overrides = []): array
    {
        return $overrides + [
            'opcache.validate_timestamps' => '1',
            'opcache.revalidate_freq' => '2',
            'opcache.max_accelerated_files' => '10000',
            'opcache.interned_strings_buffer' => '8',
        ];
    }

    /**
     * opcache_get_status() returns false — not an array — when OPcache is off,
     * which is the case the controller hands straight through.
     */
    public function testReportsDisabledForFalse(): void
    {
        $this->assertSame(['enabled' => false], OpcacheStatus::summarize(false));
    }

    /** The controller passes null when the function does not even exist. */
    public function testReportsDisabledForNull(): void
    {
        $this->assertSame(['enabled' => false], OpcacheStatus::summarize(null));
    }

    public function testReportsDisabledWhenTheFlagIsOff(): void
    {
        $status = $this->rawStatus(['opcache_enabled' => false]);

        $this->assertSame(['enabled' => false], OpcacheStatus::summarize($status));
    }

    /**
     * OPcache reports no segment total, so it has to be reconstructed. Wasted
     * memory is part of the segment and is not available for new scripts, so it
     * belongs on the used side of the ratio — treating it as free would report
     * headroom that cannot be allocated.
     */
    public function testMemoryTotalIsTheSumOfAllThreeAndWastedCountsAsUsed(): void
    {
        $out = OpcacheStatus::summarize($this->rawStatus(), $this->ini());

        $this->assertSame(130_000_000, $out['memoryTotalBytes']);
        $this->assertSame(30_000_000, $out['memoryUsedBytes']);
        $this->assertSame(90_000_000, $out['memoryFreeBytes']);
        $this->assertSame(10_000_000, $out['memoryWastedBytes']);
        //(30M + 10M wasted) / 130M
        $this->assertSame(30.8, $out['memoryPercentUsed']);
        $this->assertSame(7.5, $out['wastedPercent']);
    }

    /**
     * The trap this class exists to avoid. PHP rounds the script hash table up to
     * the next prime, so a configured max_accelerated_files of 10000 becomes a
     * real ceiling of 16229. Measuring against the configured number would have
     * reported 24% used where the truth is 15% — understating headroom on a cache
     * that throws everything away when it fills.
     */
    public function testKeyUsageIsMeasuredAgainstTheRealTableSizeNotTheConfiguredOne(): void
    {
        $out = OpcacheStatus::summarize($this->rawStatus(), $this->ini());

        $this->assertSame(16229, $out['maxCachedKeys']);
        $this->assertSame(10000, $out['maxAcceleratedFilesConfigured']);
        $this->assertSame(15.0, $out['keysPercentUsed'], '2427/16229, not 2427/10000');
    }

    /**
     * Keys exceed scripts because one script can occupy several (path plus
     * resolved realpath), so the key count is the one to compare against the
     * ceiling.
     */
    public function testReportsScriptsAndKeysSeparately(): void
    {
        $out = OpcacheStatus::summarize($this->rawStatus(), $this->ini());

        $this->assertSame(1273, $out['cachedScripts']);
        $this->assertSame(2427, $out['cachedKeys']);
    }

    public function testHitRateIsOverLookupsNotHits(): void
    {
        $out = OpcacheStatus::summarize($this->rawStatus(), $this->ini());

        $this->assertSame(99.0, $out['hitRatePercent'], '990/(990+10)');
    }

    /**
     * A cold cache has zero lookups and a zero-sized interned buffer is possible
     * too; neither may become a division by zero.
     */
    public function testRatiosAreNullRatherThanDividingByZero(): void
    {
        $out = OpcacheStatus::summarize($this->rawStatus([
            'memory_usage' => ['used_memory' => 0, 'free_memory' => 0, 'wasted_memory' => 0],
            'interned_strings_usage' => ['buffer_size' => 0, 'used_memory' => 0],
            'opcache_statistics' => ['hits' => 0, 'misses' => 0, 'num_cached_keys' => 0, 'max_cached_keys' => 0],
        ]), $this->ini());

        $this->assertNull($out['memoryPercentUsed']);
        $this->assertNull($out['keysPercentUsed']);
        $this->assertNull($out['hitRatePercent']);
        $this->assertNull($out['internedPercentUsed']);
    }

    /**
     * The buffer size is reported, not just the ratio over it — and this is the
     * assertion that would have failed before 2026-08-18, when the endpoint gave a
     * percentage whose denominator was invisible.
     *
     * A ratio cannot show its own denominator changing. Raising
     * opcache.interned_strings_buffer from 8 MB to 32 MB moves nothing else in this
     * payload in a way that distinguishes it from the app simply interning fewer
     * strings, so "did the setting take effect" was unanswerable from here.
     */
    public function testTheInternedBufferSizeIsReportedAndNotJustTheRatioOverIt(): void
    {
        $out = OpcacheStatus::summarize($this->rawStatus(), $this->ini());

        $this->assertSame(8_388_608, $out['internedBufferBytes'], '8 MiB, exactly');
        $this->assertSame(6_291_456, $out['internedUsedBytes']);
        $this->assertSame(2_097_152, $out['internedFreeBytes']);
        $this->assertSame(75.0, $out['internedPercentUsed'], '6 MiB of 8 MiB');
        $this->assertSame(74_545, $out['internedStrings']);
    }

    /**
     * The configured size and the allocated size are separate readings, because
     * they can disagree — and the disagreement is the interesting state.
     *
     * opcache.interned_strings_buffer is PHP_INI_SYSTEM: read once, when the process
     * starts. So after the ini file is changed, a pool that has not recycled reports
     * the NEW configured value from ini_get() while still running on the OLD buffer.
     * Reporting one number would hide exactly the transition anyone raising this
     * setting is trying to observe.
     */
    public function testConfiguredAndAllocatedBufferSizesAreReportedSeparately(): void
    {
        $out = OpcacheStatus::summarize(
            $this->rawStatus(),
            $this->ini(['opcache.interned_strings_buffer' => '32'])
        );

        $this->assertSame(32, $out['internedBufferConfiguredMb'], 'what the ini asks for');
        $this->assertSame(8_388_608, $out['internedBufferBytes'], 'what this pool actually allocated');
    }

    /**
     * The raw start time, kept as well as the derived uptime, because it is the only
     * per-segment identity OPcache exposes and this host runs three pools. Uptime
     * cannot serve as that identity: it differs between two polls of the same
     * segment, which is precisely what a sampling tool must not treat as two pools.
     */
    public function testStartTimeIsReportedAsAStableSegmentIdentity(): void
    {
        OpcacheStatus::$clock = fn(): int => 1_000_500;
        $first = OpcacheStatus::summarize($this->rawStatus(), $this->ini());

        OpcacheStatus::$clock = fn(): int => 1_000_900;
        $second = OpcacheStatus::summarize($this->rawStatus(), $this->ini());

        $this->assertSame(1_000_000, $first['startTimeUnix']);
        $this->assertSame($first['startTimeUnix'], $second['startTimeUnix'], 'same segment, same key');
        $this->assertNotSame($first['uptimeSeconds'], $second['uptimeSeconds'], 'uptime moves, so it is no key');
    }

    /** A never-started cache has no identity to report either. */
    public function testStartTimeIsNullWithoutAStartTime(): void
    {
        $out = OpcacheStatus::summarize(
            $this->rawStatus(['opcache_statistics' => ['start_time' => 0]]),
            $this->ini()
        );

        $this->assertNull($out['startTimeUnix']);
    }

    /**
     * These are the OPcache equivalent of APCu's expunges: OPcache does not slow
     * down when it runs out of room, it restarts and discards everything, and the
     * counter is the only lasting evidence.
     */
    public function testRestartCountersArePassedThrough(): void
    {
        $out = OpcacheStatus::summarize($this->rawStatus([
            'opcache_statistics' => ['oom_restarts' => 3, 'hash_restarts' => 2, 'manual_restarts' => 1],
        ]), $this->ini());

        $this->assertSame(3, $out['oomRestarts']);
        $this->assertSame(2, $out['hashRestarts']);
        $this->assertSame(1, $out['manualRestarts']);
    }

    public function testCacheFullAndRestartFlagsAreBooleans(): void
    {
        $out = OpcacheStatus::summarize($this->rawStatus([
            'cache_full' => true,
            'restart_pending' => true,
            'restart_in_progress' => true,
        ]), $this->ini());

        $this->assertTrue($out['cacheFull']);
        $this->assertTrue($out['restartPending']);
        $this->assertTrue($out['restartInProgress']);
    }

    /**
     * ini_get() yields strings, and this flag decides whether a deploy needs a
     * pool restart — so '0' must not read as true.
     */
    public function testValidateTimestampsComesBackAsABoolean(): void
    {
        $on = OpcacheStatus::summarize($this->rawStatus(), $this->ini(['opcache.validate_timestamps' => '1']));
        $off = OpcacheStatus::summarize($this->rawStatus(), $this->ini(['opcache.validate_timestamps' => '0']));
        $empty = OpcacheStatus::summarize($this->rawStatus(), $this->ini(['opcache.validate_timestamps' => '']));

        $this->assertTrue($on['validateTimestamps']);
        $this->assertFalse($off['validateTimestamps']);
        $this->assertFalse($empty['validateTimestamps']);
    }

    public function testUptimeIsMeasuredFromStartTime(): void
    {
        OpcacheStatus::$clock = fn(): int => 1_000_500;

        $out = OpcacheStatus::summarize($this->rawStatus(), $this->ini());

        $this->assertSame(500, $out['uptimeSeconds']);
    }

    /** A never-started cache reports no start time; uptime is unknown, not 0. */
    public function testUptimeIsNullWithoutAStartTime(): void
    {
        $out = OpcacheStatus::summarize(
            $this->rawStatus(['opcache_statistics' => ['start_time' => 0]]),
            $this->ini()
        );

        $this->assertNull($out['uptimeSeconds']);
    }

    /**
     * Defensive: OPcache builds vary, and a missing sub-array must not take the
     * whole maintenance endpoint down with it.
     */
    public function testSurvivesAStatusMissingItsSubArrays(): void
    {
        $out = OpcacheStatus::summarize(['opcache_enabled' => true]);

        $this->assertTrue($out['enabled']);
        $this->assertSame(0, $out['cachedScripts']);
        $this->assertSame(0, $out['memoryTotalBytes']);
        $this->assertNull($out['memoryPercentUsed']);
        $this->assertSame(0, $out['internedBufferBytes']);
        $this->assertNull($out['internedPercentUsed']);
        $this->assertNull($out['startTimeUnix']);
        $this->assertFalse($out['jitEnabled']);
    }
}

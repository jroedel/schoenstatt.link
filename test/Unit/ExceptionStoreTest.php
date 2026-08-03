<?php

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;
use SionModel\Error\ExceptionRecord;
use SionModel\Error\ExceptionStore;
use SionModel\Error\RecordOutcome;

/**
 * Pins SionModel\Error\ExceptionStore against a real filesystem temp
 * directory. Per the class docblock, every method here is failure-tolerant
 * and must return rather than throw, because this code runs while the
 * application is already broken.
 *
 * The class files are required directly: test/bootstrap.php deliberately
 * avoids vendor/autoload.php so the suite stays valid even when vendor/ is
 * mid-migration.
 */
class ExceptionStoreTest extends TestCase
{
    /** @var string */
    private $dir;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../module/SionModel/src/Error/RecordOutcome.php';
        require_once __DIR__ . '/../../module/SionModel/src/Error/ExceptionRecord.php';
        require_once __DIR__ . '/../../module/SionModel/src/Error/ExceptionStore.php';
    }

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/exception-store-test-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->dir);
    }

    private function removeRecursively(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (! is_dir($path)) {
            return;
        }
        foreach (scandir($path) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $this->removeRecursively($path . '/' . $entry);
        }
        @rmdir($path);
    }

    /**
     * @param string $fingerprint 8 lowercase hex chars
     * @param array  $overrides   attribute overrides for ExceptionRecord
     */
    private function makeRecord(string $fingerprint, array $overrides = []): ExceptionRecord
    {
        $chain = [
            [
                'class'   => 'RuntimeException',
                'message' => $overrides['message'] ?? 'boom',
                'file'    => 'SomeModule/SomeClass.php',
                'line'    => 42,
                'trace'   => $overrides['trace'] ?? "#0 {main}",
            ],
        ];
        unset($overrides['message'], $overrides['trace']);

        return new ExceptionRecord($fingerprint, $chain, $overrides);
    }

    public function testFirstRecordIsNewWithCountOneAndWritesTheExpectedFiles(): void
    {
        $store = new ExceptionStore($this->dir);
        $record = $this->makeRecord('aaaaaaaa', ['route' => 'r', 'occurred_at' => '2026-01-01T00:00:00+00:00']);

        $outcome = $store->record($record);

        $this->assertInstanceOf(RecordOutcome::class, $outcome);
        $this->assertTrue($outcome->isNew());
        $this->assertSame(1, $outcome->getCount());

        $fpDir = $this->dir . '/aaaaaaaa';
        $this->assertFileExists($fpDir . '/meta.json');
        $this->assertFileExists($fpDir . '/first.txt');
        $this->assertFileExists($fpDir . '/last.txt');
        $this->assertFileExists($fpDir . '/recent/1.txt');
    }

    /**
     * first.txt is the original context and must never be overwritten;
     * last.txt answers "is it still happening" and must always advance.
     */
    public function testSecondRecordOfSameFingerprintIsNotNewAndDoesNotOverwriteFirstTxt(): void
    {
        $store = new ExceptionStore($this->dir);
        $first = $this->makeRecord('aaaaaaaa', [
            'occurred_at' => '2026-01-01T00:00:00+00:00',
            'message'     => 'first-content',
        ]);
        $second = $this->makeRecord('aaaaaaaa', [
            'occurred_at' => '2026-01-02T00:00:00+00:00',
            'message'     => 'second-content',
        ]);

        $store->record($first);
        $outcome = $store->record($second);

        $this->assertFalse($outcome->isNew());
        $this->assertSame(2, $outcome->getCount());

        $fpDir = $this->dir . '/aaaaaaaa';
        $this->assertStringContainsString('first-content', file_get_contents($fpDir . '/first.txt'));
        $this->assertStringNotContainsString('second-content', file_get_contents($fpDir . '/first.txt'));
        $this->assertStringContainsString('second-content', file_get_contents($fpDir . '/last.txt'));
    }

    public function testFirstSeenIsPreservedAndLastSeenAdvancesAcrossOccurrences(): void
    {
        $store = new ExceptionStore($this->dir);
        $store->record($this->makeRecord('aaaaaaaa', ['occurred_at' => '2026-01-01T00:00:00+00:00']));
        $store->record($this->makeRecord('aaaaaaaa', ['occurred_at' => '2026-01-02T00:00:00+00:00']));

        $meta = $store->readMeta('aaaaaaaa');

        $this->assertSame('2026-01-01T00:00:00+00:00', $meta['first_seen']);
        $this->assertSame('2026-01-02T00:00:00+00:00', $meta['last_seen']);
        $this->assertSame(2, $meta['count']);
    }

    /**
     * notified_at / notified_counts are the notifier's bookkeeping. A new
     * occurrence must not reset them, or the notifier would re-send the
     * email on the very next hit.
     */
    public function testNotifiedBookkeepingSurvivesASubsequentRecord(): void
    {
        $store = new ExceptionStore($this->dir);
        $store->record($this->makeRecord('aaaaaaaa'));
        $store->markNotified('aaaaaaaa', 1);

        $store->record($this->makeRecord('aaaaaaaa'));
        $meta = $store->readMeta('aaaaaaaa');

        $this->assertSame([1], $meta['notified_counts']);
        $this->assertNotNull($meta['notified_at']);
    }

    /**
     * The ring buffer: newest write-up is always recent/1.txt, and it never
     * exceeds ringSize files.
     */
    public function testRingBufferRotatesAndNeverExceedsRingSize(): void
    {
        $store = new ExceptionStore($this->dir, 500, 2);

        $store->record($this->makeRecord('aaaaaaaa', ['message' => 'occurrence-1']));
        $store->record($this->makeRecord('aaaaaaaa', ['message' => 'occurrence-2']));
        $store->record($this->makeRecord('aaaaaaaa', ['message' => 'occurrence-3']));

        $ring = $this->dir . '/aaaaaaaa/recent';
        $this->assertStringContainsString('occurrence-3', file_get_contents($ring . '/1.txt'));
        $this->assertStringContainsString('occurrence-2', file_get_contents($ring . '/2.txt'));
        $this->assertFileDoesNotExist($ring . '/3.txt');
    }

    /**
     * maxFingerprints is a hard ceiling: at capacity a NEW fingerprint is
     * rejected (with a .overflow breadcrumb) while an EXISTING fingerprint
     * still records normally.
     */
    public function testMaxFingerprintsIsEnforcedForNewFingerprintsButNotForExistingOnes(): void
    {
        $store = new ExceptionStore($this->dir, 2);

        $this->assertInstanceOf(RecordOutcome::class, $store->record($this->makeRecord('aaaaaaaa')));
        $this->assertInstanceOf(RecordOutcome::class, $store->record($this->makeRecord('bbbbbbbb')));

        // at capacity now; a brand new fingerprint must be rejected
        $this->assertNull($store->record($this->makeRecord('cccccccc')));
        $this->assertFileExists($this->dir . '/.overflow');

        // but an existing fingerprint must still record
        $outcome = $store->record($this->makeRecord('aaaaaaaa'));
        $this->assertNotNull($outcome);
        $this->assertSame(2, $outcome->getCount());
    }

    public function testClaimEmailSlotAllowsUpToTheHourlyCeilingThenRefuses(): void
    {
        $store = new ExceptionStore($this->dir);

        $this->assertTrue($store->claimEmailSlot(2));
        $this->assertTrue($store->claimEmailSlot(2));
        $this->assertFalse($store->claimEmailSlot(2));
    }

    public function testOpenBreakerMakesIsBreakerOpenTrue(): void
    {
        $store = new ExceptionStore($this->dir);

        $this->assertFalse($store->isBreakerOpen());
        $store->openBreaker(900);
        $this->assertTrue($store->isBreakerOpen());
    }

    public function testMarkNotifiedMergesAndDeduplicatesCountsAndAcceptsIntOrArray(): void
    {
        $store = new ExceptionStore($this->dir);
        $store->record($this->makeRecord('aaaaaaaa'));

        $this->assertTrue($store->markNotified('aaaaaaaa', 1));
        $this->assertTrue($store->markNotified('aaaaaaaa', [10, 1, 25]));

        $meta = $store->readMeta('aaaaaaaa');
        $this->assertSame([1, 10, 25], $meta['notified_counts']);
    }

    public function testMarkNotifiedClearsAPreviouslyRecordedNotifyError(): void
    {
        $store = new ExceptionStore($this->dir);
        $store->record($this->makeRecord('aaaaaaaa'));

        $this->assertTrue($store->noteNotifyError('aaaaaaaa', 'smtp connection refused'));
        $this->assertArrayHasKey('notify_error', $store->readMeta('aaaaaaaa'));

        $this->assertTrue($store->markNotified('aaaaaaaa', 1));
        $meta = $store->readMeta('aaaaaaaa');
        $this->assertArrayNotHasKey('notify_error', $meta);
        $this->assertArrayNotHasKey('notify_error_at', $meta);
    }

    public function testWriteUpLongerThanMaxWriteUpBytesIsTruncated(): void
    {
        $store = new ExceptionStore($this->dir, 500, 3, 4096);
        $record = $this->makeRecord('aaaaaaaa', ['message' => str_repeat('x', 20000)]);

        $store->record($record);

        $contents = file_get_contents($this->dir . '/aaaaaaaa/last.txt');
        $this->assertLessThanOrEqual(4096 + strlen("\n[truncated]\n"), strlen($contents));
        $this->assertStringEndsWith("[truncated]\n", $contents);
    }

    public function testInvalidFingerprintIsRejectedByRecordAndReadMeta(): void
    {
        $store = new ExceptionStore($this->dir);

        $this->assertNull($store->record($this->makeRecord('NOTHEX8x')));
        $this->assertNull($store->record($this->makeRecord('short')));
        $this->assertNull($store->record($this->makeRecord('ABCDEF12')));

        $this->assertNull($store->readMeta('NOTHEX8x'));
        $this->assertFalse($store->isValidFingerprint('ABCDEF12'));
        $this->assertTrue($store->isValidFingerprint('abcdef12'));
    }

    /**
     * record() must never throw, even when the store path cannot be created
     * — e.g. an ancestor segment already exists as a plain file.
     */
    public function testRecordReturnsNullRatherThanThrowingWhenStorePathCannotBeCreated(): void
    {
        $blocker = $this->dir . '-blocker-file';
        file_put_contents($blocker, 'not a directory');

        try {
            $store = new ExceptionStore($blocker . '/sub/store');
            $outcome = $store->record($this->makeRecord('aaaaaaaa'));
            $this->assertNull($outcome);
        } finally {
            @unlink($blocker);
        }
    }

    public function testFingerprintsListsOnlyValidFingerprintDirectories(): void
    {
        $store = new ExceptionStore($this->dir);
        $store->record($this->makeRecord('aaaaaaaa'));
        $store->record($this->makeRecord('bbbbbbbb'));
        mkdir($this->dir . '/not-a-fingerprint', 0775, true);

        $fingerprints = $store->fingerprints();
        sort($fingerprints);

        $this->assertSame(['aaaaaaaa', 'bbbbbbbb'], $fingerprints);
    }
}

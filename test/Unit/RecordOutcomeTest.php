<?php

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;
use SionModel\Error\RecordOutcome;

/**
 * Pins SionModel\Error\RecordOutcome, the value object NotificationGate
 * decides from. Per its docblock, hasBeenNotified() — not isNew() — is what
 * suppresses duplicate mail, since the store marks a fingerprint notified
 * *before* attempting the send.
 *
 * The class file is required directly: test/bootstrap.php deliberately avoids
 * vendor/autoload.php so the suite stays valid even when vendor/ is mid-migration.
 */
class RecordOutcomeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../module/SionModel/src/Error/RecordOutcome.php';
    }

    public function testAccessorsReturnConstructedValues(): void
    {
        $outcome = new RecordOutcome('deadbeef', true, 1, [1]);

        $this->assertSame('deadbeef', $outcome->getFingerprint());
        $this->assertTrue($outcome->isNew());
        $this->assertSame(1, $outcome->getCount());
        $this->assertSame([1], $outcome->getNotifiedCounts());
    }

    public function testHasBeenNotifiedIsFalseWhenNotifiedCountsIsEmpty(): void
    {
        $outcome = new RecordOutcome('deadbeef', true, 1, []);

        $this->assertFalse($outcome->hasBeenNotified());
    }

    public function testHasBeenNotifiedIsTrueAssoonAsAnyCountIsRecorded(): void
    {
        // Note: hasBeenNotified() is independent of isNew() — an occurrence
        // can be a repeat (isNew === false) yet still have never notified.
        $outcome = new RecordOutcome('deadbeef', false, 5, [1]);

        $this->assertFalse($outcome->isNew());
        $this->assertTrue($outcome->hasBeenNotified());
    }

    public function testNotifiedCountsAreCoercedToIntegers(): void
    {
        $outcome = new RecordOutcome('deadbeef', false, 3, ['1', '10']);

        $this->assertSame([1, 10], $outcome->getNotifiedCounts());
    }
}

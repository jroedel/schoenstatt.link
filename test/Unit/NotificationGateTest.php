<?php

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;
use SionModel\Error\NotificationGate;
use SionModel\Error\RecordOutcome;

/**
 * Pins SionModel\Error\NotificationGate: one email the first time a
 * fingerprint is seen, then nothing until occurrence volume crosses a
 * threshold, with an ignore list for exceptions that are ordinary traffic
 * rather than bugs (e.g. BjyAuthorize\Exception\UnAuthorizedException from an
 * unauthenticated visitor touching an admin route).
 *
 * The class files are required directly: test/bootstrap.php deliberately
 * avoids vendor/autoload.php so the suite stays valid even when vendor/ is
 * mid-migration.
 */
class NotificationGateTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../module/SionModel/src/Error/RecordOutcome.php';
        require_once __DIR__ . '/../../module/SionModel/src/Error/NotificationGate.php';
    }

    public function testFirstEverOccurrenceNotifiesWithReasonFirst(): void
    {
        $gate = new NotificationGate(true, [], [10, 100]);
        $outcome = new RecordOutcome('deadbeef', true, 1, []);

        $decision = $gate->decide(['App\\Foo'], $outcome);

        $this->assertNotNull($decision);
        $this->assertSame('first', $decision['reason']);
    }

    /**
     * The "one email per fingerprint" guarantee: once notified, staying
     * below every spike threshold must decide null forever.
     */
    public function testAlreadyNotifiedFingerprintBelowEveryThresholdStaysSilent(): void
    {
        $gate = new NotificationGate(true, [], [10, 100]);
        // notified at the first occurrence (count 1); now at count 5, still
        // well under the lowest threshold of 10.
        $outcome = new RecordOutcome('deadbeef', false, 5, [1]);

        $this->assertNull($gate->decide(['App\\Foo'], $outcome));
    }

    public function testCrossingASpikeThresholdDecidesSpike(): void
    {
        $gate = new NotificationGate(true, [], [10, 100]);
        $outcome = new RecordOutcome('deadbeef', false, 10, [1]);

        $decision = $gate->decide(['App\\Foo'], $outcome);

        $this->assertNotNull($decision);
        $this->assertSame('spike', $decision['reason']);
    }

    /**
     * Marking only the highest crossed threshold would leave a lower one
     * perpetually unmarked, re-firing on every subsequent occurrence — an
     * infinite mail loop. With thresholds [10, 100] and a count of 150 where
     * nothing is marked yet (only the count-1 "first" notification is
     * recorded), both 10 and 100 must appear in 'mark'.
     */
    public function testCrossingMultipleThresholdsAtOnceMarksEveryOneOfThem(): void
    {
        $gate = new NotificationGate(true, [], [10, 100]);
        $outcome = new RecordOutcome('deadbeef', false, 150, [1]);

        $decision = $gate->decide(['App\\Foo'], $outcome);

        $this->assertNotNull($decision);
        $this->assertSame('spike', $decision['reason']);
        $this->assertSame([10, 100], $decision['mark']);
    }

    public function testThresholdAlreadyPresentInNotifiedCountsNeverRefires(): void
    {
        $gate = new NotificationGate(true, [], [10, 100]);
        // 10 has already been reported; only 100 is newly crossed.
        $outcome = new RecordOutcome('deadbeef', false, 150, [1, 10]);

        $decision = $gate->decide(['App\\Foo'], $outcome);

        $this->assertSame([100], $decision['mark']);
    }

    public function testAllThresholdsAlreadyMarkedStaysSilentEvenAtHighCount(): void
    {
        $gate = new NotificationGate(true, [], [10, 100]);
        $outcome = new RecordOutcome('deadbeef', false, 500, [1, 10, 100]);

        $this->assertNull($gate->decide(['App\\Foo'], $outcome));
    }

    /**
     * An ignored class anywhere in the chain — not just the outermost —
     * suppresses everything, including a first occurrence and a spike.
     */
    public function testIgnoredClassAnywhereInTheChainSuppressesFirstOccurrence(): void
    {
        $gate = new NotificationGate(true, ['BjyAuthorize\\Exception\\UnAuthorizedException'], [10]);
        $outcome = new RecordOutcome('deadbeef', true, 1, []);

        // The ignored class is wrapped by an outer exception, not outermost.
        $decision = $gate->decide(['App\\Controller\\Exception', 'BjyAuthorize\\Exception\\UnAuthorizedException'], $outcome);

        $this->assertNull($decision);
    }

    public function testIgnoredClassAnywhereInTheChainSuppressesSpikes(): void
    {
        $gate = new NotificationGate(true, ['BjyAuthorize\\Exception\\UnAuthorizedException'], [10]);
        $outcome = new RecordOutcome('deadbeef', false, 999, [1]);

        $decision = $gate->decide(['App\\Controller\\Exception', 'BjyAuthorize\\Exception\\UnAuthorizedException'], $outcome);

        $this->assertNull($decision);
    }

    public function testWildcardIgnorePatternMatchesByPrefix(): void
    {
        $gate = new NotificationGate(true, ['BjyAuthorize\\Exception\\*'], []);
        $outcome = new RecordOutcome('deadbeef', true, 1, []);

        $this->assertNull($gate->decide(['BjyAuthorize\\Exception\\UnAuthorizedException'], $outcome));
        // A sibling class outside the wildcard's namespace must still notify.
        $this->assertNotNull($gate->decide(['BjyAuthorize\\Other\\SomeException'], $outcome));
    }

    public function testWildcardMatchingIgnoresLeadingBackslashesOnEitherSide(): void
    {
        $outcome = new RecordOutcome('deadbeef', true, 1, []);

        $gatePatternLeadingSlash = new NotificationGate(true, ['\\BjyAuthorize\\Exception\\*'], []);
        $this->assertNull($gatePatternLeadingSlash->decide(['BjyAuthorize\\Exception\\UnAuthorizedException'], $outcome));

        $gateClassLeadingSlash = new NotificationGate(true, ['BjyAuthorize\\Exception\\*'], []);
        $this->assertNull($gateClassLeadingSlash->decide(['\\BjyAuthorize\\Exception\\UnAuthorizedException'], $outcome));
    }

    public function testExactClassIgnorePatternIgnoresLeadingBackslashesOnEitherSide(): void
    {
        $outcome = new RecordOutcome('deadbeef', true, 1, []);

        $gate = new NotificationGate(true, ['\\App\\Exception\\Boring'], []);
        $this->assertNull($gate->decide(['App\\Exception\\Boring'], $outcome));

        $gate2 = new NotificationGate(true, ['App\\Exception\\Boring'], []);
        $this->assertNull($gate2->decide(['\\App\\Exception\\Boring'], $outcome));
    }

    public function testDisabledGateSuppressesEverythingEvenAFirstOccurrence(): void
    {
        $gate = new NotificationGate(false, [], [10]);
        $outcome = new RecordOutcome('deadbeef', true, 1, []);

        $this->assertNull($gate->decide(['App\\Foo'], $outcome));
    }

    public function testDisabledGateSuppressesSpikesToo(): void
    {
        $gate = new NotificationGate(false, [], [10]);
        $outcome = new RecordOutcome('deadbeef', false, 999, [1]);

        $this->assertNull($gate->decide(['App\\Foo'], $outcome));
    }
}

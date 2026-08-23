<?php

namespace SchoenstattTest\Unit;

use App\Provenance\Assertion;
use App\Provenance\Outcome;
use App\Provenance\SourceClass;
use App\Provenance\WriteGate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Provenance/SourceClass.php';
require_once __DIR__ . '/../../src/Provenance/Outcome.php';
require_once __DIR__ . '/../../src/Provenance/Assertion.php';
require_once __DIR__ . '/../../src/Provenance/WriteGate.php';

/**
 * The ranking rule, which is the only part of the provenance layer that changes what a
 * write does.
 *
 * Worth pinning field by field rather than eyeballing, because every condition in
 * {@see WriteGate::isProtected()} has a plausible-looking wrong version and none of them
 * would fail loudly: get it too strict and an agent can never correct anything, get it too
 * loose and a scrape silently undoes a rector's phone call — which is the exact failure the
 * whole project exists to prevent, and which produces no error either way.
 *
 * Touches no framework and no database, so it lives in the vendor-free unit suite and
 * requires its four files directly.
 */
class ProvenanceWriteGateTest extends TestCase
{
    private const NOW = '2026-08-23 12:00:00';

    private function gate(): WriteGate
    {
        return new WriteGate();
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    private function standing(
        SourceClass $source,
        string $assertedOn,
        Outcome $outcome = Outcome::Corrected
    ): Assertion {
        return new Assertion(
            entity: 'association',
            entityId: 319,
            fieldGroup: 'contactInfo',
            source: $source,
            outcome: $outcome,
            assertedOn: new DateTimeImmutable($assertedOn),
            recordedOn: new DateTimeImmutable($assertedOn),
        );
    }

    /** Nothing on file means nothing to protect: a first claim always applies. */
    public function testAnEmptyRecordProtectsNothing(): void
    {
        $this->assertSame(
            Outcome::Corrected,
            $this->gate()->decide(SourceClass::Inference, null, $this->now())
        );
    }

    /** The headline case: a fresh phone call from the shrine beats a website scrape. */
    public function testAFreshFirstHandClaimWithholdsALesserSource(): void
    {
        $standing = $this->standing(SourceClass::Shrine, '2026-07-01 00:00:00');

        $this->assertSame(
            Outcome::Competing,
            $this->gate()->decide(SourceClass::Website, $standing, $this->now())
        );
    }

    /**
     * Strictly higher, not higher-or-equal.
     *
     * Two claims from the same kind of source are a fresher reading of the same thing, not a
     * dispute. If equal ranks blocked each other the record would freeze at whatever was
     * scraped earliest and no agent could ever correct its own earlier mistake.
     */
    public function testAnEqualRankAppliesRatherThanCompeting(): void
    {
        $standing = $this->standing(SourceClass::Website, '2026-08-20 00:00:00');

        $this->assertSame(
            Outcome::Corrected,
            $this->gate()->decide(SourceClass::Website, $standing, $this->now())
        );
    }

    /** A better source is never blocked by a worse one. */
    public function testAHigherRankOverridesAStandingLesserClaim(): void
    {
        $standing = $this->standing(SourceClass::Directory, '2026-08-22 00:00:00');

        $this->assertSame(
            Outcome::Corrected,
            $this->gate()->decide(SourceClass::Shrine, $standing, $this->now())
        );
    }

    /**
     * The window has to expire, or a protection becomes a lock — a 2019 phone call would
     * stop the shrine's own website correcting the number in 2026, which is the state this
     * project exists to escape.
     */
    public function testProtectionLapsesAfterTheWindow(): void
    {
        $old = $this->standing(SourceClass::Shrine, '2019-06-01 00:00:00');

        $this->assertSame(
            Outcome::Corrected,
            $this->gate()->decide(SourceClass::Website, $old, $this->now())
        );
    }

    /** The boundary, both sides of it, since an off-by-one here is invisible. */
    public function testTheWindowBoundaryIsExclusiveOnTheDayItLapses(): void
    {
        $now  = $this->now();
        $gate = $this->gate();

        $justInside = $now->modify('-' . (WriteGate::PROTECTION_DAYS - 1) . ' days');
        $justOutside = $now->modify('-' . WriteGate::PROTECTION_DAYS . ' days');

        $this->assertTrue(
            $gate->isProtected(
                SourceClass::Website,
                $this->standing(SourceClass::Shrine, $justInside->format('Y-m-d H:i:s')),
                $now
            ),
            'a claim younger than the window must still protect'
        );

        $this->assertFalse(
            $gate->isProtected(
                SourceClass::Website,
                $this->standing(SourceClass::Shrine, $justOutside->format('Y-m-d H:i:s')),
                $now
            ),
            'a claim exactly at the window must have lapsed'
        );
    }

    /**
     * A claim that was itself withheld protects nothing.
     *
     * Otherwise one refused scrape would shield the value from every later write, including
     * from the shrine itself — a competing claim would become stronger than the thing it
     * lost to.
     */
    public function testACompetingClaimProtectsNothing(): void
    {
        $refused = $this->standing(SourceClass::Shrine, '2026-08-01 00:00:00', Outcome::Competing);

        $this->assertSame(
            Outcome::Corrected,
            $this->gate()->decide(SourceClass::Inference, $refused, $this->now())
        );
    }

    /** A confirmation vouches for the stored value, so it protects like a correction. */
    public function testAConfirmationProtectsLikeACorrection(): void
    {
        $confirmed = $this->standing(SourceClass::Office, '2026-08-01 00:00:00', Outcome::Confirmed);

        $this->assertSame(
            Outcome::Competing,
            $this->gate()->decide(SourceClass::Directory, $confirmed, $this->now())
        );
    }

    /**
     * Age is measured from the observation, not from the filing.
     *
     * Otherwise the window is defeated by re-uploading old evidence: a two-year-old bulletin
     * filed this morning would protect the value for another six months.
     */
    public function testAgeIsMeasuredFromAssertedOnNotRecordedOn(): void
    {
        $staleObservationFiledToday = new Assertion(
            entity: 'association',
            entityId: 319,
            fieldGroup: 'contactInfo',
            source: SourceClass::Shrine,
            outcome: Outcome::Corrected,
            assertedOn: new DateTimeImmutable('2019-06-01 00:00:00'),
            recordedOn: new DateTimeImmutable(self::NOW),
        );

        $this->assertFalse(
            $this->gate()->isProtected(
                SourceClass::Website,
                $staleObservationFiledToday,
                $this->now()
            ),
            're-filing a 2019 observation must not buy it a fresh six months'
        );
    }

    /** The ranks themselves, since the whole rule is arithmetic on them. */
    public function testTheRankingIsStrictlyOrdered(): void
    {
        $this->assertGreaterThan(SourceClass::Office->rank(), SourceClass::Shrine->rank());
        $this->assertGreaterThan(SourceClass::Website->rank(), SourceClass::Office->rank());
        $this->assertGreaterThan(SourceClass::Directory->rank(), SourceClass::Website->rank());
        $this->assertGreaterThan(SourceClass::Inference->rank(), SourceClass::Directory->rank());
    }

    /** Only the shrine and an office are somebody vouching in person. */
    public function testOnlyTheShrineAndAnOfficeAreFirstHand(): void
    {
        $this->assertTrue(SourceClass::Shrine->isFirstHand());
        $this->assertTrue(SourceClass::Office->isFirstHand());
        $this->assertFalse(SourceClass::Website->isFirstHand());
        $this->assertFalse(SourceClass::Directory->isFirstHand());
        $this->assertFalse(SourceClass::Inference->isFirstHand());
    }
}

<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SchoenstattTest\Rules\RuleSurface;

require_once __DIR__ . '/../Rules/RuleSurface.php';

/**
 * `RuleSurface::describeMoment()` must not call a fixed timestamp "the clock".
 *
 * ## The bug this pins
 *
 * The recording writes a produced `DateTime` relative to the instant the recording was
 * taken, because a third of the date corpus is clock-derived: `'1799'` is *this month,
 * this day, this second* of the year 1799. The time-of-day was written as `<clock>`
 * whenever it fell within `CLOCK_TOLERANCE` of the reference's time-of-day.
 *
 * That test is true for five minutes a day of any fixed timestamp in the corpus.
 * `'2020-03-15'` parses to midnight, and between 00:00:00 and 00:05:00 midnight is within
 * 300 seconds of the clock — so nine entries recorded `<clock>` where the baseline says
 * `00:00:00`, and `RuleSurfaceTest` failed. The two `date-iso-datetime` cases have the
 * same window around 14:30.
 *
 * It is a latent flake that fires for roughly twenty minutes a day and is invisible the
 * rest of the time. On 2026-09-24 a CI run landed at 00:04:37, master went red, and the
 * deploy workflow's CI gate refused a production deploy — which is the gate working, but
 * over a defect in the recorder rather than in the application.
 *
 * ## Why these tests pin the reference
 *
 * A bug that depends on the wall clock cannot be caught by running at the wall clock. The
 * reference is a private static, set once per process, so reflection is how a test says
 * "pretend it is four minutes past midnight". That is the only way this failure is
 * reproducible rather than waited for.
 */
final class RuleSurfaceClockWindowTest extends TestCase
{
    //No setAccessible(): it has had no effect since PHP 8.1 and is deprecated in 8.5,
    //which is the version production and the capsule both run.
    private function pinReference(string $when): void
    {
        (new ReflectionClass(RuleSurface::class))
            ->getProperty('reference')
            ->setValue(null, new DateTimeImmutable($when));
    }

    private function describe(string $moment): string
    {
        $method = (new ReflectionClass(RuleSurface::class))->getMethod('describeMoment');

        return (string) $method->invoke(null, new DateTimeImmutable($moment));
    }

    protected function tearDown(): void
    {
        $property = (new ReflectionClass(RuleSurface::class))->getProperty('reference');
        $property->setValue(null, null);
    }

    /**
     * The exact failure: a fixed date, recorded four minutes after midnight.
     */
    public function testAFixedMidnightIsNotTheClockJustAfterMidnight(): void
    {
        $this->pinReference('2026-09-24 00:04:37 +00:00');

        self::assertSame(
            '2020-03-15 00:00:00 +00:00',
            $this->describe('2020-03-15 00:00:00 +00:00'),
            'a date five years ago cannot have taken its time from this clock'
        );
    }

    /** The second window, around the corpus's other literal time. */
    public function testAFixedAfternoonTimeIsNotTheClockAtThatTime(): void
    {
        $this->pinReference('2026-09-24 14:32:00 +00:00');

        self::assertSame(
            '2020-03-15 14:30:00 +00:00',
            $this->describe('2020-03-15 14:30:00 +00:00')
        );
    }

    /**
     * The behaviour that has to survive the fix: a genuinely clock-derived moment. `'1799'`
     * through `ToDateTime` is this month, this day, this second, of 1799 — the year moves,
     * the day does not, which is exactly why "same day" is the right guard.
     */
    public function testAClockDerivedMomentIsStillTheClock(): void
    {
        $this->pinReference('2026-09-24 14:32:00 +00:00');

        self::assertSame(
            'today+0 days, year-227 <clock> +00:00',
            $this->describe('1799-09-24 14:32:00 +00:00')
        );
    }

    /** Still the clock a few seconds either side, which is what the tolerance is for. */
    public function testTheToleranceStillAbsorbsASlowRun(): void
    {
        $this->pinReference('2026-09-24 14:32:00 +00:00');

        self::assertSame(
            'today+0 days, year-227 <clock> +00:00',
            $this->describe('1799-09-24 14:33:30 +00:00'),
            'ninety seconds is well inside CLOCK_TOLERANCE and must not be written literally'
        );
    }

    /**
     * A moment on a neighbouring day keeps its date shorthand and a literal time. Nothing
     * in the corpus produces this today, but `'tomorrow'` would, and it is midnight — so
     * without the same-day guard it would be the next thing to flake at 00:04.
     */
    public function testMidnightTomorrowKeepsItsLiteralTime(): void
    {
        $this->pinReference('2026-09-24 00:04:37 +00:00');

        self::assertSame(
            'today+1 days, year+0 00:00:00 +00:00',
            $this->describe('2026-09-25 00:00:00 +00:00')
        );
    }
}

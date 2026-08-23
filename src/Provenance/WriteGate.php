<?php

declare(strict_types=1);

namespace App\Provenance;

use DateTimeImmutable;

/**
 * Decides whether an incoming claim may change the stored value, or is only recorded.
 *
 * This is the rule that makes an authoritative tip-off worth more than a scrape, and it is
 * the only part of the provenance layer that changes what a write *does*. Everything else
 * observes.
 *
 * ## The rule
 *
 * A claim is recorded but **not applied** when a standing claim on the same field group
 *
 *   1. comes from a **strictly** higher-ranked source, and
 *   2. actually vouched for the stored value (`confirmed` or `corrected` — a previously
 *      refused claim protects nothing), and
 *   3. is younger than {@see self::PROTECTION_DAYS} measured from when it was *asserted*.
 *
 * Otherwise it applies. Three notes on why each condition is shaped that way:
 *
 * **Strictly higher, not higher-or-equal.** Two claims from the same kind of source are a
 * fresher reading of the same thing, not a dispute to adjudicate — a second website check
 * must be able to correct the first, or the record freezes at whatever was scraped earliest.
 *
 * **Asserted, not recorded.** Otherwise the window is defeated by re-filing old evidence:
 * an agent could re-upload a two-year-old bulletin and have it protect the value for another
 * six months. {@see Assertion::ageInDays()}.
 *
 * **The window exists at all** because a protection with no expiry is a lock. A rector who
 * confirmed a phone number in 2019 and has not been heard from since should not prevent the
 * shrine's own website from correcting it in 2026 — that is precisely the state this project
 * exists to escape.
 *
 * ## What this deliberately does not do
 *
 * It does not refuse the request. A `409` would protect the value and lose the finding, and
 * the finding is the scarce thing here: an agent that noticed a discrepancy has done useful
 * work whether or not it wins. The claim is stored as {@see Outcome::Competing} and shows up
 * for review. The API answers `200` with the value unchanged and says so — a behaviour
 * change for an existing caller, which is why it is written into the schema document rather
 * than left to be discovered.
 */
final class WriteGate
{
    /**
     * How long a first-hand claim protects a value from a lesser source.
     *
     * Six months: long enough that a rector's confirmation stands for a season, short enough
     * that a single 2019 phone call cannot freeze a record for a decade. A policy number, and
     * the reason it is a named constant rather than a literal is that it will be argued about.
     */
    public const PROTECTION_DAYS = 180;

    /**
     * @param Assertion|null $standing the newest assertion for this field group, or null if
     *                                 nothing has ever been claimed about it
     */
    public function decide(
        SourceClass $incoming,
        ?Assertion $standing,
        DateTimeImmutable $now
    ): Outcome {
        if (! $this->isProtected($incoming, $standing, $now)) {
            return Outcome::Corrected;
        }

        return Outcome::Competing;
    }

    /**
     * Whether a standing claim shields the stored value from `$incoming`.
     *
     * Split out from {@see self::decide()} because it is the half worth asserting about
     * directly in tests, and because the shrine page wants to say "protected until <date>"
     * without asking a question about a hypothetical write.
     */
    public function isProtected(
        SourceClass $incoming,
        ?Assertion $standing,
        DateTimeImmutable $now
    ): bool {
        if (null === $standing) {
            return false;
        }

        if (! $standing->outcome->verifiesTheStoredValue()) {
            return false;
        }

        if ($standing->source->rank() <= $incoming->rank()) {
            return false;
        }

        return $standing->ageInDays($now) < self::PROTECTION_DAYS;
    }
}

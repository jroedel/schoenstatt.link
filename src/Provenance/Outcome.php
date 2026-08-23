<?php

declare(strict_types=1);

namespace App\Provenance;

/**
 * What an assertion did to the stored value.
 *
 * Three cases rather than two, and the third is the one that justifies the table being
 * append-only: a claim can be *kept without being applied*. An agent that finds a different
 * phone number on a website a month after the rector confirmed the current one has found
 * something worth keeping and has not found grounds to overwrite a better source.
 */
enum Outcome: string
{
    /**
     * Checked; the stored value was already right. Nothing was written to the entity.
     *
     * This is the case the API could not express at all before — a `PATCH` sending a value
     * already stored answers `changed: []` and writes nothing, so "I checked this today and
     * it is still correct" left no trace anywhere.
     */
    case Confirmed = 'confirmed';

    /** Checked; the value changed. The entity write happened. */
    case Corrected = 'corrected';

    /**
     * A lower-ranked source contradicted a fresher, higher-ranked claim. The finding is
     * recorded; the stored value was left alone. {@see WriteGate}.
     */
    case Competing = 'competing';

    /** Whether this outcome means the entity itself was written. */
    public function wroteTheEntity(): bool
    {
        return self::Corrected === $this;
    }

    /**
     * Whether this outcome counts as somebody having checked the group.
     *
     * `competing` deliberately does not: the claim is on file, but the *stored* value has
     * not been vouched for by it, and reporting a refused scrape as "verified today" on the
     * shrine page would be a lie in the one place a pilgrim reads.
     */
    public function verifiesTheStoredValue(): bool
    {
        return self::Confirmed === $this || self::Corrected === $this;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $o): string => $o->value, self::cases());
    }
}

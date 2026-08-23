<?php

declare(strict_types=1);

namespace App\Provenance;

/**
 * Where a claim about a shrine's data came from, and how much it is worth against a
 * competing claim.
 *
 * The rank is the whole point. Every field on an association is writable by an
 * administrator through the moderator form and by an agent through `PATCH
 * /api/v3/associations/{id}`, and until this existed the two were indistinguishable
 * afterwards: a nightly scrape and a rector's phone call produced byte-identical rows and
 * silently overwrote each other in whatever order they happened to arrive.
 *
 * The ranks are a **policy, not a fact about the data**, which is why they live here rather
 * than in the schema (see database/db9.0.sql). Revising one is a code change with a test
 * behind it, not a migration.
 *
 * The gaps between the numbers are deliberate. A new class — a diocesan spreadsheet, say,
 * or a verified pilgrim report — should be insertable without renumbering the others, and
 * renumbering is exactly how a ranking quietly changes meaning.
 */
enum SourceClass: string
{
    /** The shrine itself: its rector, its staff, someone answering its phone. */
    case Shrine = 'shrine';

    /** A diocesan, national or international office speaking about a shrine under it. */
    case Office = 'office';

    /** The shrine's own website or its own social account. Authoritative but often stale. */
    case Website = 'website';

    /** A third-party directory — masstimes.org, a diocesan listing. Second-hand by design. */
    case Directory = 'directory';

    /** An agent's inference: derived, guessed, or assembled from indirect evidence. */
    case Inference = 'inference';

    /**
     * Higher wins. Only a *strictly* higher rank protects a stored value — see
     * {@see WriteGate}, and note that equal ranks deliberately do not block each other,
     * because a second observation from the same kind of source is a fresher reading of the
     * same thing rather than a contradiction to adjudicate.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Shrine    => 100,
            self::Office    => 80,
            self::Website   => 50,
            self::Directory => 40,
            self::Inference => 10,
        };
    }

    /**
     * Whether a human at the shrine or an office stands behind this.
     *
     * The distinction the shrine page needs: "verified by the shrine in March" and "read off
     * their website in March" are both true and only one of them is worth showing a pilgrim
     * as a verification.
     */
    public function isFirstHand(): bool
    {
        return match ($this) {
            self::Shrine, self::Office => true,
            default                    => false,
        };
    }

    /** @return list<string> every value, for a schema document or an InArray haystack */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}

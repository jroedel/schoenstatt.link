<?php

declare(strict_types=1);

namespace App\Provenance;

use DateTimeImmutable;

use function array_values;

/**
 * The one thing a write path talks to: plan a write, then record what it did.
 *
 * Two calls, deliberately separate. {@see self::assess()} answers "what may I write?" before
 * anything is stored, and {@see self::commit()} files the assertions after the caller has
 * actually written. Collapsing them into one method would mean recording a `corrected`
 * outcome for a write that then failed — and the whole value of this table is that its rows
 * are true.
 *
 * ## What a caller does
 *
 * ```php
 * $assessment = $recorder->assess('association', $id, $changedFields, $groups, $source, $now);
 * $values     = array_intersect_key($values, array_flip($assessment->writableFields));
 * // ... write $values ...
 * $recorder->commit($assessment, 'association', $id, $source, $assertedOn, $now, $url, $note, $userId);
 * ```
 *
 * A confirmation — nothing changed — goes through {@see self::assessConfirmation()} instead,
 * and that is the case the API had no way to express at all until now.
 */
final class Recorder
{
    public function __construct(
        private readonly ProvenanceStore $store,
        private readonly WriteGate $gate,
    ) {
    }

    /**
     * Plan a write that changes something.
     *
     * Each field is mapped to its group; each group is put to {@see WriteGate}; the fields of
     * a protected group are withheld and the rest are cleared to write.
     *
     * **Ungrouped fields are always writable.** `name`, `kind`, `country`, `parentId` and
     * `geoPoint` belong to no group ({@see FieldGroups}), so there is no standing claim to
     * weigh and nothing to protect them with. That is the right answer rather than a gap:
     * they are identity rather than perishable contact detail, and gating an association's
     * `kind` behind a six-month freshness window would be protecting the wrong thing.
     *
     * @param list<string> $changedFields
     */
    public function assess(
        string $entity,
        int $entityId,
        array $changedFields,
        FieldGroups $groups,
        SourceClass $source,
        DateTimeImmutable $now,
    ): Assessment {
        $outcomes = [];
        $writable = [];
        $withheld = [];

        foreach ($changedFields as $field) {
            $group = $groups->groupFor($field);

            if (null === $group) {
                $writable[] = $field;
                continue;
            }

            if (! isset($outcomes[$group])) {
                $standing         = $this->store->latestFor($entity, $entityId, $group);
                $outcomes[$group] = $this->gate->decide($source, $standing, $now);
            }

            if (Outcome::Competing === $outcomes[$group]) {
                $withheld[] = $field;
                continue;
            }

            $writable[] = $field;
        }

        return new Assessment($outcomes, array_values($writable), array_values($withheld));
    }

    /**
     * Plan a confirmation: the caller checked these fields and every one already held the
     * right value.
     *
     * The gate is not consulted, and that is not an oversight. Confirming a value writes
     * nothing, so there is nothing for a better source to protect — and a low-ranked
     * confirmation is *information* rather than a threat. An agent reporting "the website
     * still shows the number the rector gave us" is corroboration, and refusing to record it
     * because the rector outranks the website would discard the cheapest evidence available.
     *
     * Note this takes the fields the caller **asserted about**, not the ones that changed —
     * by definition none changed.
     *
     * @param list<string> $assertedFields
     */
    public function assessConfirmation(array $assertedFields, FieldGroups $groups): Assessment
    {
        $outcomes = [];
        foreach ($groups->groupsFor($assertedFields) as $group) {
            $outcomes[$group] = Outcome::Confirmed;
        }

        return new Assessment($outcomes, [], []);
    }

    /**
     * File one assertion per group in the assessment. Returns the ids written.
     *
     * @return list<int>
     */
    public function commit(
        Assessment $assessment,
        string $entity,
        int $entityId,
        SourceClass $source,
        DateTimeImmutable $assertedOn,
        DateTimeImmutable $now,
        ?string $sourceUrl = null,
        ?string $sourceNote = null,
        ?int $recordedBy = null,
    ): array {
        $ids = [];
        foreach ($assessment->outcomes as $group => $outcome) {
            $ids[] = $this->store->record(new Assertion(
                entity: $entity,
                entityId: $entityId,
                fieldGroup: (string) $group,
                source: $source,
                outcome: $outcome,
                assertedOn: $assertedOn,
                recordedOn: $now,
                sourceUrl: $sourceUrl,
                sourceNote: $sourceNote,
                recordedBy: $recordedBy,
            ));
        }

        return $ids;
    }
}

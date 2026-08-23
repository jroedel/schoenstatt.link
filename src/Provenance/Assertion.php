<?php

declare(strict_types=1);

namespace App\Provenance;

use DateTimeImmutable;

/**
 * One claim about one field group of one entity: who said so, on what evidence, when it was
 * true, and what it did to the stored value.
 *
 * A row of `sch_provenance`, in both directions — {@see ProvenanceStore} writes these and
 * hands them back. Immutable, because the table is append-only: correcting an assertion
 * means filing a better one, not editing the record of what somebody believed in March.
 *
 * `assertedOn` and `recordedOn` are separate on purpose. An agent reading a parish bulletin
 * published in March and filing it in August is asserting something about March, and a
 * confirmation is worth what its observation is worth rather than what its upload is worth.
 * Both are UTC.
 */
final class Assertion
{
    public function __construct(
        public readonly string $entity,
        public readonly int $entityId,
        public readonly string $fieldGroup,
        public readonly SourceClass $source,
        public readonly Outcome $outcome,
        public readonly DateTimeImmutable $assertedOn,
        public readonly DateTimeImmutable $recordedOn,
        public readonly ?string $sourceUrl = null,
        public readonly ?string $sourceNote = null,
        public readonly ?int $recordedBy = null,
        public readonly ?int $provenanceId = null,
    ) {
    }

    /**
     * Age in days of the *observation*, not of the record.
     *
     * Deliberately measured from `assertedOn`: a six-month-old bulletin uploaded this
     * morning is six months old, and the protection window in {@see WriteGate} would
     * otherwise be trivially defeated by re-filing old evidence.
     */
    public function ageInDays(DateTimeImmutable $now): int
    {
        return (int) $this->assertedOn->diff($now)->days;
    }

    /** @param array<string, mixed> $row a `sch_provenance` row as laminas-db returns it */
    public static function fromRow(array $row): self
    {
        return new self(
            entity: (string) $row['Entity'],
            entityId: (int) $row['EntityId'],
            fieldGroup: (string) $row['FieldGroup'],
            source: SourceClass::from((string) $row['SourceClass']),
            outcome: Outcome::from((string) $row['Outcome']),
            assertedOn: new DateTimeImmutable((string) $row['AssertedOn']),
            recordedOn: new DateTimeImmutable((string) $row['RecordedOn']),
            sourceUrl: isset($row['SourceUrl']) ? (string) $row['SourceUrl'] : null,
            sourceNote: isset($row['SourceNote']) ? (string) $row['SourceNote'] : null,
            recordedBy: isset($row['RecordedBy']) ? (int) $row['RecordedBy'] : null,
            provenanceId: isset($row['ProvenanceId']) ? (int) $row['ProvenanceId'] : null,
        );
    }

    /** @return array<string, mixed> the shape `sch_provenance` takes on insert */
    public function toRow(): array
    {
        return [
            'Entity'      => $this->entity,
            'EntityId'    => $this->entityId,
            'FieldGroup'  => $this->fieldGroup,
            'SourceClass' => $this->source->value,
            'SourceUrl'   => $this->sourceUrl,
            'SourceNote'  => $this->sourceNote,
            'Outcome'     => $this->outcome->value,
            'AssertedOn'  => $this->assertedOn->format('Y-m-d H:i:s'),
            'RecordedOn'  => $this->recordedOn->format('Y-m-d H:i:s'),
            'RecordedBy'  => $this->recordedBy,
        ];
    }

    /** @return array<string, mixed> the shape the API and the shrine page read */
    public function toArray(): array
    {
        return [
            'fieldGroup' => $this->fieldGroup,
            'source'     => $this->source->value,
            'sourceUrl'  => $this->sourceUrl,
            'sourceNote' => $this->sourceNote,
            'outcome'    => $this->outcome->value,
            'assertedOn' => $this->assertedOn->format('c'),
            'recordedOn' => $this->recordedOn->format('c'),
            'recordedBy' => $this->recordedBy,
            'firstHand'  => $this->source->isFirstHand(),
        ];
    }
}

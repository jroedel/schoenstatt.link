<?php

declare(strict_types=1);

namespace App\Provenance;

use App\Laminas\ServiceBridge;
use DateTimeImmutable;
use DateTimeZone;
use SionModel\Db\Connection;
use SionModel\Db\Sql\Insert;
use SionModel\Db\Sql\Select;

use function is_array;
use function is_object;

/**
 * Reads and writes `sch_provenance`: the append-only record of who claimed what about a
 * shrine, on what evidence, and when.
 *
 * ## Append-only, and what that buys
 *
 * Nothing here updates or deletes. Correcting an assertion means filing a better one, and
 * "current" is the newest row per (entity, entityId, fieldGroup) rather than a column
 * somebody keeps in step. Two things depend on that: a refused claim
 * ({@see Outcome::Competing}) has somewhere to live, and the verification history that the
 * emailed-verification loop is built on survives every subsequent write.
 *
 * ## Why not sch_changes, and why not the *UpdatedOn columns
 *
 * Both were considered and both are wrong, for reasons recorded in `database/db9.0.sql`. The
 * one worth repeating here, because it is the trap: SionModel already has a
 * confirm-without-changing primitive in `updateEntity()`'s `$fieldsToTouch`, and using it
 * would bump the entity's own `UpdatedOn`. That column is the only thing that can still say
 * "216 of 250 shrines were last edited in 2019" — the measurement this project exists to
 * act on — so recording confirmations through it would destroy the signal within a year.
 * **A confirmation is not a change.**
 *
 * ## Time
 *
 * Every datetime written here is UTC, matching `SionTable` and every other stamp in this
 * database. Read {@see Assertion} for why `assertedOn` and `recordedOn` are separate.
 */
final class ProvenanceStore
{
    public const TABLE = 'sch_provenance';

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /** UTC now, which is what every column in this table means. */
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * File one assertion. Returns the new `ProvenanceId`.
     *
     * There is deliberately no de-duplication. An agent that checks the same shrine every
     * week and finds it unchanged every week is producing exactly the record this table is
     * for, and collapsing those rows would throw away the evidence that somebody is
     * actually watching. If the volume ever matters, thin it on read.
     */
    public function record(Assertion $assertion): int
    {
        $insert = (new Insert(self::TABLE))->values($assertion->toRow());

        $this->adapter()->execute($insert);

        return $this->adapter()->lastInsertId();
    }

    /**
     * The newest assertion for one field group, or null if nothing was ever claimed.
     *
     * This is what {@see WriteGate} asks about before letting a write through, so it is on
     * the hot path of every API PATCH and every moderator save. `EntityGroupRecorded` covers
     * it.
     */
    public function latestFor(string $entity, int $entityId, string $fieldGroup): ?Assertion
    {
        $select = (new Select(self::TABLE))
            ->where([
                'Entity'     => $entity,
                'EntityId'   => $entityId,
                'FieldGroup' => $fieldGroup,
            ])
            //RecordedOn then ProvenanceId: two assertions filed in the same second are
            //possible (a PATCH touching two groups is one request) and an ORDER BY with no
            //tiebreaker leaves which one is "newest" up to the storage engine.
            ->order(['RecordedOn' => 'DESC', 'ProvenanceId' => 'DESC'])
            ->limit(1);

        $row = $this->adapter()->select($select)->current();

        //laminas-db answers **false** for an empty set, not null, so a `!== null` test here
        //would report a hit on every miss. Same shape as App\Api\BotIdentity.
        if (! is_array($row) && ! is_object($row)) {
            return null;
        }

        /** @var array<string, mixed> $row */
        return Assertion::fromRow((array) $row);
    }

    /**
     * The newest assertion for every group of one entity, keyed by group.
     *
     * One query rather than one per group: the shrine page wants all of them at once, and
     * the groups are known only after the rows come back anyway.
     *
     * @return array<string, Assertion>
     */
    public function currentFor(string $entity, int $entityId): array
    {
        $select = (new Select(self::TABLE))
            ->where(['Entity' => $entity, 'EntityId' => $entityId])
            ->order(['RecordedOn' => 'ASC', 'ProvenanceId' => 'ASC']);

        $current = [];
        foreach ($this->adapter()->select($select) as $row) {
            /** @var array<string, mixed> $row */
            $assertion = Assertion::fromRow((array) $row);
            //Ascending order plus unconditional overwrite leaves the newest per group. The
            //alternative is a correlated MAX() subquery per group, which is more SQL for a
            //table this size to answer.
            $current[$assertion->fieldGroup] = $assertion;
        }

        return $current;
    }

    /**
     * Every assertion about an entity, newest first — optionally one group only.
     *
     * The competing claims are in here and nowhere else, so this is what a review screen
     * reads.
     *
     * @return list<Assertion>
     */
    public function historyFor(string $entity, int $entityId, ?string $fieldGroup = null): array
    {
        $where = ['Entity' => $entity, 'EntityId' => $entityId];
        if (null !== $fieldGroup) {
            $where['FieldGroup'] = $fieldGroup;
        }

        $select = (new Select(self::TABLE))
            ->where($where)
            ->order(['RecordedOn' => 'DESC', 'ProvenanceId' => 'DESC']);

        $history = [];
        foreach ($this->adapter()->select($select) as $row) {
            /** @var array<string, mixed> $row */
            $history[] = Assertion::fromRow((array) $row);
        }

        return $history;
    }

    /**
     * `Connection::class`, not `Connection::class` — the application registers the
     * concrete adapter and the interface resolves to a different instance. Same note as
     * App\Api\BotIdentity, and getting it wrong is a second connection rather than an error.
     */
    private function adapter(): Connection
    {
        /** @var Connection $adapter */
        $adapter = $this->laminas->get(Connection::class);

        return $adapter;
    }
}

-- db9.0 — sch_provenance: who claimed a shrine's data, from where, and when
--
-- @phase: pre
-- @kind: ddl
-- @idempotent: yes
-- @destructive: no
-- @tables: none
-- @verify: SELECT 'sch_provenance is missing' AS problem WHERE NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sch_provenance')
--
-- Phase 1 of the shrine-data project. See docs/shrine-data.md for why this exists; the
-- short version is that the shrine data stopped moving in 2019 and a direct write records
-- neither where a value came from nor when anybody last checked it, so nothing can tell a
-- rector's correction from a website scrape or a stale field from a verified one.
--
-- ## Append-only, one row per assertion
--
-- Not one upserted row per group. An assertion that was *refused* — a scrape contradicting
-- a fresher, better-sourced claim — has to live somewhere, and so does the verification
-- history that the email loop in phase 4 is built on. "Current" is the newest row per
-- (Entity, EntityId, FieldGroup) and is a query, not a column. At 250 shrines across four
-- groups the volume is nothing.
--
-- ## Why this is not the existing *UpdatedOn columns, and not sch_changes
--
-- `sch_associations` already declares ten field-group stamp pairs, and they cannot answer
-- this. Two of the ten were never written at all (a duplicate array key; see db8.9), and the
-- ones that do get written move on saves that changed nothing — three associations carry an
-- `OpeningHoursHumanUpdatedOn` with no hours value stored.
--
-- `sch_changes` cannot answer it either, and the reason is sharper than "wrong shape".
-- SionModel already has a confirm-without-changing primitive — `updateEntity()`'s
-- `$fieldsToTouch`, which nothing in this codebase passes — and using it would file a
-- `sch_changes` row whose OldValue and NewValue are equal **and bump the entity's own
-- `UpdatedOn`**. That last part is disqualifying: `UpdatedOn` is the only thing that can
-- still say "216 of 250 shrines were last edited in 2019", which is the measurement this
-- whole project rests on. Recording confirmations there would erase the staleness signal
-- within a year of starting to use it. A confirmation is not a change and must not be
-- stored as one.
--
-- ## Columns
--
-- `Entity`/`EntityId` are deliberately generic — `sch_persons` carries the same field groups
-- and will want this later — but only `association` is written today.
--
-- `FieldGroup` is derived from the entity's own configuration rather than listed here: a
-- field's group is its `many_to_one_update_columns` entry if it has one, otherwise the field
-- itself when a `<field>UpdatedOn` column exists. Deriving it is deliberate. db8.9 was
-- cleaning up after a hand-maintained duplicate of exactly this mapping that had silently
-- disagreed with itself for years.
--
-- `SourceClass` carries the rank that decides who wins: the shrine itself outranks an
-- office, which outranks the shrine's website, which outranks a third-party directory,
-- which outranks an agent's inference. The ranks live in App\Provenance\SourceClass, not in
-- the schema, because they are a policy and policies get revised.
--
-- `Outcome` is the three things an assertion can have done:
--   confirmed — checked, the stored value was already right, nothing was written
--   corrected — checked, the value changed
--   competing — a lower-ranked source contradicted a fresher higher-ranked claim; the
--               finding is kept, the stored value was left alone
--
-- `AssertedOn` and `RecordedOn` are different dates on purpose. An agent reading a parish
-- bulletin published in March and filing it in August asserts something about March; a
-- confirmation is only as good as the observation behind it, not as good as its upload.
-- Both are UTC, like every other datetime this application writes.

CREATE TABLE IF NOT EXISTS `sch_provenance` (
    `ProvenanceId` INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `Entity`       VARCHAR(50)   NOT NULL COMMENT 'association; persons carry the same groups and may follow',
    `EntityId`     INT           NOT NULL,
    `FieldGroup`   VARCHAR(50)   NOT NULL COMMENT 'derived from the entity config, never hand-listed',
    `SourceClass`  VARCHAR(20)   NOT NULL COMMENT 'shrine|office|website|directory|inference; rank lives in PHP',
    `SourceUrl`    VARCHAR(1000) NULL,
    `SourceNote`   VARCHAR(255)  NULL COMMENT 'free text: who said so, which bulletin, which phone call',
    `Outcome`      VARCHAR(20)   NOT NULL COMMENT 'confirmed|corrected|competing',
    `AssertedOn`   DATETIME      NOT NULL COMMENT 'when the claim was true, UTC — not when it was filed',
    `RecordedOn`   DATETIME      NOT NULL COMMENT 'when we stored it, UTC',
    `RecordedBy`   INT           NULL COMMENT 'user id of the bot or human; null for an unauthenticated token',
    PRIMARY KEY (`ProvenanceId`),
    KEY `EntityGroupRecorded` (`Entity`, `EntityId`, `FieldGroup`, `RecordedOn`),
    KEY `RecordedOn` (`RecordedOn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

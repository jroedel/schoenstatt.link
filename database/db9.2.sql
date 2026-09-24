-- db9.2 — drop every stored date of birth, name day and date of ordination
--
-- @phase: post
-- @kind: ddl
-- @idempotent: yes
-- @destructive: yes
-- @tables: sch_persons
-- @verify: SELECT COLUMN_NAME AS column_still_present FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sch_persons' AND COLUMN_NAME IN ('BirthDate','BirthDatePrecision','NameDay','PriestDate','PriestDatePrecision','BishopDate','BishopDatePrecision')
--
-- The decision (2026-09-24): a date of birth is personally identifiable information that
-- this application has no purpose for, so it is not stored. The same reasoning reaches the
-- name day and the three dates of ordination. The code stopped reading and writing all
-- seven columns in the release this ships with; this drops them and their data.
--
-- ## Why none of the seven had a purpose
--
--   * `BirthDate` was displayed on one line of the person page and nothing else. The
--     computed `age` that went with it was in every person row the table built and was
--     read by no template, no test and no API. 149 of 325 persons had one, with real
--     years — 1932 to 1998 — which is why the privacy policy's claim that the site held
--     "birthdays (never birth years)" had to go with the column rather than be repaired.
--   * `NameDay` was a day and a month with a fixed 1900 year, shown as one line, editable
--     nowhere — the edit form round-tripped it through hidden inputs precisely because no
--     screen offered it.
--   * `PriestDate`, `BishopDate` and their precisions recorded *when* a man was ordained.
--     What the site actually uses is *whether* he was, and that is `PersonTags`, which
--     carries `priest`, `bishop` and `deacon`, drives every displayed title, and is
--     editable on the form. It is also the more complete record: `BishopDate` had zero
--     rows, all ten persons with a `PriestDate` are tagged, and five more are tagged
--     priests with no date at all.
--
-- Nothing sorted, filtered, indexed or searched on any of them, no /api/v3 resource
-- exposed a person, and there is no birthday list or anniversary feature to break.
--
-- ## What this does not remove
--
-- `DeathDate` and `DeathDatePrecision` stay. They are the one date a historical register
-- of a movement genuinely needs, they are far less identifying than a birth date, and the
-- person page has always shown them.
--
-- The patres application still sends `birthDate`, `priestDate`, `bishopDate` and
-- `deaconDate` in its person records, and `Schoenstatt\Service\PatresGateway` still reads
-- them — to derive the tag, which is the status this site keeps, and then discards the
-- dates. The input filter no longer names those fields, so they cannot reach a column.
--
-- ## Why `@phase: post`
--
-- `pre` runs before the symlink swap, while the PREVIOUS release is still serving, and
-- that release SELECTs all seven by name in `SchoenstattTable` and writes four of them on
-- every person save. Dropping them first would break every person page and every person
-- save for the length of the deploy. Post-swap, the code that touches them is gone.
--
-- `@destructive: yes` for the same reason in reverse: a rollback to a release that still
-- names these columns is a broken site, and tools/deploy.sh refuses one.
--
-- ## THIS DOES NOT PHYSICALLY ERASE THE BYTES
--
-- The same caveat as db9.1, and the same remedy. MariaDB 10.11 drops a column with
-- ALGORITHM=INSTANT when it can; an instant drop records that the column is gone and does
-- not rewrite the rows already on disk. After this, the application cannot read a birth
-- date and no query can return one, but the values remain in the existing records until
-- the table is rebuilt with `ALTER TABLE sch_persons FORCE`.
--
-- `sch_persons` is small — hundreds of rows, not the 8.7 million of `sch_visits` — so
-- unlike db9.1 the rebuild is cheap. It is still not in this file: a deploy-time table
-- copy is a deploy-time lock, and the rule that kept it out of db9.1 is not worth bending
-- for a table that takes a moment to rebuild by hand afterwards.
--
-- The pre-migration snapshot this file triggers is itself a complete copy of the data
-- being removed. It lives in shared/migration-snapshots/ under the retention policy in
-- docs/DEPLOY.md, and the point of the exercise is not served until it ages out or is
-- deleted deliberately.

ALTER TABLE `sch_persons`
    DROP COLUMN IF EXISTS `BirthDate`,
    DROP COLUMN IF EXISTS `BirthDatePrecision`,
    DROP COLUMN IF EXISTS `NameDay`,
    DROP COLUMN IF EXISTS `PriestDate`,
    DROP COLUMN IF EXISTS `PriestDatePrecision`,
    DROP COLUMN IF EXISTS `BishopDate`,
    DROP COLUMN IF EXISTS `BishopDatePrecision`;

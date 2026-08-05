-- db6.5 — date precision for persons, associations and assignments
--
-- WHY
-- ---
-- `events` already models how precisely a date is known: events.StartDatePrecision
-- is varchar(20) NOT NULL DEFAULT 'day' holding 'year', 'month' or 'day', and the
-- data agrees with it perfectly — all 363 'year' rows are 1 January, all 9 'month'
-- rows are day 1, all 155 'day' rows are full dates. It is a good model and it is
-- the only table that has one.
--
-- The other date columns use the same convention informally, with nothing to
-- record it, so "1 January 1952" and "sometime in 1952" are the same value:
--
--     sch_assignments.StartDate     41% are 1 January   (36 of 88)
--     sch_assignments.EndDate        5% are 1 January   (2 of 40)
--     sch_associations.FoundationDate  0.9% are 1 January (2 of 217)
--     sch_persons.BirthDate          0% are 1 January, 4.7% are day 1
--
-- These columns get the same precision column so the distinction can be stated
-- rather than implied, and so a contributor can enter "born in 1952" without
-- asserting a day nobody knows.
--
-- Requires DDL rights. The application's own database user does not have them,
-- which is why this is a hand-run script and not something the app performs.
-- Run it once per environment, before deploying the code that reads these
-- columns; the code tolerates their absence only in the sense that it will fail
-- loudly, so the order matters.
--
-- Non-destructive by construction: no date value is modified. Precision is
-- metadata about a date, so a wrong label degrades how a date is *displayed* and
-- is corrected by editing one field. That is what makes the one inference below
-- acceptable.

ALTER TABLE `sch_persons`
  ADD COLUMN `BirthDatePrecision`  VARCHAR(20) NOT NULL DEFAULT 'day' AFTER `BirthDate`,
  ADD COLUMN `PriestDatePrecision` VARCHAR(20) NOT NULL DEFAULT 'day' AFTER `PriestDate`,
  ADD COLUMN `BishopDatePrecision` VARCHAR(20) NOT NULL DEFAULT 'day' AFTER `BishopDate`,
  ADD COLUMN `DeathDatePrecision`  VARCHAR(20) NOT NULL DEFAULT 'day' AFTER `DeathDate`;

ALTER TABLE `sch_associations`
  ADD COLUMN `FoundationDatePrecision` VARCHAR(20) NOT NULL DEFAULT 'day' AFTER `FoundationDate`;

ALTER TABLE `sch_assignments`
  ADD COLUMN `StartDatePrecision` VARCHAR(20) NOT NULL DEFAULT 'day' AFTER `StartDate`,
  ADD COLUMN `EndDatePrecision`   VARCHAR(20) NOT NULL DEFAULT 'day' AFTER `EndDate`;

-- The one backfill the evidence actually supports.
--
-- 41% of assignment start dates are 1 January. If those days were real, chance
-- would put the figure near 0.27% (1/365), so this is roughly 150x above what
-- coincidence explains: these are year-only values. One or two of the 36 may be a
-- genuine New Year's Day start, which is why the review query below lists them.
UPDATE `sch_assignments`
   SET `StartDatePrecision` = 'year'
 WHERE `StartDate` IS NOT NULL
   AND MONTH(`StartDate`) = 1
   AND DAY(`StartDate`) = 1;

-- Everything else deliberately keeps the 'day' default, because the evidence does
-- not distinguish convention from coincidence:
--
--   sch_persons.BirthDate — 4.7% fall on day 1 against 3.3% expected by chance
--     (1/30). That is chance. Labelling them 'month' would turn "born 1 June
--     1952" into "born in June 1952" and lose a known day.
--   sch_associations.FoundationDate — 5.1% on day 1, again chance; and the two
--     1 January rows are 0.9% of 217, too few to separate from a real New Year
--     foundation, which is entirely plausible for this subject matter.
--   sch_assignments.EndDate — only 2 rows on 1 January. Same reasoning.
--   sch_assignments.StartDate day-1-but-not-January — 11 rows, 12.5% against
--     3.3% expected, so some are probably month-only. "Probably" is not a
--     migration; they are listed for review instead.
--
-- Guessing 'month' anywhere would be the one irreversible-in-practice mistake
-- available here, because nobody will later remember which label was inferred.

-- REVIEW QUERY — not run by this script. These are the rows whose precision is
-- plausibly not 'day' but could not be decided from the data. Someone who knows
-- the records should look at them and correct the precision by hand.
--
-- SELECT 'assignment start, 1 Jan (set to year — confirm none is a real 1 January)' AS review,
--        AssignmentId AS id, StartDate AS d FROM sch_assignments
--  WHERE StartDate IS NOT NULL AND MONTH(StartDate)=1 AND DAY(StartDate)=1
-- UNION ALL
-- SELECT 'assignment start, day 1 of another month (month-only?)',
--        AssignmentId, StartDate FROM sch_assignments
--  WHERE StartDate IS NOT NULL AND DAY(StartDate)=1 AND MONTH(StartDate)<>1
-- UNION ALL
-- SELECT 'assignment end, 1 Jan (year-only?)',
--        AssignmentId, EndDate FROM sch_assignments
--  WHERE EndDate IS NOT NULL AND MONTH(EndDate)=1 AND DAY(EndDate)=1
-- UNION ALL
-- SELECT 'association foundation, 1 Jan (year-only?)',
--        AssociationId, FoundationDate FROM sch_associations
--  WHERE FoundationDate IS NOT NULL AND MONTH(FoundationDate)=1 AND DAY(FoundationDate)=1
-- ORDER BY review, d;

-- ROLLBACK, should it be needed. Safe: the columns hold only derived metadata,
-- and no date value was touched, so dropping them returns the schema exactly to
-- db6.4 with no data loss.
--
-- ALTER TABLE `sch_persons`
--   DROP COLUMN `BirthDatePrecision`, DROP COLUMN `PriestDatePrecision`,
--   DROP COLUMN `BishopDatePrecision`, DROP COLUMN `DeathDatePrecision`;
-- ALTER TABLE `sch_associations` DROP COLUMN `FoundationDatePrecision`;
-- ALTER TABLE `sch_assignments`
--   DROP COLUMN `StartDatePrecision`, DROP COLUMN `EndDatePrecision`;

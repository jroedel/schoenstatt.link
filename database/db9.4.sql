-- db9.4 — blank the change-log copies of the dates db9.2 dropped
--
-- @phase: post
-- @kind: dml
-- @tables: sch_changes
-- @verify: SELECT ChangeID AS copy_still_present FROM sch_changes WHERE ChangedEntity = 'person' AND ChangedField IN ('birthDate', 'birthDatePrecision', 'nameDay', 'priestDate', 'priestDatePrecision', 'bishopDate', 'bishopDatePrecision') AND (OldValue IS NOT NULL OR NewValue IS NOT NULL)
--
-- db9.2 (#315) dropped every date of birth, name day and date of ordination from
-- `sch_persons`. The change log kept its own copies: every edit of one of those fields had
-- written the old and the new value into `sch_changes`, and eight such rows still held a
-- date (one birth date, four name days, three ordination dates in the production data).
-- This blanks them. The rows themselves stay, as `privacy:retention` leaves them: the
-- entity, the field, the date and the editor say that an edit happened, and none of that
-- is the date.
--
-- `post` because nothing reads these values and nothing old writes them any more — the
-- release that could is the one before db9.2's. Not `@destructive`: a rollback finds the
-- same code reading the same (blanked) rows.
--
-- The pre-migration snapshot of `sch_changes` holds the eight values, and ages out under
-- the retention in docs/DEPLOY.md.

UPDATE sch_changes
SET OldValue = NULL, NewValue = NULL
WHERE ChangedEntity = 'person'
  AND ChangedField IN ('birthDate', 'birthDatePrecision', 'nameDay', 'priestDate', 'priestDatePrecision', 'bishopDate', 'bishopDatePrecision')
  AND (OldValue IS NOT NULL OR NewValue IS NOT NULL);

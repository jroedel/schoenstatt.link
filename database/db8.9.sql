-- db8.9 — drop 24 columns that have never held a value
--
-- @phase: post
-- @kind: ddl
-- @idempotent: yes
-- @destructive: yes
-- @tables: sch_associations, sch_persons
-- @verify: SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME) AS column_still_present FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='sch_associations' AND COLUMN_NAME IN ('IdealEn','IdealEs','IdealDe','IdealPt','IdealFr','VisitorsInformationEn','VisitorsInformationEs','VisitorsInformationDe','VisitorsInformationPt','VisitorsInformationFr','HistoryEn','HistoryEs','HistoryDe','HistoryPt','HistoryFr','SlugFr','EmailsUpdatedOn','EmailsUpdatedBy','PhonesUpdatedOn','PhonesUpdatedBy')) OR (TABLE_NAME='sch_persons' AND COLUMN_NAME IN ('EmailsUpdatedOn','EmailsUpdatedBy','PhonesUpdatedOn','PhonesUpdatedBy')))
--
-- Twenty on `sch_associations`, four on `sch_persons`. Every one of them holds zero
-- non-NULL values — measured 2026-08-23 across all 498 associations and all 325 persons,
-- not a sample. See docs/shrine-data.md § The dead columns, which is where the decision and
-- the alternatives live.
--
-- They are not all dead for the same reason, and the difference is the whole content of
-- this file.
--
-- ## The fifteen prose columns, and SlugFr
--
-- `IdealEn/Es/De/Pt/Fr`, `VisitorsInformationEn/Es/De/Pt/Fr`, `HistoryEn/Es/De/Pt/Fr` were a
-- previous generation's attempt at per-locale shrine prose. They appear in the codebase only
-- in commented-out `update_columns` lines, each already marked `@deprecated`, and the feature
-- was superseded by `PublicNotes` plus the phrase table.
--
-- What settles them is not the row count but the **locale set**: they cover
-- `en/es/de/pt/fr`, while this site's five configured locales are `en_US/es_ES/de_DE/pt_BR/it_IT`.
-- French was planned; Italian arrived instead. These columns could not serve the current site
-- even if somebody wanted to revive them, so there is nothing to weigh.
--
-- `sch_associations.SlugFr` is dead for exactly that reason —
-- `SchoenstattTable::LOCALES_TO_SLUG_COLUMN_NAME` names five slug columns and `SlugFr` is not
-- one of them, and `getSelectPrototype()` merges the same five by hand.
--
-- **Care point: a live `SlugFr` exists on another table.** `module/Books/config/module.config.php`
-- maps `slugFr => SlugFr` on the `events` entity and `Books\Model\EventTextTable` reads it.
-- Only the `sch_associations` copy is dropped here. A repo-wide search for the column name
-- will find the live one; do not follow it.
--
-- ## The four field-group stamps, which are dead from a bug
--
-- `EmailsUpdatedOn`, `EmailsUpdatedBy`, `PhonesUpdatedOn`, `PhonesUpdatedBy`, on both tables.
--
-- SionModel stamps a "this group of fields was last touched" timestamp per field group, and
-- `module/Schoenstatt/config/module.config.php` maps each field to its group. It mapped
-- `email`, `phone1`, `phone1Label`, `phone2`, `phone2Label`, `phone3` and `phone3Label` to
-- `'emails'`/`'phones'` and then, twenty lines later **inside the same array literal**, to
-- `'contactInfo'`. PHP keeps the last value for a duplicate key silently. So every email and
-- phone field has always belonged to `contactInfo` alone — 247 of 498 rows carry that stamp —
-- and the `emails` and `phones` groups were unreachable. The person entity carried the same
-- duplication independently.
--
-- The visible consequence, and the reason this is not a cosmetic cleanup: the "✓ Up-to-date"
-- tooltip on the association and person pages is guarded on `phonesUpdatedOn`, so it **has
-- never rendered on either front controller**, in either the Twig or the `.phtml` rendering.
-- The `docs/BACKLOG.md` item about it displaying a hardcoded `jeff` was describing
-- unreachable code. Those blocks are deleted in the same commit as this file.
--
-- They are dropped rather than repaired because the scheme is being superseded either way:
-- docs/shrine-data.md decision 1 gives provenance its own store, precisely because these
-- stamps move on saves that changed nothing (3 associations carry an
-- `OpeningHoursHumanUpdatedOn` with no hours value) and because two of the ten declared
-- groups turned out to be silently unreachable. Fixing the mapping would start collecting
-- data in a scheme we intend to replace. Whether emails and phones deserve their own groups
-- is a real question, and it belongs to the new store.
--
-- ## Why `post`, and why `destructive`
--
-- **`post` is mandatory here, not a preference.** `SchoenstattTable::getSelectPrototype()`
-- builds the association query from `array_values($entitySpec->updateColumns)` — it
-- enumerates its columns rather than issuing `SELECT *` — and the four stamp columns are in
-- that list until the config change ships. Dropping them first means the still-running old
-- release asks for columns that no longer exist: SQLSTATE 42S22 / errno 1054 on **every**
-- association page, not a warning. Code first, then the drop.
--
-- **`@destructive: yes` for the mirror image of the same fact.** Once these columns are gone,
-- any release from before the config change cannot serve an association page, so both
-- rollback paths must refuse a target that predates this file. The fifteen prose columns and
-- `SlugFr` are referenced by nothing and would be safe in either order; the four stamps are
-- what makes the file as a whole destructive, and splitting it would not change the rollback
-- constraint because both halves ship in the same deploy.
--
-- Note for whoever greps history: `database/db2.1.sql` and `database/db7.0.sql` name the
-- prose columns. Both are applied and their bytes are frozen by the ledger — they are the
-- record of when the columns arrived and of the charset conversion that carried them, and
-- they are not edited here.

ALTER TABLE `sch_associations`
    DROP COLUMN IF EXISTS `IdealEn`,
    DROP COLUMN IF EXISTS `IdealEs`,
    DROP COLUMN IF EXISTS `IdealDe`,
    DROP COLUMN IF EXISTS `IdealPt`,
    DROP COLUMN IF EXISTS `IdealFr`,
    DROP COLUMN IF EXISTS `VisitorsInformationEn`,
    DROP COLUMN IF EXISTS `VisitorsInformationEs`,
    DROP COLUMN IF EXISTS `VisitorsInformationDe`,
    DROP COLUMN IF EXISTS `VisitorsInformationPt`,
    DROP COLUMN IF EXISTS `VisitorsInformationFr`,
    DROP COLUMN IF EXISTS `HistoryEn`,
    DROP COLUMN IF EXISTS `HistoryEs`,
    DROP COLUMN IF EXISTS `HistoryDe`,
    DROP COLUMN IF EXISTS `HistoryPt`,
    DROP COLUMN IF EXISTS `HistoryFr`,
    DROP COLUMN IF EXISTS `SlugFr`,
    DROP COLUMN IF EXISTS `EmailsUpdatedOn`,
    DROP COLUMN IF EXISTS `EmailsUpdatedBy`,
    DROP COLUMN IF EXISTS `PhonesUpdatedOn`,
    DROP COLUMN IF EXISTS `PhonesUpdatedBy`;

ALTER TABLE `sch_persons`
    DROP COLUMN IF EXISTS `EmailsUpdatedOn`,
    DROP COLUMN IF EXISTS `EmailsUpdatedBy`,
    DROP COLUMN IF EXISTS `PhonesUpdatedOn`,
    DROP COLUMN IF EXISTS `PhonesUpdatedBy`;

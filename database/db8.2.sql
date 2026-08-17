-- db8.2 — the last references the retired literature-maintenance sweeps would have fixed
--
-- @phase: post
-- @kind: dml
-- @tables: sch_publications, lib_books, sch_changes
-- @verify: SELECT p.`PublicationId` FROM `sch_publications` p JOIN `sch_publications` o ON o.`PublicationId` = p.`MainPublicationId` AND o.`DataSource` IS NOT NULL AND o.`MergedIntoPublicationId` IS NOT NULL WHERE p.`DataSource` IS NULL UNION ALL SELECT p.`PublicationId` FROM `sch_publications` p JOIN `sch_publications` o ON o.`PublicationId` = p.`TranslatedFromPublicationId` AND o.`DataSource` IS NOT NULL AND o.`MergedIntoPublicationId` IS NOT NULL WHERE p.`DataSource` IS NULL UNION ALL SELECT b.`book_id` FROM `lib_books` b JOIN `sch_publications` o ON o.`PublicationId` = b.`publication_id` AND o.`DataSource` IS NOT NULL AND o.`MergedIntoPublicationId` IS NOT NULL
--
-- `post`, and the reason is the same one every `db7.x` retirement had: the code that
-- performs these updates is deleted in the same commit. During the pre phase the
-- PREVIOUS release is still serving and still routes /admin/literature-maintenance,
-- so nothing breaks either way — but running the data fix before the swap would leave
-- a window where the old code's simulate view reports zero rows for a migration it
-- still advertises. After the swap the routes are gone and this is the only thing that
-- can still do the work.
--
-- WHAT THIS IS
-- -----------
-- `/admin/literature-maintenance` and its six children were the 2020 migration that
-- turned imported ("data-sourced") publication rows into first-class ones. The bulk of
-- it finished years ago. Measured in the capsule on 2026-08-17, against a production
-- export days old:
--
--   copy-data-sourced-row-to-first-class-citizen         0 rows
--   update-main-publication-ids                          0 rows
--   update-translated-from-publication-id                1 row
--   update-library-book-publication-references           2 rows
--   update-cover-images                                 20 files, 4 publications
--   list-merged-publication-id-map              read-only, 3,630 entries
--
-- The 2,333 publications that still carry a `DataSource` are NOT unfinished work: they
-- sit at `IsAwaitingMerge = 1`, which the bulk sweep skips by design. They are merged
-- one at a time by a human through `publication-copy-to-main-corpus`, which stays.
--
-- So the whole remaining output of five write sweeps was three rows, and all three
-- point at the same publication: 1426 ("Wachstum im höheren Gebetsleben", DataSource
-- `b_bibprim_edition`), merged into 10249. One publication claims to be translated from
-- it and two library books catalogue it.
--
-- WRITTEN AS A JOIN, NOT AS THREE IDS
-- -----------------------------------
-- The capsule's data is days old, not identical to production's, and a moderator can
-- merge another publication at any time through the per-row action. Naming 1426 and
-- 10249 literally would fix exactly what was true here on 2026-08-17 and silently miss
-- anything merged since. The joins below reproduce the sweeps' own predicate — "this
-- reference names a data-sourced row that has been merged away; follow the merge" — so
-- the file is correct on whatever production actually holds, and re-running it is a
-- no-op once no such reference remains.
--
-- `MainPublicationId` is included even though it measured zero. It is the same defect
-- with the same fix, the sweep that handled it is being deleted too, and a migration
-- that covers two of the three reference columns would be a trap for whoever finds the
-- third one later.
--
-- The `p.DataSource IS NULL` restriction on the two publication statements is the
-- sweeps' own, kept deliberately rather than tidied away: a data-sourced row is a
-- verbatim record of what an external catalogue said, and rewriting its references
-- would edit history rather than repair a link. Books carry no such column, so their
-- statement has no equivalent clause.


-- 1. Record what is about to change, before it changes.
--
--    The sweeps wrote through SionTable::updateEntity(), which files a `sch_changes`
--    row per field it touches; a plain UPDATE does not. That log is the only accurate
--    modification time this application keeps — entity `UpdatedOn` columns are not
--    maintained — and it is what the sitemap reads for <lastmod>, so a repair that
--    skipped it would make three records look untouched since 2021.
--
--    This runs FIRST because after the UPDATEs the rows can no longer be identified:
--    the predicate that selects them is exactly the one the UPDATEs dissolve.
--
--    Guarded with NOT EXISTS on the same three key columns, for the reason db7.9
--    records: an unconditional INSERT here would add another audit row on every
--    re-run, and this file is otherwise re-runnable. `UpdatedBy` is NULL — no user did
--    this, a migration did.
INSERT INTO `sch_changes` (`ChangedEntity`, `ChangedField`, `ChangedIDValue`, `NewValue`, `OldValue`, `UpdatedOn`, `UpdatedBy`, `IpAddress`)
SELECT 'publication', 'mainPublicationId', p.`PublicationId`, o.`MergedIntoPublicationId`, p.`MainPublicationId`, UTC_TIMESTAMP(), NULL, NULL
FROM `sch_publications` p
JOIN `sch_publications` o
  ON o.`PublicationId` = p.`MainPublicationId`
 AND o.`DataSource` IS NOT NULL
 AND o.`MergedIntoPublicationId` IS NOT NULL
WHERE p.`DataSource` IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `sch_changes`) c
    WHERE c.`ChangedEntity` = 'publication'
      AND c.`ChangedField`  = 'mainPublicationId'
      AND c.`ChangedIDValue` = p.`PublicationId`
      AND c.`NewValue` = o.`MergedIntoPublicationId`
  );

INSERT INTO `sch_changes` (`ChangedEntity`, `ChangedField`, `ChangedIDValue`, `NewValue`, `OldValue`, `UpdatedOn`, `UpdatedBy`, `IpAddress`)
SELECT 'publication', 'translatedFromPublicationId', p.`PublicationId`, o.`MergedIntoPublicationId`, p.`TranslatedFromPublicationId`, UTC_TIMESTAMP(), NULL, NULL
FROM `sch_publications` p
JOIN `sch_publications` o
  ON o.`PublicationId` = p.`TranslatedFromPublicationId`
 AND o.`DataSource` IS NOT NULL
 AND o.`MergedIntoPublicationId` IS NOT NULL
WHERE p.`DataSource` IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `sch_changes`) c
    WHERE c.`ChangedEntity` = 'publication'
      AND c.`ChangedField`  = 'translatedFromPublicationId'
      AND c.`ChangedIDValue` = p.`PublicationId`
      AND c.`NewValue` = o.`MergedIntoPublicationId`
  );

INSERT INTO `sch_changes` (`ChangedEntity`, `ChangedField`, `ChangedIDValue`, `NewValue`, `OldValue`, `UpdatedOn`, `UpdatedBy`, `IpAddress`)
SELECT 'book', 'publicationId', b.`book_id`, o.`MergedIntoPublicationId`, b.`publication_id`, UTC_TIMESTAMP(), NULL, NULL
FROM `lib_books` b
JOIN `sch_publications` o
  ON o.`PublicationId` = b.`publication_id`
 AND o.`DataSource` IS NOT NULL
 AND o.`MergedIntoPublicationId` IS NOT NULL
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `sch_changes`) c
    WHERE c.`ChangedEntity` = 'book'
      AND c.`ChangedField`  = 'publicationId'
      AND c.`ChangedIDValue` = b.`book_id`
      AND c.`NewValue` = o.`MergedIntoPublicationId`
  );


-- 2. Follow the merge, in all three places a publication id can be referenced.
UPDATE `sch_publications` p
JOIN `sch_publications` o
  ON o.`PublicationId` = p.`MainPublicationId`
 AND o.`DataSource` IS NOT NULL
 AND o.`MergedIntoPublicationId` IS NOT NULL
SET p.`MainPublicationId` = o.`MergedIntoPublicationId`
WHERE p.`DataSource` IS NULL;

UPDATE `sch_publications` p
JOIN `sch_publications` o
  ON o.`PublicationId` = p.`TranslatedFromPublicationId`
 AND o.`DataSource` IS NOT NULL
 AND o.`MergedIntoPublicationId` IS NOT NULL
SET p.`TranslatedFromPublicationId` = o.`MergedIntoPublicationId`
WHERE p.`DataSource` IS NULL;

UPDATE `lib_books` b
JOIN `sch_publications` o
  ON o.`PublicationId` = b.`publication_id`
 AND o.`DataSource` IS NOT NULL
 AND o.`MergedIntoPublicationId` IS NOT NULL
SET b.`publication_id` = o.`MergedIntoPublicationId`;


-- 3. Verification: no reference names a merged-away data-sourced row any more.
--
--    Each of these should report 0. They are the @verify query counted rather than
--    listed, so a failure here says which of the three columns still has work.
SELECT 'sch_publications.MainPublicationId' AS `reference`, COUNT(*) AS `stale`
FROM `sch_publications` p
JOIN `sch_publications` o
  ON o.`PublicationId` = p.`MainPublicationId`
 AND o.`DataSource` IS NOT NULL
 AND o.`MergedIntoPublicationId` IS NOT NULL
WHERE p.`DataSource` IS NULL
UNION ALL
SELECT 'sch_publications.TranslatedFromPublicationId', COUNT(*)
FROM `sch_publications` p
JOIN `sch_publications` o
  ON o.`PublicationId` = p.`TranslatedFromPublicationId`
 AND o.`DataSource` IS NOT NULL
 AND o.`MergedIntoPublicationId` IS NOT NULL
WHERE p.`DataSource` IS NULL
UNION ALL
SELECT 'lib_books.publication_id', COUNT(*)
FROM `lib_books` b
JOIN `sch_publications` o
  ON o.`PublicationId` = b.`publication_id`
 AND o.`DataSource` IS NOT NULL
 AND o.`MergedIntoPublicationId` IS NOT NULL;

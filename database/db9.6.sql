-- db9.6 — Bellavista and Colegio Mayor lend to their own borrowers, not the Patres roster;
--         untick the 57 borrowers entered in August 2017 who never borrowed
--
-- @phase: pre
-- @kind: dml
-- @destructive: yes
-- @tables: lib_libraries, sch_persons
-- @verify: SELECT CONCAT('library ', LibraryId) AS still_wrong FROM lib_libraries WHERE CheckoutPersonListKind = 'patres-sion' UNION ALL SELECT CONCAT('person ', PersonId) FROM sch_persons p WHERE IsBorrower = 1 AND CreatedOn < '2018-01-01' AND NOT EXISTS (SELECT 1 FROM lib_checkouts c WHERE c.PersonId = p.PersonId)
--
-- The decision (2026-10-25): the Patres persons API that `patres-sion` read is being retired,
-- and this application stops calling it. The two libraries that offered its roster offer
-- `library-borrowers` instead: everyone ticked as a borrower who has borrowed there or has
-- not borrowed anywhere yet, plus the library's default checkout person (docs/libraries.md).
-- A father was imported into `sch_persons` and ticked as a borrower on his first loan, so
-- in the production data 68 of the 69 people who have borrowed at either library are on
-- the new list (one of them as both libraries' default checkout person). The other is a
-- person entered here by hand, never ticked, with a single loan in 2017.
--
-- `pre` because the release this ships with no longer knows `patres-sion` and refuses to
-- build a checkout form for an unknown kind. The old code keeps reading the library rows
-- from APCu until the post-swap flush, so it goes on serving the old value through the
-- deploy, and the flush is what shows the new code the new one. `@destructive: yes` because
-- a rollback would put code that does not know `library-borrowers` in front of these rows,
-- and every checkout page at both libraries would then refuse to render.
--
-- ## The 57 unticked borrowers
--
-- `library-borrowers` offers anyone ticked as a borrower who has not borrowed anywhere yet,
-- because that is how a new borrower appears. 57 people were ticked between 16 and 24
-- August 2017 (Argentina and Chile; one of them imported from Patres) and have never
-- borrowed, so they would have filled both houses' lists. Decided 2026-10-25: untick them.
-- Selected by all three conditions — ticked, created before 2018, no checkout anywhere — so
-- a borrower added since and waiting for a first loan cannot match. The application's
-- change log (`sch_changes`) does not record a write made here; the pre-migration snapshot
-- of `sch_persons` holds the old flags.

UPDATE lib_libraries
SET CheckoutPersonListKind = 'library-borrowers'
WHERE CheckoutPersonListKind = 'patres-sion';

UPDATE sch_persons p
SET IsBorrower = 0
WHERE IsBorrower = 1
  AND CreatedOn < '2018-01-01'
  AND NOT EXISTS (SELECT 1 FROM lib_checkouts c WHERE c.PersonId = p.PersonId);

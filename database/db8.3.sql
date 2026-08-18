-- db8.3 — retire the three library imports that have been pending since 2017 and 2021
--
-- @phase: post
-- @kind: dml
-- @tables: lib_imports
-- @verify: SELECT `ImportId`, `Status` FROM `lib_imports` WHERE `Status` = 'pending'
--
-- `post`, for the reason every code-before-data migration here is post: the release
-- being swapped in is the first that knows what `abandoned` means. Applied before the
-- swap, the previous release would read these rows as ordinary pending imports — which
-- is exactly the state this migration exists to end — and its configure page would go
-- on offering to run them for the length of the composer install.
--
-- ## What was live
--
-- Three rows, none of them ever run:
--
--   1   2017-07-24  From google docs
--   3   2017-08-15  Google docs, with german and book removals
--   14  2021-08-10  Intento 1
--
-- Imports 1 and 3 still had their spreadsheets on disk, so their pages still worked,
-- and both are complete imports — meaning every active book the sheet does not list
-- gets marked inactive. Simulated against the current catalogue on 2026-08-18:
--
--   import 1:  8 create,  8,417 match,  3,492 inactivate,  2 errors
--   import 3:  1 create, 10,091 match,  1,281 inactivate,  8 errors
--
-- Colegio Mayor has 10,874 active books. One click on a nine-year-old spreadsheet
-- retired a third of them, from a page that named no numbers before the button.
--
-- Import 14 is the other kind of stale: its `FilePath` is
-- `C:\Users\Ramon Vergara\Desktop\intento.xlsx`, a path on somebody's own computer,
-- typed into a field that meant "path on the server". It has never been runnable.
--
-- ## Why abandon rather than delete
--
-- The rows are the record that these attempts happened, and import 14 in particular is
-- the clearest evidence of the usability problem the same release fixes. `abandoned`
-- keeps them in the list, keeps them out of the configure page, and is reversible;
-- `DELETE` is none of those.

UPDATE `lib_imports`
   SET `Status` = 'abandoned'
 WHERE `ImportId` IN (1, 3, 14)
   AND `Status` = 'pending';

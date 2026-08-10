-- db7.0 — InnoDB and utf8mb4_unicode_520_ci for every table the application uses
--
-- WHY
-- ---
-- Eleven tables were still MyISAM, an engine with no crash recovery and no
-- transactions. Three of them are live application tables that the site reads and
-- writes on ordinary page loads: `lib_books` (33,690 rows, the whole library
-- catalogue), `sch_roles` and `sch_assignments` (the office/assignment model).
-- They have survived on luck. An unclean shutdown corrupts a MyISAM table and the
-- first anyone hears of it is a wrong answer, not an error.
--
-- The charset was a second, quieter problem. The database held four different
-- collations at once — `utf8mb3_general_ci` (most tables), `utf8mb3_unicode_ci`
-- (`user`, `user_api_token`), `latin1_swedish_ci` (five SionModel entity tables)
-- and `utf8mb4_unicode_ci` (`jtranslate_migration`, created recently by
-- JTranslate's migration runner). Nobody chose that; it accreted. Mixed collations
-- are not merely untidy: a cross-table string comparison between two of them
-- raises "Illegal mix of collations" at runtime, in whatever query happens to
-- reach it first.
--
-- So: one engine, one charset, one collation, for every table this repository
-- actually uses.
--
--
-- WHY utf8mb4_unicode_520_ci
-- -------------------------
-- Because it is the newest Unicode Collation Algorithm available on both engines
-- this application could plausibly run on, and because the two "modern defaults"
-- people reach for are each unavailable here:
--
--   * MySQL 8's default `utf8mb4_0900_ai_ci` (UCA 9.0.0) does not exist on MariaDB.
--   * MariaDB's `utf8mb4_uca1400_*` family (UCA 14.0.0) arrived in MariaDB 11.
--     Production runs 10.11, which has *zero* of them — verified, not assumed.
--   * `utf8mb4_unicode_520_ci` (UCA 5.2.0) exists on both.
--
-- `utf8mb4_general_ci` was rejected outright. It is not a Unicode collation at all,
-- it is a legacy MySQL 4.x sort table, and it gets these wrong where 520 gets them
-- right — measured on this server, not quoted from documentation:
--
--       straße = strasse    general_ci: no    unicode_520_ci: yes
--       Æon    = Aeon       general_ci: no    unicode_520_ci: yes
--       Œuvre  = Oeuvre     general_ci: no    unicode_520_ci: yes
--       ﬁnal   = final      general_ci: no    unicode_520_ci: yes
--       Ⅻ      = XII        general_ci: no    unicode_520_ci: yes
--
-- For a catalogue holding German, Spanish and Portuguese titles, `straße` failing
-- to match `strasse` is a live search bug. The older `utf8mb4_unicode_ci`
-- (UCA 4.0.0, 2003) gets four of those five right but misses Æ/AE.
--
-- Cost of the collation change on current data: nothing. Measured before writing
-- this file — 0 unique-key collisions on `lib_books`, and identical `DISTINCT`
-- counts under the old and new collations for `lib_books.author` (12,406),
-- `.category` (864), `.public_tags` (738) and `sch_roles.RoleTitle` (39), across
-- 8,431 rows containing non-ASCII authors. Nothing merges and nothing splits; the
-- collations differ only on data that does not exist here yet.
--
-- One caveat for whoever moves this database to MariaDB 11 or MySQL 8: every
-- pre-`uca1400` collation is PAD SPACE, meaning trailing spaces are ignored in
-- comparison, and both modern families are NO PAD. That semantic changes once more
-- at that jump. It is a property of the era, not of this choice.
--
--
-- >>> PREREQUISITE: THE CONNECTION CHARSET <<<
-- ------------------------------------------
-- Storing utf8mb4 is half the job. The application connects with
--
--     PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8;'
--
-- and in MariaDB `utf8` means `utf8mb3`. On a utf8mb3 connection the server
-- silently converts 4-byte characters down to `?` on the way out — demonstrated on
-- this server, where two *different* emoji sent over such a connection came back
-- identical and compared equal. Until that line says `SET NAMES utf8mb4`, this
-- migration buys storage the application can neither read nor write.
--
-- `config/autoload/local.php` is gitignored, so production's copy is a server-side
-- file. After running this migration, edit it there:
--
--     'driver_options' => [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4;'],
--
-- Either order is safe, because no 4-byte data exists yet. Tables first is
-- recommended, since a utf8mb4 connection against utf8mb3 tables is the more
-- surprising of the two intermediate states.
--
--
-- SCOPE: 29 TABLES, AND THE RULE THAT PICKED THEM
-- -----------------------------------------------
-- A table is in scope if this repository mentions it — in `module/`, `src/`,
-- `config/`, `templates/`, `tools/`, `bin/`, `test/` or `public/`. Historical
-- migrations under `database/` and prose in `docs/` deliberately do not count; old
-- migration files name nearly every table this project has ever had, and counting
-- them would make the rule meaningless.
--
-- Sixteen tables are therefore NOT converted here. They are a server maintenance
-- task, not an application concern, and are listed in `docs/BACKLOG.md`:
--
--   * The nine Bible tables (`bib_books`, `bib_book_abbreviations`, `bib_dh_page`,
--     `bib_greek_root_words`, `bib_verses`, and the four `bib_*_temp` tables that
--     exist only in production). The Bible module was removed 2026-08-05 and the
--     feature moved to another application. `bib_verses` is the largest MyISAM
--     table in production at 236,422 rows / 45.8 MiB.
--   * The five `b_bib*` tables — the source data for a one-time import from the old
--     "sion" bibliography system. The only code that read them was
--     `PublicationsTable::importPublications()`, which this branch deletes: it
--     called `issett()`, a typo, so it fatalled on every invocation and cannot have
--     run in years.
--   * `sch_dictionary_dictionary` (3,084 rows) and `sch_dictionary_users` (10 rows,
--     and it has a `password` column — legacy credential hashes with nothing
--     reading them). No code references either. The live dictionary is
--     `sch_dictionary_entries`, which IS converted below.
--   * `user_remember_me`. No code references it. It holds a foreign key into
--     `user`, but that key is on `user_id INT` — verified — so converting `user`
--     does not touch it.
--   * `sch_visits_rollover_2023-11-02` and `sch_visits_rollover_2025-07-17`.
--
-- `sch_visits` IS in scope (`config/autoload/sionmodel.global.php` names it as
-- `visits_table`) but is NOT in this file. It is ~7.1M rows / 1.3 GiB in
-- production against 1,038 rows in the capsule, so its rebuild dwarfs everything
-- here combined and cannot be rehearsed locally at size. It has its own migration,
-- `db7.1.sql`, to be run over SSH rather than through phpMyAdmin.
--
--
-- THE TWO THINGS THAT COULD HAVE CORRUPTED DATA
-- ---------------------------------------------
-- 1. The five `latin1_swedish_ci` tables. `CONVERT TO CHARACTER SET` reinterprets
--    bytes, so if UTF-8 text had ever been written into a latin1 column — the
--    classic double-encoding mess — this migration would mojibake it permanently.
--    Checked byte-exactly: of every non-ASCII value in every latin1 column across
--    `comments`, `events`, `files`, `predicates` and `relationships`, exactly two
--    rows exist (both in `comments.Comment`) and NEITHER is already-UTF-8. The
--    bytes are genuine latin1 — `podrían`, `años`, `México`, correct when read as
--    latin1 and invalid as UTF-8. So a plain `CONVERT TO` is the right operation,
--    and the BLOB round-trip usually recommended for this situation would itself
--    have been the corruption here.
--
--    RE-RUN THAT CHECK AGAINST PRODUCTION BEFORE APPLYING. The capsule is a dump
--    and rows may have been added since. It must return zero:
--
--      SELECT COUNT(*) FROM `comments`
--       WHERE `Comment` <> CONVERT(`Comment` USING ascii)
--         AND HEX(CONVERT(CONVERT(BINARY(`Comment`) USING utf8mb4) USING binary))
--             = HEX(`Comment`);
--
-- 2. The one string foreign key. Every other FK in this database is on an `int`
--    column and is unaffected by charset work, but `relationships.PredicateKind`
--    references `predicates.PredicateKind` and both are varchar. Converting either
--    side raises error 1833, and `SET FOREIGN_KEY_CHECKS = 0` does not suppress it
--    — that flag governs row validation, not column-type compatibility. This was
--    the first approach tried and it failed against the capsule. The constraint is
--    dropped, both tables convert, and it is recreated identically. See the block
--    below for the detail.
--
--
-- COLUMN TYPE PROMOTIONS, DECLARED RATHER THAN IMPLIED
-- ---------------------------------------------------
-- `CONVERT TO CHARACTER SET` silently changes column types to preserve character
-- capacity: a utf8mb3 `TEXT` holds 21,845 characters and a utf8mb4 `TEXT` only
-- 16,383, so the server promotes it to `MEDIUMTEXT`. Across these 29 tables that
-- affects 45 columns — 31 `TEXT` -> `MEDIUMTEXT` and 14 `MEDIUMTEXT` -> `LONGTEXT`.
--
-- Rather than let that happen invisibly, every one of the 45 is written out as an
-- explicit `MODIFY` below. The result is identical to what the server would have
-- done on its own; the point is that it appears in the diff and in review instead
-- of only in a later `SHOW CREATE TABLE`.
--
-- (Noted in passing, not fixed here: `csp_reports` stores `line_number`,
-- `column_number` and `status_code` as MEDIUMTEXT, which is why they become
-- LONGTEXT. That is a schema design worth revisiting on its own terms.)
--
--
-- STRUCTURAL CHECKS, ALL CLEAR
-- ----------------------------
-- Verified against every table below before writing this:
--   * No index key exceeds the 3072-byte limit under utf8mb4.
--   * No table's VARCHAR columns approach the 65,535-byte row limit.
--   * Every table is already ROW_FORMAT=Dynamic, so the 3072-byte prefix applies
--     rather than the old 767-byte one.
--   * AUTO_INCREMENT survives the MyISAM -> InnoDB change *and* a server restart.
--     MariaDB 10.11 persists the counter (MDEV-6076); it does not recompute it as
--     MAX(id)+1, which would have let deleted ids be reused. That mattered because
--     `lib_checkouts.BookId` points at `lib_books.book_id` with no foreign key to
--     stop reuse from silently re-pointing checkout history at a different book.
--     Tested directly in the capsule rather than taken on trust.
--
--
-- PREREQUISITES, AND RE-RUNNING THIS FILE
-- ---------------------------------------
-- `db6.7.sql` must have been applied, because it creates `user_api_token`, which
-- is converted below. Production has it. A capsule built from the 2021 dump does
-- not until `db6.6` through `db6.9` are applied, which is what a local rehearsal
-- has to do first for the rehearsal to mean anything.
--
-- `jtranslate_migration` is handled differently — see its guard below — because it
-- is created by JTranslate's migration runner rather than by any migration file,
-- so its absence is a legitimate state rather than a missing prerequisite.
--
-- This file is safe to re-run. `CONVERT TO CHARACTER SET` against a table already
-- in the target charset rebuilds it to the same definition, and the `MODIFY`
-- statements are idempotent. That matters because the first version of this
-- migration aborted partway through on the foreign key described below, leaving
-- twelve tables converted and seventeen not; re-running after the fix was the
-- recovery, and it worked.
--
--
-- ROLLBACK
-- --------
-- Converting back (`CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci`,
-- `ENGINE=MyISAM`) is possible and lossless *only* until something writes a
-- 4-byte character or a character the old collation cannot round-trip. After the
-- connection charset is switched and the site has run for any length of time,
-- restoring from backup is the honest rollback. Take one first.
--
-- comments — InnoDB, latin1_swedish_ci, 8 rows
ALTER TABLE `comments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- csp_reports — InnoDB, utf8mb3_general_ci, 0 rows
ALTER TABLE `csp_reports`
  MODIFY `full_report` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  MODIFY `document_uri` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  MODIFY `referrer` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  MODIFY `violated_directive` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  MODIFY `original_policy` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  MODIFY `blocked_uri` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  MODIFY `source_file` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  MODIFY `line_number` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  MODIFY `column_number` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  MODIFY `status_code` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL;
ALTER TABLE `csp_reports` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- events — InnoDB, latin1_swedish_ci, 527 rows
ALTER TABLE `events`
  MODIFY `PublicNotes` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `AdminNotes` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE `events` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- files — InnoDB, latin1_swedish_ci, 0 rows
ALTER TABLE `files` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- jtranslate_migration — InnoDB, utf8mb4_unicode_ci, 2 rows
--
-- Guarded, because unlike every other table here this one is not created by any
-- migration file: JTranslate's MigrationRunner creates it the first time it runs.
-- An installation that has never run `bin/console jtranslate:migrate` does not have
-- it, and an unguarded ALTER would abort the rest of this migration. Production
-- has it; a capsule built from the 2021 dump does not.
--
-- It is also the one table already on utf8mb4 — the runner created it as
-- utf8mb4_unicode_ci (UCA 4.0.0). This moves it to 5.2.0 with everything else; the
-- runner itself now emits 520 directly.
SET @sql := (
  SELECT IF(COUNT(*) > 0,
    'ALTER TABLE `jtranslate_migration` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci',
    'DO 0')
  FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'jtranslate_migration'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- lib_books — MyISAM, utf8mb3_general_ci, 33690 rows
ALTER TABLE `lib_books` ENGINE=InnoDB;
ALTER TABLE `lib_books` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- lib_checkouts — InnoDB, utf8mb3_general_ci, 1953 rows
ALTER TABLE `lib_checkouts` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- lib_collections — InnoDB, utf8mb3_general_ci, 7 rows
ALTER TABLE `lib_collections` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- lib_imports — InnoDB, utf8mb3_general_ci, 14 rows
ALTER TABLE `lib_imports` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- lib_libraries — InnoDB, utf8mb3_general_ci, 6 rows
ALTER TABLE `lib_libraries` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- mailings — InnoDB, utf8mb3_general_ci, 108 rows
ALTER TABLE `mailings`
  MODIFY `Body` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `MailingText` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `StackTrace` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE `mailings` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- mus_compositions — InnoDB, utf8mb3_general_ci, 307 rows
ALTER TABLE `mus_compositions`
  MODIFY `Lyrics` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `ChordProSpec` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `LilyPondSpec` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE `mus_compositions` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- predicates + relationships — InnoDB, latin1_swedish_ci, 46 and 8 rows
--
-- These two carry the only string foreign key in the database:
-- relationships.PredicateKind references predicates.PredicateKind, both varchar(50).
--
-- MariaDB raises error 1833 ("Cannot change column ... used in a foreign key
-- constraint") when converting either side, and SET FOREIGN_KEY_CHECKS = 0 does
-- NOT suppress it — that flag governs row validation, not column-type
-- compatibility. This was tried first and failed against the capsule. So the
-- constraint is dropped, both tables convert, and it is recreated identically
-- (RESTRICT/RESTRICT, i.e. the default, which is why no ON clause is written).
--
-- The `relationships_fk0` index on PredicateKind survives the DROP FOREIGN KEY and
-- is reused by the ADD CONSTRAINT.
ALTER TABLE `relationships` DROP FOREIGN KEY `relationships_fk0`;
ALTER TABLE `predicates` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE `relationships` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE `relationships`
  ADD CONSTRAINT `relationships_fk0` FOREIGN KEY (`PredicateKind`)
      REFERENCES `predicates` (`PredicateKind`);

-- sch_assignments — MyISAM, utf8mb3_general_ci, 266 rows
ALTER TABLE `sch_assignments` ENGINE=InnoDB;
ALTER TABLE `sch_assignments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- sch_associations — InnoDB, utf8mb3_general_ci, 501 rows
ALTER TABLE `sch_associations`
  MODIFY `PublicNotes` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `AdminNotes` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `VisitorsInformationEn` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `VisitorsInformationEs` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `VisitorsInformationDe` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `VisitorsInformationPt` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `VisitorsInformationFr` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `HistoryEn` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `HistoryEs` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `HistoryDe` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `HistoryPt` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `HistoryFr` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `OpeningHoursSpecification` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `EventsJson` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE `sch_associations` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- sch_changes — InnoDB, utf8mb3_general_ci, 137321 rows
ALTER TABLE `sch_changes`
  MODIFY `NewValue` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `OldValue` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE `sch_changes` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- sch_dictionary_entries — InnoDB, utf8mb3_general_ci, 2788 rows
ALTER TABLE `sch_dictionary_entries` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- sch_persons — InnoDB, utf8mb3_general_ci, 325 rows
ALTER TABLE `sch_persons`
  MODIFY `PublicNotes` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `AdminNotes` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `ContactNotes` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE `sch_persons` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- sch_publications — InnoDB, utf8mb3_general_ci, 9690 rows
ALTER TABLE `sch_publications`
  MODIFY `Description` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `PublicNotes` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `AdminNotes` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `EditionNotes` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE `sch_publications` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- sch_pub_categories — InnoDB, utf8mb3_general_ci, 16 rows
ALTER TABLE `sch_pub_categories` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- sch_roles — MyISAM, utf8mb3_general_ci, 1468 rows
ALTER TABLE `sch_roles` ENGINE=InnoDB;
ALTER TABLE `sch_roles` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- texts — InnoDB, utf8mb3_general_ci, 1918 rows
ALTER TABLE `texts`
  MODIFY `MarkdownText` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `HtmlText` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `PlainText` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  MODIFY `SearchText` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE `texts` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- trans_phrases — InnoDB, utf8mb3_general_ci, 3074 rows
ALTER TABLE `trans_phrases` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- trans_translations — InnoDB, utf8mb3_general_ci, 14273 rows
ALTER TABLE `trans_translations` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- user — InnoDB, utf8mb3_unicode_ci, 563 rows
ALTER TABLE `user` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- user_api_token — InnoDB, utf8mb3_unicode_ci, 1 rows
ALTER TABLE `user_api_token` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- user_role — InnoDB, utf8mb3_general_ci, 43 rows
ALTER TABLE `user_role` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- user_role_linker — InnoDB, utf8mb3_general_ci, 3805 rows
ALTER TABLE `user_role_linker` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;


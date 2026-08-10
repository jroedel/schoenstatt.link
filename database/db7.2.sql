-- db7.2 — remove the blog's translation phrases
--
-- WHY
-- ---
-- The blog was removed from the application in f19a5ca. Its phrases were not, because
-- nothing has ever removed a phrase from `trans_phrases`: `deletePhrase()` cascades
-- the translations away and `trans_translations` has no history to recover them from,
-- so leaving rows in place was always the safer choice and the table only grew.
--
-- Measured in the capsule, of 6,856 rows belonging to project `Schoenstatt`, 5,125 came
-- from a `blog%` route and held **40** distinct phrases. After JTranslate's migrations
-- 003 and 004 have merged the duplicates, that collapses to **39 phrase rows carrying 78
-- translation rows** — which is what this file actually deletes. Production is within a
-- few rows of the same; see the note on counts below.
--
--
-- WHAT ACTUALLY PRODUCED THEM, BECAUSE IT IS NOT WHAT IT LOOKS LIKE
-- -----------------------------------------------------------------
-- 5,088 of those rows are exactly 2,000 characters long and hold **two** distinct
-- strings — two blog post bodies, at 2,636 and 2,452 copies each.
--
-- `trans_phrases.phrase` was `varchar(2000)`. Under the non-strict `sql_mode` these
-- tables were created with, a phrase longer than that was silently truncated on
-- insert, and from that moment the row could never be recognised again: JTranslate
-- tests a hash of the *full* phrase against an index built from the *stored* one, and
-- the two can never match. So every render of those two blog posts inserted another
-- row. One row per pageview, for years.
--
-- That is fixed in the library, not here — JTranslate's migrations 003 and 004 widen
-- the column, add a `phrase_hash` with `UNIQUE (project, text_domain, phrase_hash)`,
-- and merge whatever duplicates a database already has. **Run them first.** If this
-- file runs first the deletion still works, but M004 then has less to merge and the
-- counts below will not match what you see.
--
--     php bin/console jtranslate:migrate --pretend     # on a machine with the code
--     php bin/console jtranslate:migrate --status
--
--
-- WHAT IS LOST, STATED PLAINLY
-- ----------------------------
-- `origin_route` records where a phrase was **first seen**, not where it is used. The
-- phrase index is keyed by text, so whichever page rendered a string first owns its
-- origin route permanently. Deleting `WHERE origin_route LIKE 'blog%'` therefore also
-- removes strings that are live elsewhere in the site and merely appeared on a blog
-- page first. In the capsule that is at least:
--
--     Switch kernel               blog             the Symfony canary toggle
--     Italian                     blog/create      a language name, on every create form
--     Original language           blog/create      a form label
--     Is draft?                   blog/create      a form label
--     Text successfully created.  blog/blog-post   the texts module's flash message
--     Text successfully updated.  blog/blog-post   the same
--
-- Those six rows come back automatically the next time a page renders them — that is
-- the normal discovery path — but they come back **untranslated**. Across all `blog%`
-- rows there are 39 non-English translations (25 es, 8 pt, 6 de), and the ones
-- attached to strings still in use have to be typed again by a human.
--
-- This was a deliberate choice in favour of a smaller table. The alternative was
-- `jtranslate:retire --origin-route='blog%'`, which hides the same rows from the
-- translator's worklist while leaving them in place, so that anything still in use
-- un-retires itself with its translations intact. That command exists and is the
-- recommended tool for the *next* cleanup of this kind.
--
--
-- THE BACKUP IS NOT OPTIONAL
-- --------------------------
-- Because of the paragraph above, the two `CREATE TABLE ... SELECT` statements below
-- are what makes this reversible at all. They copy every row this file deletes into
-- `*_blog_backup` tables in the same database. Keep them until the site has been
-- browsed in all four locales and nobody has reported a missing translation; then
-- drop them by hand:
--
--     DROP TABLE trans_translations_blog_backup, trans_phrases_blog_backup;
--
-- To restore one phrase and its translations from the backup, insert the phrase row
-- first (letting AUTO_INCREMENT assign a new id), then its translations against that
-- new id. The old ids cannot be reused: the site will have re-created some of these
-- phrases under new ids by the time anybody looks.
--
--
-- ABOUT THE COUNTS
-- ----------------
-- An earlier version of this header warned that production's duplicate count would be
-- far larger than the capsule's, and told you to run the DELETE in batches of 10,000.
-- **That was wrong and is corrected here**, because the deploy has now happened and the
-- real numbers are known.
--
-- Production's `trans_phrases` stood at AUTO_INCREMENT=12842 against the capsule's
-- 12803 — 39 additional inserts, ever. The duplication was a burst that ended when
-- those two blog posts stopped being read, not an ongoing accumulation, so the table
-- was never large. JTranslate's migrations 003 and 004 ran against the live database on
-- 2026-08-10 in about 1.2 seconds of statement time, taking project `Schoenstatt` from
-- 6,895 phrases to 1,787 and `trans_translations` from 12,957 rows to 7,846.
--
-- So: no batching, and this file is a matter of seconds. Check before and after anyway,
-- because a count that surprises you is the one thing that should stop you:
--
--     SELECT COUNT(*) FROM trans_phrases WHERE project='Schoenstatt' AND origin_route LIKE 'blog%';
--
-- The capsule answered 39 there after the merge, holding 78 translation rows.
--
--
-- WHAT ACTUALLY HAPPENS TO THE SIX LIVE STRINGS — DEMONSTRATED, NOT PREDICTED
-- --------------------------------------------------------------------------
-- Rehearsed in the capsule on 2026-08-10. After this file ran, rendering the pages that
-- use them re-created all of them within one request each:
--
--     phrase                       origin_route    retired_on   translations
--     Is draft?                    texts/create    NULL         en_US only
--     Italian                      texts/create    NULL         en_US only
--     Original language            texts/create    NULL         en_US only
--     Text successfully created.   texts/create    NULL         en_US only
--     Text successfully updated.   texts/create    NULL         en_US only
--
-- Two things to read off that. The rows come back with an **honest** `origin_route` —
-- `texts/create`, where they are actually used, not the stale `blog/create` they carried
-- for years. And they come back with the key locale only: the es/de/pt translations are
-- gone, exactly as this header says. That is the cost that was accepted.
--
--
-- HOW TO RUN IT
-- -------------
-- Over SSH with the mysql client, as with db7.0 and db7.1:
--
--     mysql -u <user> -p ourlink_db1 < db7.2.sql
--
-- Then clear the caches, because both of JTranslate's derived cache items are now
-- stale and neither is invalidated by a change made outside the application:
--
--     curl -sS https://schoenstatt.link/en/sm/clear-cache
--
-- and recompile the catalogs, so the deleted phrases stop being written into them:
--
--     php bin/console jtranslate:export-catalogs
--
-- Skipping the recompile is not dangerous — a stale catalog entry for a phrase nobody
-- renders is inert — but it leaves the `*.lang.php` files disagreeing with the
-- database, and those files are supposed to be a pure derivative of it.

-- --------------------------------------------------------------------------------
-- 1. Back up what is about to be deleted.
-- --------------------------------------------------------------------------------

DROP TABLE IF EXISTS `trans_translations_blog_backup`;
DROP TABLE IF EXISTS `trans_phrases_blog_backup`;

CREATE TABLE `trans_phrases_blog_backup` AS
SELECT *
FROM `trans_phrases`
WHERE `project` = 'Schoenstatt'
  AND `origin_route` LIKE 'blog%';

-- Selected by joining to the backup rather than repeating the LIKE, so the two tables
-- cannot disagree about what was in scope.
CREATE TABLE `trans_translations_blog_backup` AS
SELECT t.*
FROM `trans_translations` t
JOIN `trans_phrases_blog_backup` b
  ON b.`translation_phrase_id` = t.`translation_phrase_id`;

-- --------------------------------------------------------------------------------
-- 2. Delete. The foreign key cascades, so the translations go with the phrases and
--    `trans_translations` is not named here.
-- --------------------------------------------------------------------------------

DELETE FROM `trans_phrases`
WHERE `project` = 'Schoenstatt'
  AND `origin_route` LIKE 'blog%';

-- --------------------------------------------------------------------------------
-- 3. Report. Both counts should be zero; the backup counts are what was removed.
-- --------------------------------------------------------------------------------

SELECT 'phrases remaining under blog%' AS check_name,
       COUNT(*) AS value
FROM `trans_phrases`
WHERE `project` = 'Schoenstatt' AND `origin_route` LIKE 'blog%'
UNION ALL
SELECT 'orphaned translations', COUNT(*)
FROM `trans_translations` t
LEFT JOIN `trans_phrases` p ON p.`translation_phrase_id` = t.`translation_phrase_id`
WHERE p.`translation_phrase_id` IS NULL
UNION ALL
SELECT 'phrases backed up', COUNT(*) FROM `trans_phrases_blog_backup`
UNION ALL
SELECT 'translations backed up', COUNT(*) FROM `trans_translations_blog_backup`;

-- db7.4 — retire the record names a translated breadcrumb filed as phrases, and finish
--         db7.3's renames in English
--
-- Statements 1–5 retire record content: change request §12. Statements 6 and 7 correct the
-- `en_US` rows db7.3 left holding the old text: §13. They are unrelated defects that share
-- one property — both are invisible from a rendered page in the language you are reading.
--
-- (The "db7.4" db7.3's header promised — the append-only translation history — shipped as
-- JTranslate migration 006 instead, so this number was still free.)
--
-- WHY
-- ---
-- The navigation carries one page per publication, composition, library and association:
-- ten thousand publications alone, each labelled with the row's own title. Until
-- 2026-08-10 nothing translated a breadcrumb label, so that cost nothing. The fix for
-- change request §4 — "an Italian shrine page said Santuari in the menu and Shrines in the
-- breadcrumb directly below it" — started translating them, and a translator miss is
-- exactly how a phrase is filed. Within a day 3,071 publication titles were 61% of the
-- whole phrase table, each filed twice because the partial falls back to the `default`
-- domain when the navigation domain misses.
--
-- The code fix is in the same commit as this file: Application\Module::markDataLabels()
-- flags every navigation page whose label came out of a database row, and
-- partial/breadcrumbs.phtml renders a flagged label untouched. **That has to be deployed
-- before this runs**, or the next crawl of a publication page files the rows again — and
-- `retired_on` is cleared by discovery, so they would come back un-retired.
--
-- A work's title is the title of that work. `Apologia pro vita mea`, `Das Ehe-Ideal`,
-- `A házaspárok útján` are not translatable in any language, and a Portuguese rendering
-- would invent an edition that does not exist. Where a translated edition does exist, its
-- title belongs on that publication's own record, which is where every other title is.
--
-- Reported by the agent that translated 1,383 phrases into Italian, as §12 of
-- `api-change-requests.md`; answered in `docs/api-change-requests-response.md` §12. Its
-- diagnosis named `headTitle()`, which was already `setTranslatorEnabled(false)` on both
-- show pages — the breadcrumb was the path. The rows it counted are the ones this retires.
--
--
-- WHAT MAKES IT SAFE
-- ------------------
-- Retired, not deleted: `retired_on` is a statement about the translator's worklist and it
-- is reversible, where deletion of `trans_translations` is not (db7.2 §"what cannot be
-- recovered"). A retired phrase keeps rendering, and any phrase the site still looks up
-- un-retires itself on the next miss.
--
-- Three conditions together, and each one alone would be too broad:
--
--   1. **The text is a record's own name**, joined against the table it comes from rather
--      than matched by a pattern. 10,166 publication titles include short generic words,
--      which is why the join is not enough by itself.
--   2. **The origin route is the record's own show page** — where a breadcrumb naming that
--      record renders. `origin_route` records where a phrase was *first seen*, so this is a
--      claim about how the row arrived, which is the thing being undone.
--   3. **Nothing has ever translated it beyond English.** The English row here is the
--      auto-filled verbatim copy discovery writes; a real interface string that somebody
--      has translated is left alone even if it collides with a title. On `publication` the
--      rows failing this test are precisely the nine real interface strings — `Data
--      source`, `Date published`, `Leave a comment`, `Total views`, `Copy into main
--      corpus` and the rest.
--
--      **This third condition turned out to be weaker than it reads, and db7.6 replaces
--      it.** `writeMissingPhrasesToDb()` copies translations onto a newly discovered
--      phrase from any row of the same project holding the same text in another text
--      domain, so a title that collides with an already-translated string arrives
--      pre-translated in four languages and passes this test seconds after being filed.
--      Six such rows survived on production and db7.6 retires them, scoping on the route
--      and text domain — which the breadcrumb determines and a translation cannot forge —
--      rather than on how translated the row happens to be.
--
-- Association names are treated differently and deliberately so. `IsNameTranslateable` and
-- the kind name formats mean SchoenstattTable translates some names on purpose, in the
-- `Schoenstatt` domain, and §6 of the same report is the standing warning about deleting a
-- feature on a wrong premise. So statement 4 touches only the `Application` and `default`
-- domains — where the breadcrumb's two-step lookup filed them — and only rows added since
-- the breadcrumb change. The designed rows in `Schoenstatt` are untouched.
--
-- Scoped to project 'Schoenstatt' throughout. Three other projects share these tables.
--
--
-- ORDER
-- -----
-- Deploy the code first, then this file, then rebuild the catalogs:
--
--     php bin/console jtranslate:export-catalogs && php bin/console cache:flush-persistent
--
-- To reverse: `UPDATE trans_phrases SET retired_on = NULL WHERE …` with the same WHERE, or
-- `php bin/console jtranslate:retire --undo --origin-route=publication`, which un-retires
-- everything on that route rather than only these.


-- 1. Publication titles. `publications/publication` is the pre-2020 name of the same show
--    route; rows carrying it are from the first time this happened, in 2019.
UPDATE `trans_phrases` p
JOIN `sch_publications` r ON r.`Title` = p.`phrase`
SET p.`retired_on` = UTC_TIMESTAMP()
WHERE p.`project`      = 'Schoenstatt'
  AND p.`retired_on`   IS NULL
  AND p.`origin_route` IN ('publication', 'publications/publication')
  AND NOT EXISTS (
      SELECT 1 FROM `trans_translations` t
      WHERE t.`translation_phrase_id` = p.`translation_phrase_id`
        AND t.`locale` <> 'en_US'
  );

-- 2. Composition names.
UPDATE `trans_phrases` p
JOIN `mus_compositions` r ON r.`CompositionName` = p.`phrase`
SET p.`retired_on` = UTC_TIMESTAMP()
WHERE p.`project`      = 'Schoenstatt'
  AND p.`retired_on`   IS NULL
  AND p.`origin_route` IN ('composition', 'compositions/composition')
  AND NOT EXISTS (
      SELECT 1 FROM `trans_translations` t
      WHERE t.`translation_phrase_id` = p.`translation_phrase_id`
        AND t.`locale` <> 'en_US'
  );

-- 3. Library names.
UPDATE `trans_phrases` p
JOIN `lib_libraries` r ON r.`LibraryName` = p.`phrase`
SET p.`retired_on` = UTC_TIMESTAMP()
WHERE p.`project`      = 'Schoenstatt'
  AND p.`retired_on`   IS NULL
  AND p.`origin_route` IN ('libraries/library', 'libraries/library/show')
  AND NOT EXISTS (
      SELECT 1 FROM `trans_translations` t
      WHERE t.`translation_phrase_id` = p.`translation_phrase_id`
        AND t.`locale` <> 'en_US'
  );

-- 4. Association names, in the two domains the breadcrumb looked them up in and nowhere
--    else. `Schoenstatt` is where the translateable ones live by design.
UPDATE `trans_phrases` p
JOIN `sch_associations` r ON r.`AssociationName` = p.`phrase`
SET p.`retired_on` = UTC_TIMESTAMP()
WHERE p.`project`      = 'Schoenstatt'
  AND p.`retired_on`   IS NULL
  AND p.`origin_route` = 'association'
  AND p.`text_domain`  IN ('Application', 'default')
  AND p.`added_on`     >= '2026-08-10'
  AND NOT EXISTS (
      SELECT 1 FROM `trans_translations` t
      WHERE t.`translation_phrase_id` = p.`translation_phrase_id`
        AND t.`locale` <> 'en_US'
  );

-- 5. The composed labels above them in the same trails: `German Schoenstatt Literature`
--    (Module.php builds it as `$languageName . ' Schoenstatt Literature'`) and
--    `German to Spanish Dictionary` (`sprintf('German to %s Dictionary', …)`). Neither is
--    translatable as written, which is the same reason §3's concatenated messages were not.
--
--    A phrase containing a literal `%` is excluded: that is the sprintf *template*, and
--    `German to %s Dictionary` is a real translated row — the site translates the template
--    and interpolates, which is what these labels should have done too.
UPDATE `trans_phrases`
SET `retired_on` = UTC_TIMESTAMP()
WHERE `project`      = 'Schoenstatt'
  AND `retired_on`   IS NULL
  AND `origin_route` IN (
      'publication', 'publications/publication', 'publications/index',
      'composition', 'association', 'libraries/library', 'dictionary/inLanguage'
  )
  AND (`phrase` LIKE '% Schoenstatt Literature' OR `phrase` LIKE 'German to % Dictionary')
  AND `phrase` NOT LIKE '%\%%'
  AND NOT EXISTS (
      SELECT 1 FROM `trans_translations` t
      WHERE t.`translation_phrase_id` = `trans_phrases`.`translation_phrase_id`
        AND t.`locale` <> 'en_US'
  );


-- 6 and 7. Finish db7.3's renames: the English row is a translation too.
--
--    §13 of the report, found by the only reader who compares each key against its own
--    English row. db7.3 corrected three phrases *in place* so their translations would
--    follow the corrected text — which worked for the four languages whose translations
--    were of the meaning. English is not one of those: `en_US` is an ordinary row in
--    `trans_translations`, auto-filled with a verbatim copy of the key when the phrase was
--    discovered, and English pages render it rather than the key. So the key was corrected
--    while English went on saying the thing the rename existed to remove.
--
--    The reporter set them through the v3 API on production the same day. This is here
--    because the repository is what makes every other environment agree — a capsule
--    imported from any dump before that still shows the typos — and because the rule is
--    general: **a rename in place must update `en_US` in the same migration.**
--
--    Guarded on the texts db7.3 produced and on the row actually differing, so it is
--    re-runnable and a no-op where the API already did it. The history row goes first,
--    with `written_on`/`written_by` carried over from the row being replaced, because this
--    destroys a translation and the whole point of `trans_translations_history` is that
--    nothing does that silently. `replaced_by` is null: no user account ran this.
INSERT INTO `trans_translations_history`
  (`project`, `phrase_hash`, `locale`, `text_domain`, `translation_phrase_id`,
   `old_translation`, `operation`, `notes`, `written_by`, `written_on`, `replaced_by`, `replaced_on`)
SELECT p.`project`, p.`phrase_hash`, t.`locale`, p.`text_domain`, p.`translation_phrase_id`,
       t.`translation`, 'update',
       'db7.4: db7.3 renamed this phrase in place to correct a typo; the en_US row still held the old text, so English readers kept seeing it.',
       t.`modified_by`, t.`modified_on`, NULL, UTC_TIMESTAMP()
FROM `trans_phrases` p
JOIN `trans_translations` t
  ON  t.`translation_phrase_id` = p.`translation_phrase_id`
  AND t.`locale` = 'en_US'
WHERE p.`project` = 'Schoenstatt'
  AND t.`translation` <> p.`phrase`
  AND p.`phrase` IN (
      'This is the name that will be shown to most users of the page (supposing most users are Schoenstatters). This field has no name formats as the public name field does. If the internal name would be the same as the public name, please leave blank.',
      'Tags regarding the content, form or liturgical use of the song.',
      'This data may be accessed directly by any apps or websites which wish to receive up-to-date information via the %sREST API%s (application programming interface).'
  );

UPDATE `trans_translations` t
JOIN `trans_phrases` p ON p.`translation_phrase_id` = t.`translation_phrase_id`
SET t.`translation` = p.`phrase`,
    t.`modified_on` = UTC_TIMESTAMP()
WHERE p.`project` = 'Schoenstatt'
  AND t.`locale`  = 'en_US'
  AND t.`translation` <> p.`phrase`
  AND p.`phrase` IN (
      'This is the name that will be shown to most users of the page (supposing most users are Schoenstatters). This field has no name formats as the public name field does. If the internal name would be the same as the public name, please leave blank.',
      'Tags regarding the content, form or liturgical use of the song.',
      'This data may be accessed directly by any apps or websites which wish to receive up-to-date information via the %sREST API%s (application programming interface).'
  );

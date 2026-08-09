-- db6.9 — two pt_BR translations that existed only in a committed catalog
--
-- WHY
-- ---
-- The compiled `.lang.php` catalogs stop being tracked in git as of this change:
-- they are build output, written from `trans_phrases`/`trans_translations` by
-- `TranslationsTable::writePhpTranslationArrays()` and rebuilt by
-- `bin/console jtranslate:export-catalogs`.
--
-- Before untracking them, every key of every committed catalog was compared against
-- what a fresh export produces. Almost all of the difference was harmless: keys whose
-- phrase no longer exists in this project's table (so nothing ever looks them up), and
-- keys whose phrase moved to another text domain (so the export writes them, just
-- somewhere else).
--
-- Two were not harmless. These two phrases still exist, are still rendered, and their
-- Portuguese translation lived **only** in the committed file — there is no row for it
-- in `trans_translations`. Deleting the file without this migration would have made
-- both fall back to English on the next deploy, silently, with the only copy of the
-- Portuguese gone from the repository at the same moment.
--
-- So the translations move to where translations belong. After this, the catalogs are
-- fully derivable from the database, which is the property that makes untracking them
-- safe rather than merely tidy.
--
-- ABOUT THE SECOND ONE
-- --------------------
-- `Codigorz de barra` is carried across **exactly as it was found**. It is almost
-- certainly a typo for `Código de barras` — which is what the Spanish row says — but
-- it is what the site has been serving, and correcting it here would be an editorial
-- change smuggled into a data-preservation migration. Fix it in the translation GUI at
-- /admin/translations, where it is one edit and it is attributable.
--
-- IDEMPOTENT
-- ----------
-- Matched by project + text domain + phrase rather than by a hardcoded id, and skipped
-- when a pt_BR row already exists — so an environment whose database already has these
-- (or has since been given a better translation) is left alone. Safe to re-run.
-- `trans_translations` has a UNIQUE KEY on (translation_phrase_id, locale), so the
-- guard is belt and braces; the guard is what keeps a *better* translation from being
-- clobbered, which the unique key alone would not do.
--
-- `modified_by` is NULL on purpose: nobody alive wrote these rows, they were recovered
-- from a file, and inventing an author would put a false name in the admin listing.

INSERT INTO `trans_translations` (`translation_phrase_id`, `locale`, `translation`, `modified_on`, `modified_by`)
SELECT p.`translation_phrase_id`, 'pt_BR', 'É bilíngue?', NOW(), NULL
FROM `trans_phrases` p
WHERE p.`project` = 'Schoenstatt'
  AND p.`text_domain` = 'Application'
  AND p.`phrase` = 'Are you bilingual?'
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT `translation_phrase_id`, `locale` FROM `trans_translations`) existing
      WHERE existing.`translation_phrase_id` = p.`translation_phrase_id`
        AND existing.`locale` = 'pt_BR'
  );

INSERT INTO `trans_translations` (`translation_phrase_id`, `locale`, `translation`, `modified_on`, `modified_by`)
SELECT p.`translation_phrase_id`, 'pt_BR', 'Codigorz de barra', NOW(), NULL
FROM `trans_phrases` p
WHERE p.`project` = 'Schoenstatt'
  AND p.`text_domain` = 'Books'
  AND p.`phrase` = 'Barcode'
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT `translation_phrase_id`, `locale` FROM `trans_translations`) existing
      WHERE existing.`translation_phrase_id` = p.`translation_phrase_id`
        AND existing.`locale` = 'pt_BR'
  );

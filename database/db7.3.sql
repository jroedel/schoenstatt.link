-- db7.3 — carry translations across four corrected source strings, and retire three
--         phrases that were never language
--
-- WHY
-- ---
-- A phrase's identity is its text. Correcting a typo in a source literal therefore does
-- not correct the phrase — it abandons it. The old row keeps the four or five
-- translations somebody wrote, the corrected string arrives as a new row with none, and
-- the page renders in English until every language is done again. That is the whole
-- reason typos in source strings have survived here since 2017.
--
-- So the code change and this file are two halves of one change, and this half has to
-- run for the other one to be free. Each statement below renames a row *in place*:
-- the text and its `phrase_hash` together, which is what keeps the row findable — see
-- JTranslate\Model\PhraseIdentity, and db7.2 for what a phrase whose stored text
-- disagrees with its own hash does to a table.
--
-- Reported by the agent that translated 1,383 phrases into Italian on 2026-08-10
-- (`api-change-requests.md` §9). Its point is worth restating: it translated these
-- from the corrected sense rather than reproducing the error, so **the Italian is
-- already better than the English key**. Renaming the key is what makes the two agree.
--
--
-- THE FOUR RENAMES
-- ----------------
--   1. "…the name that would be that will be shown…"   → "…the name that will be shown…"
--      AssociationForm, `internalName` help block. The English translation row already
--      holds the corrected sentence, so this makes the key agree with its own en_US row.
--
--   2. "Tags regarding the content of the content, form…" → "Tags regarding the content, form…"
--      CompositionForm, `tags` help block.
--
--   3. "…any apps or websites who which to receive…"   → "…any apps or websites which wish to receive…"
--      The shrines and wayside-shrines index pages, one string shared by both. Not in
--      the report; found next to the one that was, in the same paragraph.
--
--   4. Nothing. The fourth string named in §9 —
--      "Until 2019-10-18, this page will be oriented towards data-maintainers…" — is not
--      a typo but a promise with a date on it that passed seven years ago, and the
--      redesign it announced never happened. The sentence is deleted from both
--      templates rather than reworded, so its phrase is retired below instead of
--      renamed. The report also notes that es, pt and de all render that date as the
--      10th where the key says the 18th, which stops mattering once nobody sees it.
--
-- Each rename is written to do nothing if the corrected text is already present in the
-- same (project, text_domain) — `UNIQUE (project, text_domain, phrase_hash)` would
-- refuse the update, and refusing it loudly in the middle of a deploy is worse than
-- leaving one row for the next render to rediscover. The LEFT JOIN over a derived table
-- is how MySQL allows a statement to look at the table it is updating.
--
--
-- THE THREE RETIREMENTS
-- ---------------------
-- All three are record data that leaked into the table years ago through a path that no
-- longer exists, and none of them is translatable:
--
--   * `Frau Dokto`      — a misspelt person title, from `persons/person`. No row of
--                         `sch_persons` has held it since it was corrected.
--   * `xxi+133`         — a page count, from `publications/create`.
--   * `Hey! Welcome to Schoenstatt Link! … clicking on the this link` — the old ZfcUser
--                         registration email, replaced by JUser's magic links. §9 lists
--                         its `on the this link` as a typo to fix; there is nothing left
--                         to fix it in.
--
-- Retired, not deleted, because `retired_on` is reversible and deletion is not — see
-- db7.2 on what `trans_translations` cannot recover. A retired phrase keeps rendering.
--
--
-- WHAT IS NOT HERE
-- ----------------
-- §9's remaining entries are wrong *translations*, not wrong keys, and this file cannot
-- fix a translation without destroying the one it replaces:
--
--   * `The form submitted did not originate from the expected site` (5 rows) — the en
--     and es rows describe a form that *expired*, a different error. Laminas' own Csrf
--     message; the key is correct.
--   * `Please type the following text` — Laminas' Captcha message, whose key lacks the
--     colon the four translations have.
--   * `Show editions separately?` — the Spanish row says "obsoletas", i.e. *obsolete*
--     editions.
--   * `Sun-Sat 9-11am…` — the Spanish row says Sábado-Domingo and drops a time range.
--   * `Ladies of Schoenstatt` — the Spanish row names Our Lady, a different body.
--   * `Madrugadores of %s` — the *English* row reads "Centinels of %s", for Sentinels.
--
-- Those belong to whoever owns Spanish and English, through /admin/translations or the
-- v3 API, and they are exactly the case for the append-only history db7.4 adds: today a
-- correction destroys the text it replaces with no record.
--
--
-- ORDER
-- -----
-- Run JTranslate's migrations first, 005 included — the renames compute a hash the way
-- PhraseIdentity now computes it, over normalized line endings, and a table that has not
-- had 005 applied stores hashes taken over the raw bytes.
--
--     php bin/console jtranslate:migrate --status
--
-- Then this file, then `php bin/console jtranslate:export-catalogs`, or the compiled
-- catalogs keep answering to the old keys and every renamed string renders in English.
--
-- Scoped to project 'Schoenstatt' throughout. Three other projects share these tables.

-- 1. AssociationForm, internalName help block
UPDATE `trans_phrases` p
LEFT JOIN (
  SELECT `project`, `text_domain`, `phrase_hash` FROM `trans_phrases`
) taken
  ON  taken.`project`     = p.`project`
  AND taken.`text_domain` = p.`text_domain`
  AND taken.`phrase_hash` = UNHEX(SHA2('This is the name that will be shown to most users of the page (supposing most users are Schoenstatters). This field has no name formats as the public name field does. If the internal name would be the same as the public name, please leave blank.', 256))
SET p.`phrase`      = 'This is the name that will be shown to most users of the page (supposing most users are Schoenstatters). This field has no name formats as the public name field does. If the internal name would be the same as the public name, please leave blank.',
    p.`phrase_hash` = UNHEX(SHA2('This is the name that will be shown to most users of the page (supposing most users are Schoenstatters). This field has no name formats as the public name field does. If the internal name would be the same as the public name, please leave blank.', 256))
WHERE p.`project` = 'Schoenstatt'
  AND p.`phrase`  = 'This is the name that would be that will be shown to most users of the page (supposing most users are Schoenstatters). This field has no name formats as the public name field does. If the internal name would be the same as the public name, please leave blank.'
  AND taken.`phrase_hash` IS NULL;

-- 2. CompositionForm, tags help block
UPDATE `trans_phrases` p
LEFT JOIN (
  SELECT `project`, `text_domain`, `phrase_hash` FROM `trans_phrases`
) taken
  ON  taken.`project`     = p.`project`
  AND taken.`text_domain` = p.`text_domain`
  AND taken.`phrase_hash` = UNHEX(SHA2('Tags regarding the content, form or liturgical use of the song.', 256))
SET p.`phrase`      = 'Tags regarding the content, form or liturgical use of the song.',
    p.`phrase_hash` = UNHEX(SHA2('Tags regarding the content, form or liturgical use of the song.', 256))
WHERE p.`project` = 'Schoenstatt'
  AND p.`phrase`  = 'Tags regarding the content of the content, form or liturgical use of the song.'
  AND taken.`phrase_hash` IS NULL;

-- 3. The REST API sentence on both shrine index pages
UPDATE `trans_phrases` p
LEFT JOIN (
  SELECT `project`, `text_domain`, `phrase_hash` FROM `trans_phrases`
) taken
  ON  taken.`project`     = p.`project`
  AND taken.`text_domain` = p.`text_domain`
  AND taken.`phrase_hash` = UNHEX(SHA2('This data may be accessed directly by any apps or websites which wish to receive up-to-date information via the %sREST API%s (application programming interface).', 256))
SET p.`phrase`      = 'This data may be accessed directly by any apps or websites which wish to receive up-to-date information via the %sREST API%s (application programming interface).',
    p.`phrase_hash` = UNHEX(SHA2('This data may be accessed directly by any apps or websites which wish to receive up-to-date information via the %sREST API%s (application programming interface).', 256))
WHERE p.`project` = 'Schoenstatt'
  AND p.`phrase`  = 'This data may be accessed directly by any apps or websites who which to receive up-to-date information via the %sREST API%s (application programming interface).'
  AND taken.`phrase_hash` IS NULL;

-- 4. Retire the stale deadline sentence and the three leaked record values.
--    `retired_on` is a statement about the translator's worklist; the site keeps
--    rendering whatever translations these already have.
UPDATE `trans_phrases`
SET `retired_on` = UTC_TIMESTAMP()
WHERE `project` = 'Schoenstatt'
  AND `retired_on` IS NULL
  AND (
        `phrase` = 'Until 2019-10-18, this page will be oriented towards data-maintainers and therefore show the columns of pertinent information.'
     OR `phrase` = 'Frau Dokto'
     OR `phrase` = 'xxi+133'
     OR `phrase` LIKE 'Hey! Welcome to Schoenstatt Link!%'
  );

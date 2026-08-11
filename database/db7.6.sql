-- db7.6 — the last six §12 rows, and the reason the previous guard could not see them
--
-- WHY THE GUARD WAS TOO STRONG
-- ----------------------------
-- db7.4 and db7.5 both required, before retiring a title, that **nothing had translated it
-- beyond English**. The reasoning was that discovery auto-fills the `en_US` row with a
-- verbatim copy of the key, so "English only" means "nobody has worked on this" and
-- anything with a real translation must be an interface string that merely collides with a
-- book title. The reporting agent's §12 uses the same test, and describes it as exact.
--
-- It is not. `TranslationsTable::writeMissingPhrasesToDb()` **copies translations onto a
-- newly discovered phrase** from any row of the same project holding the same text in
-- another text domain (the loop under "see if we have a matching phrase in another text
-- domain"). So a title that happens to equal an already-translated string arrives
-- pre-translated, in *four* languages, seconds after it was filed — and then looks exactly
-- like the interface strings the guard exists to protect.
--
-- That is what the standing check found on production on 2026-08-11, hours after db7.4 ran:
--
--     origin_route             text_domain   phrases  translated  newest
--     publications/publication Schoenstatt   3        3           2019-03-23   <- collision
--     publication              default       3        3           2026-08-11   <- THIS
--     publication              Application   3        3           2026-08-11   <- THIS
--     associations/association Schoenstatt   1        1           2019-06-16   <- collision
--     libraries/library        Books         1        1           2019-11-27   <- collision
--
-- Three phrases, filed twice each by the breadcrumb's two-domain lookup, at 03:19 UTC —
-- before that morning's deploy, i.e. while the defect was still live.
--
-- APPLIED 2026-08-11: statement 2 retired those six. They were `Santuario del Padre`,
-- `Schoenstatt` and `Heiligtum der Berufung`, each carrying all five languages — and the
-- rows they inherited from are the three statement 4 still shows, which are **shrine
-- names** in the `Schoenstatt` domain (`SchoenstattTable::TRANSLATOR_DOMAIN`, the domain
-- the association-name feature translates in), first seen in 2019 on books named after
-- those shrines. So the steady state of statement 4 is "the shrine names that double as
-- book titles" and **those rows must not be retired** — see docs/DEPLOY.md. The comment
-- below calling them collisions with interface strings was wrong about what they are,
-- though right that they must be left alone.
--
-- **So the durable signal is not "untranslated". It is the route and the domain.** Nothing
-- but the breadcrumb's own two-step lookup files a phrase under route `publication` into
-- `Application` or `default`; the page's real interface strings live in `Books` and
-- `Schoenstatt`. Statement 2 is scoped on that instead, and carries a date bound whose only
-- job is to keep any *future* arrival visible rather than quietly sweeping it — the fix is
-- deployed, so a row filed after it is a defect and must not be hidden by this file.
--
--
-- AND THE ONE db7.5 MISSED
-- ------------------------
-- db7.5 statement 1 reported **0 rows** on production. It required the crumb's text to equal
-- some `sch_publications.Title`, which is how db7.4 identified titles — but this label is a
-- literal in `App\Controller\OneFiftyPreguntasController`, not a value read from the table,
-- and production's row for that book evidently carries a subtitle or different punctuation.
-- The Title test is therefore the wrong test for it: we know the string is a work's title
-- because we can read the source. Statement 3 drops that condition and matches the text.
--
-- Reversible, as always: `retired_on` is a statement about the translator's worklist, the
-- site keeps rendering whatever is there, and a phrase the application still looks up
-- un-retires itself on the next miss.
--
-- Scoped to project 'Schoenstatt'. Three other projects share these tables.


-- 1. What statements 2 and 3 will retire. Printed first so the run leaves the evidence on
--    screen: these are titles, and a human should be able to see that they are.
SELECT p.`translation_phrase_id`,
       p.`text_domain`,
       p.`origin_route`,
       p.`added_on`,
       LEFT(p.`phrase`, 90) AS `phrase`,
       (
           SELECT GROUP_CONCAT(t.`locale` ORDER BY t.`locale`)
           FROM `trans_translations` t
           WHERE t.`translation_phrase_id` = p.`translation_phrase_id`
       ) AS `locales`
FROM `trans_phrases` p
WHERE p.`project` = 'Schoenstatt'
  AND p.`retired_on` IS NULL
  AND (
        (
          p.`origin_route` = 'publication'
          AND p.`text_domain` IN ('Application', 'default')
          AND p.`added_on` < '2026-08-11 09:00:00'
          AND EXISTS (SELECT 1 FROM `sch_publications` r WHERE r.`Title` = p.`phrase`)
        )
     OR (
          p.`origin_route` LIKE 'publications/one-fifty-preguntas%'
          AND p.`phrase` = '150 preguntas sobre Schoenstatt'
        )
  )
ORDER BY p.`origin_route`, p.`text_domain`, p.`translation_phrase_id`;

-- 2. The titles the breadcrumb filed under its own two lookup domains, whatever their
--    translations say. The date bound is 09:00 UTC on the day of the deploy: a row filed
--    after that means the fix is not working and belongs in the check's output, not here.
UPDATE `trans_phrases` p
SET p.`retired_on` = UTC_TIMESTAMP()
WHERE p.`project`      = 'Schoenstatt'
  AND p.`retired_on`   IS NULL
  AND p.`origin_route` = 'publication'
  AND p.`text_domain`  IN ('Application', 'default')
  AND p.`added_on`     < '2026-08-11 09:00:00'
  AND EXISTS (
      SELECT 1 FROM `sch_publications` r WHERE r.`Title` = p.`phrase`
  );

-- 3. The ported page's crumb, identified by the literal in the controller rather than by a
--    join against a title that does not match it.
UPDATE `trans_phrases` p
SET p.`retired_on` = UTC_TIMESTAMP()
WHERE p.`project`      = 'Schoenstatt'
  AND p.`retired_on`   IS NULL
  AND p.`origin_route` LIKE 'publications/one-fifty-preguntas%'
  AND p.`phrase`       = '150 preguntas sobre Schoenstatt';

-- 4. The standing check again, so the run ends by showing what is left. Everything it
--    returns should be old and translated: an interface string colliding with some book's
--    title. A row on route `publication` in `Application` or `default` is not that.
SELECT p.`origin_route`,
       p.`text_domain`,
       COUNT(*) AS phrases,
       SUM(EXISTS(
           SELECT 1 FROM `trans_translations` t
           WHERE t.`translation_phrase_id` = p.`translation_phrase_id`
             AND t.`locale` <> 'en_US'
       )) AS translated_by_somebody,
       MAX(p.`added_on`) AS newest
FROM `trans_phrases` p
WHERE p.`project` = 'Schoenstatt'
  AND p.`retired_on` IS NULL
  AND EXISTS (
      SELECT 1 FROM `sch_publications` r WHERE r.`Title` = p.`phrase`
  )
GROUP BY p.`origin_route`, p.`text_domain`
ORDER BY phrases DESC;

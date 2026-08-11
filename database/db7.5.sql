-- db7.5 — retire the one title db7.4 could not see: a ported page's own breadcrumb
--
-- WHY
-- ---
-- db7.4 retired the record names the *laminas* breadcrumb filed, scoped to each record's
-- own show route. `/literature/150-preguntas-sobre-schoenstatt` is neither: it is a
-- Symfony-served page, and its breadcrumb is not built from the navigation at all —
-- `App\Controller\OneFiftyPreguntasController` passes the trail to the Twig layout
-- itself, and the layout translates a crumb unless the controller says not to.
--
-- The label is a book's title. So the page filed it as a phrase in `Books` and in
-- `default` on 2026-08-10 — the day phrase discovery started working on ported routes,
-- which is the same repair that made §12 visible. One crumb, two rows, and it would have
-- gone on being the only surviving instance of the defect the rest of db7.4 cleaned up.
--
-- The code fix is `'translate' => false` on that crumb, in the same commit as this file,
-- and it must deploy first for the same reason db7.4's did: discovery clears `retired_on`,
-- so a render after the retirement and before the fix undoes it.
--
-- Verified in the capsule by deleting the rows, flushing APCu, rendering the page and
-- confirming nothing came back — the breadcrumb is byte-identical either way, which is
-- exactly why this needed a query to find rather than an eye.
--
-- Scoped to project 'Schoenstatt'. Three other projects share these tables.
--
-- Statement 2 is the standing check, not a change. It answers "is the defect back", and
-- it counts *phrases* rather than join pairs: 10,166 publications include duplicate
-- titles across editions — 9 of them are called `150 preguntas sobre Schoenstatt` — so
-- joining without DISTINCT multiplies one phrase into nine and reads like nine defects.
-- The rows it should keep returning are the benign collisions: interface strings that
-- happen to equal some book's title, which is why the third column matters.

-- 1. The crumb, in whatever text domain it landed in.
UPDATE `trans_phrases` p
SET p.`retired_on` = UTC_TIMESTAMP()
WHERE p.`project`      = 'Schoenstatt'
  AND p.`retired_on`   IS NULL
  AND p.`origin_route` LIKE 'publications/one-fifty-preguntas%'
  AND EXISTS (
      SELECT 1 FROM `sch_publications` r WHERE r.`Title` = p.`phrase`
  )
  AND NOT EXISTS (
      SELECT 1 FROM `trans_translations` t
      WHERE t.`translation_phrase_id` = p.`translation_phrase_id`
        AND t.`locale` <> 'en_US'
  );

-- 2. The standing check. Every row it returns should be explicable as a collision.
--    `EXISTS` rather than a join, so one phrase counts once however many editions carry
--    that title.
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

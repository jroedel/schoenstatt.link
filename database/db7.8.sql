-- db7.8 — retire the `default` duplicates the Twig layer's two-domain lookup filed
--
-- @phase: post
-- @kind: dml
-- @tables: trans_phrases
-- @verify: SELECT d.`translation_phrase_id` FROM `trans_phrases` d WHERE d.`project`='Schoenstatt' AND d.`text_domain`='default' AND d.`retired_on` IS NULL AND d.`origin_route` LIKE '%.locale' AND EXISTS (SELECT 1 FROM (SELECT * FROM `trans_phrases`) o WHERE o.`project`=d.`project` AND o.`phrase_hash`=d.`phrase_hash` AND o.`text_domain`<>'default' AND o.`retired_on` IS NULL)
--
-- Headers added 2026-08-16 when this file came under the migration ledger. `post`
-- because the code fix must land first: phrase discovery un-retires whatever the site
-- still looks up, so retiring ahead of the deploy is undone by the next page view.
-- The @verify query is the standing check narrowed to "rows statement 2 should have
-- retired and did not" — the file's own closing SELECT is broader and legitimately
-- returns rows, so it cannot be the pass/fail test.
--
-- NOT A CODE DEFECT ANY MORE, which is the same shape as db7.7: both halves of the leak are
-- fixed in the source and what is left is the rows they already wrote.
--
-- WHAT HAPPENED
-- -------------
-- `App\Twig\LaminasExtension::translate()` looked a string up in the page's text domain and
-- then fell back to `default`. Both were `Translator::translate()` calls, and a miss fires
-- `EVENT_MISSING_TRANSLATION` — which is the only path by which a row ever enters
-- `trans_phrases`. So the fallback was not a read: **every string the page's own module
-- domain could not translate was also filed in `default`**, where nothing had ever asked for
-- it. laminas files none of those, because a view helper carries exactly one text domain and
-- records a miss once, against the module the string belongs to.
--
-- Fixed 2026-08-12 by c5ef8f3 (PR #76): the page's domain is still asked with `translate()`,
-- because that call is what files an unknown phrase where it belongs, and `default` is now
-- *read* out of its compiled catalog, which fires no event.
--
-- A second door was still open after that one shut. `JTranslate\Module` sets a per-request
-- text domain on twelve view helpers from a listener attached to
-- `AbstractActionController::dispatch`, and a Symfony-served request never dispatches a
-- laminas controller, so the listener never runs. Ten of the twelve are unreachable from the
-- Twig side; `translate` was already bridged. The twelfth, `flashMessenger`, was not — so a
-- flash message rendered on a ported page was looked up in `default`, rendered as its English
-- source in every locale, and filed a fresh duplicate on the way past. Fixed in the same
-- commit as this file by `App\Laminas\ViewHelpers::useFlashMessengerTextDomain()`, called
-- from `LaminasExtension::flashMessages()`.
--
-- Both halves are measured rather than argued. Reverting only the flash-messenger line and
-- re-requesting `/es/literature/99999` files `default` / `publications.locale` /
-- "Publication not found."; with the line in place the same request across all five locales
-- files nothing, and neither does a 215-request sweep of every `tools/port-baseline.php` path.
--
-- WHAT THIS RETIRES
-- -----------------
-- 232 rows in the capsule, added between 2026-08-10 22:17 and 2026-08-13 03:22, across 24
-- routes — `association-edit.locale` 67, `shrines.locale` 30, `publication.locale` 26,
-- `association.locale` 22. **Production's set is different and larger by traffic**: the
-- capsule's rows come from local baseline sweeps, production ran the leak from the
-- 2026-08-11 kernel flip until PR #76 deployed. That is why every statement here is a query
-- over a shape and not a list of ids.
--
-- The selector is `text_domain = 'default'` and an `origin_route` ending in `.locale`. That
-- suffix is the locale-prefixed half of every `$ported()` route in `config/symfony/routes.php`
-- and it is the leak's fingerprint: no row carrying it predates 2026-08-10 22:17, because no
-- such route existed. It is paired with a requirement that the row **duplicate a live
-- non-default row of the same project**, which is what makes retiring it lossless — the
-- string still has a home, in the module domain the page's own text belongs to.
--
-- That pairing is not decoration. A ported route that declares no `_text_domain` has
-- `default` as its *own* domain, and discovering there is correct; such a row would be a
-- `.locale` row that is nobody's duplicate, and the guard leaves it alone. In the capsule
-- there are none — all 232 `.locale` rows in `default` are duplicates — but the guard is what
-- makes that a measurement rather than an assumption, and production is not the capsule.
--
-- WHAT THIS DELIBERATELY DOES NOT TOUCH
-- -------------------------------------
-- **`default` rows from laminas routes.** Twelve rows added on 2026-08-10 duplicate a
-- module-domain row and were filed by the laminas front controller during the same day's
-- captures: validator messages ('Value is required and can't be empty', 'The input was not
-- found in the haystack'), the error page ('A 404 error occurred'), a navigation label
-- ('Admin'), a JUser form message. `default` is where those belong after the §3 fix, and
-- this leak did not put them there. The `.locale` requirement excludes them by construction.
--
-- **The seven translations that exist nowhere else.** 977 translations hang off the 232 rows
-- and 240 of them carry a `modified_by`, which means nothing:
-- `TranslationsTable::writeMissingPhrasesToDb()` copies translations onto a newly discovered
-- phrase from any row of the same project holding the same text in another domain, author
-- and all — the finding that forced db7.6's guard to be rewritten. Only **7** (locale, value)
-- pairs, on three rows, exist nowhere else in the table:
--
--     13136  'Association'        de 'Verband'   pt 'Associação'   es 'Association'
--     13252  'Role'               es 'Tarea'
--     13914  'Association roles'  de/es/pt, near-variants of the Schoenstatt twin's wording
--
-- Retired with the rest, by explicit decision: each has a module-domain twin that already
-- carries the wording that module chose, the `es 'Association'` one is a verbatim copy of the
-- source string, and the two `Association*` cases differ from their twin by word choice
-- rather than by meaning. Nothing is deleted — `retired_on` keeps every translation exactly
-- where it is — so this is recoverable by clearing the column if a translator disagrees.
--
-- ORDERING
-- --------
-- **PR #76 and the flash-messenger fix must be live before this runs.** Discovery clears
-- `retired_on`, so a render between the retirement and the deploy un-retires whatever it
-- touches — the constraint db7.5 records. PR #76 was confirmed deployed on 2026-08-13; the
-- flash-messenger fix ships in the same PR as this file and deploys with it.
--
-- Reversible, as always: `retired_on` is a statement about the translator's worklist and the
-- site keeps rendering whatever is there.
--
-- **Do not expect it to un-retire itself.** Discovery clears `retired_on` on a miss, but a
-- retired row that carries a translation in the locale being rendered never misses — the
-- compiled catalog still holds it, per the next paragraph — so nothing fires. These rows
-- have 977 translations between them and most will simply stay retired, correctly and
-- silently. Statement 4 is a check to *run*, not an alarm that will find you.
--
-- "The site keeps rendering whatever is there" is worth checking rather than repeating,
-- because this file retires rows in `default` and PR #76 made `default` a *read* fallback for
-- every ported page. It holds: `TranslationsTable::getTranslatedText()`, the query the
-- catalog export is built from, filters on `project` and nothing else — a retired phrase and
-- its translations still compile into `.lang.php` exactly as before. So the retirement cannot
-- take a translation off a page even in the case it looks most dangerous, where the page's
-- own domain has no translation in some locale and the leaked `default` copy did.
-- Confirmed after the fact: the same 215-request five-locale sweep run against the retired
-- state renders identically and un-retires nothing.
--
-- The flip side, stated because it is the part that sounds like this file does more than it
-- does: **the leaked values stay in the compiled `default` catalog.** PR #76 made `default` a
-- read fallback for every ported page, so a page whose own domain has no translation in some
-- locale can still render a value that exists only because of the leak — one copied by
-- `writeMissingPhrasesToDb()` from whichever sibling domain it happened to find. `catalogHas()`
-- keeps that to phrases the page's domain has nothing for, so the effect is "more translated"
-- rather than "wrong", and it is the same superset behaviour docs/strangler.md already accepts.
-- This file cleans the translator's worklist. Undoing the *rendering* effect would mean
-- deleting these rows rather than retiring them, which is a separate decision on a set someone
-- has looked at — not a filter added to the exporter, which would change all 576 retired
-- phrases at once and is wrong for the reason TranslationsTable:1609 records.
--
-- Scoped to project 'Schoenstatt'. Three other projects share these tables.


-- 1. What statement 2 will retire, printed first so the run leaves the evidence on screen.
--    Grouped by route rather than listed row by row: on production this is several hundred
--    rows and the per-route shape is what tells you whether the selector caught the right
--    thing — the ported routes, and only those.
SELECT d.`origin_route`,
       COUNT(*)          AS `rows`,
       MIN(d.`added_on`) AS `oldest`,
       MAX(d.`added_on`) AS `newest`
FROM `trans_phrases` d
WHERE d.`project` = 'Schoenstatt'
  AND d.`text_domain` = 'default'
  AND d.`retired_on` IS NULL
  AND d.`origin_route` LIKE '%.locale'
  AND EXISTS (
        SELECT 1 FROM `trans_phrases` o
        WHERE o.`project` = d.`project`
          AND o.`phrase_hash` = d.`phrase_hash`
          AND o.`text_domain` <> 'default'
          AND o.`retired_on` IS NULL
      )
GROUP BY d.`origin_route`
ORDER BY `rows` DESC, d.`origin_route`;

-- 2. Retire them.
--
--    `phrase_hash` rather than `phrase` for the twin lookup: it is `binary(32)`, it is
--    indexed, and it is what the application itself matches on. Comparing the `text` column
--    across a few hundred rows would work and would also be the slow, collation-dependent
--    way to ask the same question.
UPDATE `trans_phrases` d
SET d.`retired_on` = UTC_TIMESTAMP()
WHERE d.`project` = 'Schoenstatt'
  AND d.`text_domain` = 'default'
  AND d.`retired_on` IS NULL
  AND d.`origin_route` LIKE '%.locale'
  AND EXISTS (
        SELECT 1 FROM (SELECT * FROM `trans_phrases`) o
        WHERE o.`project` = d.`project`
          AND o.`phrase_hash` = d.`phrase_hash`
          AND o.`text_domain` <> 'default'
          AND o.`retired_on` IS NULL
      );

-- 3. The two rows one library's name filed on 2026-08-10, before the record-name fix
--    reached that route.
--
--    A separate defect with its own history — db7.4, db7.5 and db7.6 are all about record
--    content arriving as phrases — and these are its residue, not its continuation. The code
--    fix landed on 2026-08-11 in 6e59aaa: `Application\Navigation\PageBuilder::markDataLabels()`
--    flags `library-pages` labels as data so the breadcrumb partial leaves them alone. These
--    two were filed on 2026-08-10 at 19:14, before it, on `libraries/library`, which db7.4
--    did not cover because it scoped its retirement to each record's *own* show route.
--
--    'Bellavista' only. 'Vaterhaus Investigation Library' is left live because it carries a
--    real Italian translation ('Biblioteca di ricerca Vaterhaus'), and db7.6 is the file that
--    explains why that is not, by itself, proof a human wrote it — so it stays until someone
--    looks. The name is a library's, in `default` and in `Application`, the navigation domain.
SELECT p.`translation_phrase_id`, p.`text_domain`, p.`origin_route`, p.`phrase`, p.`added_on`
FROM `trans_phrases` p
WHERE p.`project` = 'Schoenstatt'
  AND p.`retired_on` IS NULL
  AND p.`origin_route` = 'libraries/library'
  AND p.`phrase` = 'Bellavista'
ORDER BY p.`text_domain`;

UPDATE `trans_phrases` p
SET p.`retired_on` = UTC_TIMESTAMP()
WHERE p.`project` = 'Schoenstatt'
  AND p.`retired_on` IS NULL
  AND p.`origin_route` = 'libraries/library'
  AND p.`phrase` = 'Bellavista';

-- 4. What is left of the same shape, which should be nothing.
--
--    A non-zero count here means either that the run happened before the code fix was live —
--    a render un-retired what statement 2 had just taken — or that a ported route is filing
--    into `default` through a third door. Both are worth stopping for.
SELECT d.`origin_route`, d.`text_domain`, LEFT(d.`phrase`, 80) AS `phrase`, d.`added_on`
FROM `trans_phrases` d
WHERE d.`project` = 'Schoenstatt'
  AND d.`text_domain` = 'default'
  AND d.`retired_on` IS NULL
  AND d.`origin_route` LIKE '%.locale'
ORDER BY d.`added_on`;

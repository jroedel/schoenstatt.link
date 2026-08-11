-- db7.7 — retire the concatenated messages the §2/§3 fixes orphaned
--
-- NOT A CODE DEFECT. Every call site that used to build these strings by concatenation was
-- fixed on 2026-08-10 (change requests §2 and §3), and the fix is visible in the source:
--
--   * `SionController::doWorkWhenFormInvalidFor*()` and its six siblings now pass the fixed
--     literal `'Error in form submission, please review.'` — note the full stop, where the
--     old rows have a colon and a field list. There is a NOTE above it saying why.
--   * `LibraryImportsController` and `CheckoutsController` pass a
--     `JTranslate\I18n\TranslatableMessage`, so `translate()` receives
--     `'File not imported due to duplicate withinLibraryIds: %s.'` and
--     `'The following book id\'s are invalid: %s Please try again.'` — the template is the
--     phrase and the ids are interpolated afterwards.
--   * Validator messages are translated as templates by `AbstractValidator`, *before*
--     laminas substitutes `%hostname%`, and they now live in `default` rather than in each
--     module's domain.
--
-- What the fixes could not do is remove the rows already written. So the translator's
-- worklist still lists one row per combination of invalid field names, one per import batch,
-- and six strangers' mistyped email hostnames — none of them renderable into any language,
-- and none of them reachable any more: nothing translates a finished message, so the
-- interpolated form can never be a lookup key again. This file takes them off the list.
--
-- Reported from the translation GUI on 2026-08-11: rows like
-- `Error in form submission, please review: security, mainShowDisplay, viewRole,
-- checkoutPersonListKind` on the library routes. The reporter asked whether it was a bug in
-- the code. It was, until yesterday; what is left is its residue.
--
-- 28 rows in the capsule: 22 concatenations across six routes (newest 2024-08-29, i.e. all of
-- them pre-fix) and 6 interpolated hostnames. Not one carries a translation in any language
-- other than the auto-filled English copy — which is what you would expect of a string nobody
-- can translate.
--
-- **Every statement excludes phrases containing a literal `%`.** That is the template guard,
-- and it is what keeps `'…withinLibraryIds: %s.'` — the *live* message — out of a pattern
-- that would otherwise match it. Same device as db7.4 statement 5.
--
-- Reversible, as always: `retired_on` is a statement about the worklist, the site keeps
-- rendering whatever is there, and a phrase the application still looks up un-retires itself
-- on the next miss. If any of these turns out to be live, that is how it comes back.
--
-- Scoped to project 'Schoenstatt'. Three other projects share these tables.


-- 1. What statement 2 will retire, printed first so the run leaves the evidence on screen.
SELECT p.`text_domain`,
       p.`origin_route`,
       p.`added_on`,
       LEFT(p.`phrase`, 80) AS `phrase`
FROM `trans_phrases` p
WHERE p.`project` = 'Schoenstatt'
  AND p.`retired_on` IS NULL
  AND p.`phrase` NOT LIKE '%\%%'
  AND (
        p.`phrase` LIKE 'Error in form submission, please review: %'
     OR p.`phrase` LIKE 'File not imported due to duplicate withinLibraryIds: %'
     OR p.`phrase` LIKE 'The following book id%s are invalid: %'
     OR p.`phrase` LIKE 'Assignment Id: %'
     OR p.`phrase` LIKE '%is not a valid hostname for the email address'
  )
ORDER BY p.`origin_route`, p.`phrase`;

-- 2. Retire them.
--
--    `Assignment Id: %` has no call site left in any module — the code that built it is gone,
--    so those four rows are residue of a removed feature rather than of a fixed one.
--
--    The hostname pattern is anchored at the end rather than wrapped in wildcards on both
--    sides, so it cannot match a sentence that merely mentions the phrase.
UPDATE `trans_phrases` p
SET p.`retired_on` = UTC_TIMESTAMP()
WHERE p.`project` = 'Schoenstatt'
  AND p.`retired_on` IS NULL
  AND p.`phrase` NOT LIKE '%\%%'
  AND (
        p.`phrase` LIKE 'Error in form submission, please review: %'
     OR p.`phrase` LIKE 'File not imported due to duplicate withinLibraryIds: %'
     OR p.`phrase` LIKE 'The following book id%s are invalid: %'
     OR p.`phrase` LIKE 'Assignment Id: %'
     OR p.`phrase` LIKE '%is not a valid hostname for the email address'
  );

-- 3. What is left of the same shapes, which should be the templates and nothing else.
SELECT p.`text_domain`, p.`origin_route`, LEFT(p.`phrase`, 80) AS `phrase`
FROM `trans_phrases` p
WHERE p.`project` = 'Schoenstatt'
  AND p.`retired_on` IS NULL
  AND (
        p.`phrase` LIKE 'Error in form submission%'
     OR p.`phrase` LIKE 'File not imported due to duplicate%'
     OR p.`phrase` LIKE 'The following book id%'
     OR p.`phrase` LIKE 'Assignment Id%'
     OR p.`phrase` LIKE '%is not a valid hostname%'
  )
ORDER BY p.`phrase`;

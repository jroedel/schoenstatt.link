-- db7.9 — the three stored values that stood between four form fields and a domain check
--
-- Batch 12 restated the `InArray` that a select's option list implies on 13 more fields (see
-- SionModel\Form\ChoiceDomain for why naming a select in an input filter specification throws
-- its own validator away). Restating it is only safe where the data already fits the list:
-- otherwise the moderator opens a record that has been there for years, changes a comma, and
-- the form refuses it over a field they never touched.
--
-- Four of the fields did not fit, for three unrelated reasons. This file is the correction, and
-- test/Integration/ConstrainedChoiceFieldsFitTheirDataTest is what fails if any of it is missed
-- — it compares every constrained field's option list against every distinct value in its
-- column, so running the code without running this file is caught rather than discovered later
-- by a moderator.
--
-- Each statement is preceded by the SELECT that shows what it will touch. Run those first.


-- 1. mus_compositions.Country: 292 of 308 non-empty rows in lowercase
--
--    Not a typing accident 292 times. The composition template was the only one of the three
--    country selects in the application that passed `create: true` to selectize, so this form
--    let a moderator type a country where the person and association forms make them pick one.
--    `pt` and `cl` are what that produced, against a list keyed `PT` and `CL`.
--
--    Both halves are fixed in code as of batch 12: the template says `create: false` and
--    CompositionForm's specification carries `StringToUpper` before the InArray, so a value
--    arriving in the old shape is folded rather than refused. This is the rows they already
--    wrote.
--
--    `BINARY` is load-bearing in every comparison below. This schema's collation is
--    utf8mb4_general_ci, under which `'pt' <> UPPER('pt')` is FALSE — so the obvious form of
--    this statement matches nothing, updates nothing, reports success, and leaves the problem
--    exactly where it was. Written that way first, and caught only because a test compared the
--    column against the option list afterwards.
SELECT BINARY `Country` AS `country`, COUNT(*) AS `rows`
FROM `mus_compositions`
WHERE `Country` IS NOT NULL AND `Country` <> '' AND BINARY `Country` <> BINARY UPPER(`Country`)
GROUP BY BINARY `Country`
ORDER BY `rows` DESC;

UPDATE `mus_compositions`
SET `Country` = UPPER(`Country`)
WHERE `Country` IS NOT NULL AND `Country` <> '' AND BINARY `Country` <> BINARY UPPER(`Country`);


-- 2. lib_books.lang: two books holding three languages, semicolon-joined
--
--    `inLanguage` is a multiple select and SionTable stores a multiple's value pipe-joined, so
--    `es|de|en` is the shape this column expects and `es;de;en` is one value that happens to
--    contain two semicolons. The moderator's intent was expressible; the syntax was not, and
--    nothing rejected it because the specification entry had discarded the element's InArray.
--
--    Both rows are the same title in a trilingual edition ("Damit der Sion lebt / Para que Sion
--    viva / May Sion live"), so the three languages are right and only the separator is wrong.
--
--    The third stray value, `ceb`, needed no data change: Cebuano has no ISO 639-1 code, so the
--    184-entry two-letter list could not represent it at all. SionModel's LanguageSupport now
--    carries the 455 three-letter codes as well, which makes `ceb` a value the select offers.
SELECT `book_id`, `lang`, LEFT(`title`, 60) AS `title`
FROM `lib_books`
WHERE `lang` LIKE '%;%';

UPDATE `lib_books`
SET `lang` = REPLACE(`lang`, ';', '|')
WHERE `lang` LIKE '%;%';


-- 2b. lib_books.lang: two books carrying a fourth component that is not a language
--
--     `de|es|en|p` (book 52154) and `es|en|de|p` (book 52414). `p` is not a code in ISO 639-1,
--     639-2 or 639-3, so no widening of the list can accept it. It is dropped rather than
--     guessed at: it may well have been a half-typed `pt`, and both books are the kind of
--     multilingual edition that would plausibly include Portuguese — but the row does not say
--     so, and a migration is the wrong place to decide what someone meant in 2019. If
--     Portuguese belongs on either book, add it through the form; the field now offers 639
--     languages and will accept `pt`.
SELECT `book_id`, `lang`, LEFT(`title`, 60) AS `title`
FROM `lib_books`
WHERE `lang` REGEXP '(^|[|])p([|]|$)';

UPDATE `lib_books`
SET `lang` = TRIM(BOTH '|' FROM REPLACE(CONCAT('|', `lang`, '|'), '|p|', '|'))
WHERE `lang` REGEXP '(^|[|])p([|]|$)';


-- 2c. lib_books.lang: one book whose language carries a trailing space
--
--     Book 45817 holds `es ` rather than `es`. This one is only visible under a case- and
--     space-sensitive comparison: utf8mb4_general_ci treats `'es '` and `'es'` as equal, so
--     `SELECT DISTINCT lang` folds them together and every check written the obvious way
--     reports nothing wrong. The InArray does not — it compares in PHP, where a trailing
--     space is a different string — so this row would have been refused on the next save of a
--     book nobody meant to change.
SELECT `book_id`, CONCAT('[', `lang`, ']') AS `lang`, LEFT(`title`, 60) AS `title`
FROM `lib_books`
WHERE BINARY `lang` <> BINARY TRIM(`lang`) OR `lang` LIKE '% %';

UPDATE `lib_books`
SET `lang` = REPLACE(REPLACE(TRIM(`lang`), ' |', '|'), '| ', '|')
WHERE BINARY `lang` <> BINARY TRIM(`lang`) OR `lang` LIKE '% %';


-- 3. sch_assignments 206: an assignment to a person who was deleted 36 seconds later
--
--    The change log settles this one. On 2017-08-24 person 660 was created at 12:28:35,
--    assignment 206 was created against them at 12:29:00, and person 660 was deleted at
--    12:29:36 — a mistaken entry corrected within a minute, except that the delete did not
--    cascade. The row has pointed at a person who does not exist for nine years.
--
--    It is the only such row in the table, and it is why `personId` on the assignment forms had
--    no domain check: the fit test reads the column, and one dangling id is enough to make the
--    field look unconstrainable. The role (573, "Diocesan coordinator" under association 171)
--    has three other assignments, so nothing is left without a holder.
SELECT a.`AssignmentId`, a.`PersonId`, a.`RoleId`, r.`RoleTitle`, a.`CreatedOn`
FROM `sch_assignments` a
JOIN `sch_roles` r ON a.`RoleId` = r.`RoleId`
WHERE a.`PersonId` NOT IN (SELECT `PersonId` FROM `sch_persons`);

DELETE FROM `sch_assignments`
WHERE `PersonId` NOT IN (SELECT `PersonId` FROM `sch_persons`);

--    Recorded the way the application records a deletion, so the row's disappearance is not a
--    gap in the log. UpdatedBy is left NULL: no user did this, a migration did.
INSERT INTO `sch_changes` (`ChangedEntity`, `ChangedField`, `ChangedIDValue`, `NewValue`, `OldValue`, `UpdatedOn`, `UpdatedBy`, `IpAddress`)
VALUES ('assignment', 'entryDeleted', 206, NULL, NULL, UTC_TIMESTAMP(), NULL, NULL);


-- 4. Verification: all four columns now fit their form's option list.
--
--    Each of these should return zero rows. They are the same comparisons
--    ConstrainedChoiceFieldsFitTheirDataTest makes, minus the ones whose option list is built
--    in PHP rather than stored (the role and association lists), which only the test can check.
SELECT 'mus_compositions.Country not uppercase' AS `check`, COUNT(*) AS `rows`
FROM `mus_compositions`
WHERE `Country` IS NOT NULL AND `Country` <> '' AND BINARY `Country` <> BINARY UPPER(`Country`)
UNION ALL
SELECT 'lib_books.lang with a semicolon', COUNT(*)
FROM `lib_books`
WHERE `lang` LIKE '%;%'
UNION ALL
SELECT 'lib_books.lang with a bare p component', COUNT(*)
FROM `lib_books`
WHERE `lang` REGEXP '(^|[|])p([|]|$)'
UNION ALL
SELECT 'lib_books.lang with stray whitespace', COUNT(*)
FROM `lib_books`
WHERE BINARY `lang` <> BINARY TRIM(`lang`) OR `lang` LIKE '% %'
UNION ALL
SELECT 'sch_assignments pointing at a missing person', COUNT(*)
FROM `sch_assignments`
WHERE `PersonId` NOT IN (SELECT `PersonId` FROM `sch_persons`);

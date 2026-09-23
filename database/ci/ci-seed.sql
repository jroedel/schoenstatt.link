-- Invented rows for a database built from base-schema.sql, so the test suite has
-- something to assert against where it has no server to borrow from.
--
-- NOTHING HERE COMES FROM PRODUCTION. Every name, address and barcode below was made up
-- for this file, which is the whole point: it ships in a public repository, it is the only
-- way an outside contributor can run the suite, and a redacted extract would carry the
-- privacy question forever. The rows are deliberately few and deliberately boring.
--
-- Load order is base-schema.sql, then this. It is idempotent: every table it touches is
-- emptied first, so re-running it against a seeded database is a no-op rather than a
-- duplicate-key error.
--
-- ## Why these particular rows
--
-- Driven by what the integration suite asks for, not by what looks realistic. Several
-- tests name ids outright and there is no point inventing different ones:
--
--   · libraries 1, 3 and 7 -- CallNumberRequirementTest reads 1 as requiring call numbers
--     and 3 as not requiring them; LibraryImportPlanTest imports into 7 and needs it to
--     already hold books.
--   · associations must exist at all, or App\Schoenstatt\Association\AssociationFieldDomains
--     refuses to build: an empty `parentIds` domain would reject every submission, so it
--     fails loudly instead. That guard is correct, and it takes nine test classes with it.
--   · shrines need a real ISO `Country`, because App\Schoenstatt\ShrineIndex groups by
--     `countryRegion`, which is derived from the country rather than stored.
--   · an assignment must join a role to a person, or AssignmentsTableTest has no linked
--     person to render.
--
-- Tests that assert over the whole corpus will assert over these eight associations
-- instead of production's 498. That is a smaller check, not a wrong one -- and where a
-- test says so itself (ShrineIndexInvariantsTest's docblock argues for real rows), the
-- invariant it proves still holds here.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `sch_assignments`;
DELETE FROM `sch_roles`;
DELETE FROM `sch_persons`;
DELETE FROM `sch_associations`;
DELETE FROM `lib_checkouts`;
DELETE FROM `lib_books`;
DELETE FROM `lib_collections`;
DELETE FROM `lib_libraries`;
DELETE FROM `user`;

-- Two regional bodies in different countries, so the shrine index has more than one
-- region to group into, and six members under them.
INSERT INTO `sch_associations` (`AssociationId`, `AssociationName`, `Kind`, `Country`, `Parent`, `IsActive`) VALUES
  (1, 'Northern Regional Council',  'sch-regional-organization', 'DE', NULL, 1),
  (2, 'Southern Regional Council',  'sch-regional-organization', 'BR', NULL, 1),
  (3, 'Hillside Shrine',            'sch-shrine',                'DE', 1,    1),
  (4, 'Riverbend Shrine',           'sch-shrine',                'BR', 2,    1),
  (5, 'Meadow Wayside Shrine',      'sch-wayside-shrine',        'DE', 1,    1),
  (6, 'Lakeview Wayside Shrine',    'sch-wayside-shrine',        'BR', 2,    1),
  (7, 'Northern National Movement', 'sch-national-movement',     'DE', 1,    1),
  (8, 'Southern Institute',         'sch-institute',             'BR', 2,    1);

INSERT INTO `sch_persons` (`PersonId`, `LastName`, `FirstName`, `Country`, `IsAuthor`, `IsBorrower`) VALUES
  (1, 'Alder',  'Robin', 'DE', 1, 1),
  (2, 'Birch',  'Sam',   'BR', 0, 1),
  (3, 'Cedar',  'Alex',  'DE', 0, 0);

INSERT INTO `sch_roles` (`RoleId`, `RoleTitle`, `AssociationId`, `IsMainRole`, `IsActive`, `Sort`) VALUES
  (1, 'Regional Coordinator', 1, 1, 1, 1),
  (2, 'Shrine Custodian',     3, 1, 1, 1),
  (3, 'Regional Coordinator', 2, 1, 1, 1);

-- AssignmentsTableTest renders the person cell, so at least one assignment must reach a
-- person that exists.
INSERT INTO `sch_assignments` (`AssignmentId`, `RoleId`, `PersonId`) VALUES
  (1, 1, 1),
  (2, 2, 2),
  (3, 3, 3);

INSERT INTO `lib_libraries` (`LibraryId`, `LibraryName`, `RequireCallNumbers`, `IsActive`, `IsPublicallyListed`, `EnableCheckouts`) VALUES
  (1, 'Northern Reading Room',  b'1', 1, b'1', b'1'),
  (3, 'Southern Reading Room',  b'0', 1, b'1', b'0'),
  (7, 'Riverbend Lending Room', b'0', 1, b'0', b'1');

INSERT INTO `lib_collections` (`CollectionId`, `LibraryId`, `CollectionName`, `Abbreviation`) VALUES
  (1, 7, 'General Collection', 'GEN');

-- Barcodes are NUMERIC on purpose: `original_id` is a varchar, but BookBarcodeUniquenessTest
-- formats the ones it finds with %d and LibraryImportPlanTest imports them as sheet cells,
-- so a 'SEED-0001' shaped value reads as 0 in both. They are also the `UNIQUE
-- (library_id, original_id)` half that the form-level rule exists to keep the page away
-- from. Five active books, because that test retires some and asserts on more than three
-- surviving; `is_active` is set explicitly rather than left to the column default.
INSERT INTO `lib_books` (`book_id`, `library_id`, `collection_id`, `author`, `title`, `call_number`, `original_id`, `lang`, `is_active`) VALUES
  (1, 7, 1, 'Alder, Robin', 'A Book About Nothing',       'AB 100', '50001', 'en', 1),
  (2, 7, 1, 'Birch, Sam',   'Another Book About Nothing', 'AB 101', '50002', 'en', 1),
  (3, 7, 1, 'Cedar, Alex',  'A Third Book',               'AB 102', '50003', 'de', 1),
  (4, 7, 1, 'Alder, Robin', 'A Fourth Book',              'AB 103', '50004', 'en', 1),
  (5, 7, 1, 'Birch, Sam',   'A Fifth Book',               'AB 104', '50005', 'pt', 1),
  (6, 1, NULL, 'Cedar, Alex', 'A Book In Another Library','CD 200', '50006', 'en', 1);

-- UserFormUniquenessTest reads an existing username out of the table and submits it back,
-- so `username` and `display_name` must both be set rather than left null.
INSERT INTO `user` (`user_id`, `username`, `email`, `display_name`, `state`, `email_verified`, `create_datetime`, `update_datetime`) VALUES
  (1, 'seed-admin', 'seed-admin@example.com', 'Seed Admin', 1, 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
  (2, 'seed-reader', 'seed-reader@example.com', 'Seed Reader', 1, 0, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

SET FOREIGN_KEY_CHECKS = 1;

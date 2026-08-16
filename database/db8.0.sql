-- db8.0 — borrower self-service: a renewal limit, and scoped links that carry no account
--
-- @phase: pre
-- @kind: ddl
-- @idempotent: yes
-- @tables: none
-- @verify: SELECT 'MaximumBookRenewals missing' FROM information_schema.TABLES t WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_NAME='lib_libraries' AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA=DATABASE() AND c.TABLE_NAME='lib_libraries' AND c.COLUMN_NAME='MaximumBookRenewals') UNION ALL SELECT 'lib_borrower_tokens missing' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='lib_borrower_tokens')
--
-- `pre` because the code that ships with it selects both of these. Deploy first and
-- every overdue notice, and every borrower page, hits an unknown column or table.
--
-- `@tables: none` is accurate rather than lazy: one ADD COLUMN and one CREATE TABLE,
-- neither of which can lose a row, so there is nothing a snapshot would let you put
-- back. The renewal-limit column is added NULL, so no existing library changes
-- behaviour until someone sets one.

-- 1. The renewal limit had nowhere to live.
--
--    LibraryOptions::$maximumBookRenewals has existed since 2020 and was never once
--    assigned — because no column backed it. Renewal itself was equally notional:
--    LibraryTable::renewBook() computed a due date, returned it, and persisted
--    nothing. So "how many times may a book be renewed?" was a question the schema
--    could not answer and the code never asked.
--
--    NULL means "use the application default" (LibraryTable::DEFAULT_MAXIMUM_RENEWALS,
--    3). A library that wants a different rule sets its own number; 0 disables
--    renewal for that library entirely.
ALTER TABLE `lib_libraries`
    ADD COLUMN IF NOT EXISTS `MaximumBookRenewals` INT UNSIGNED NULL DEFAULT NULL
    AFTER `DefaultCheckoutTimePeriodInDays`;

-- 2. Scoped borrower links.
--
--    A borrower who gets an overdue notice needs to see their books and renew them.
--    Most borrowers have no user account and should not be given one: this database
--    has no user-to-person link at all, and every account inherits `lib_user`, which
--    is `is_default = 1` — so an account would grant far more than "my own books".
--
--    Hence a token that authorises exactly one person's checkouts at one library, and
--    nothing else. Stored as a sha256 hex digest, never in the clear: the row is
--    enough to check a presented link and useless for forging one, which is the same
--    property JUser\Service\LoginTokenService relies on.
--
--    Not single-use, unlike a magic-link sign-in — the borrower is expected to open
--    the mail, look, and renew, possibly over several days. It expires instead, and
--    `RevokedOn` exists so a librarian can cut one off without waiting.
CREATE TABLE IF NOT EXISTS `lib_borrower_tokens` (
    `TokenId`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `TokenHash`  CHAR(64)     NOT NULL COMMENT 'sha256 hex of the plaintext token; the plaintext is never stored',
    `PersonId`   INT UNSIGNED NOT NULL,
    `LibraryId`  INT UNSIGNED NOT NULL,
    `CreatedOn`  DATETIME     NOT NULL,
    `ExpiresOn`  DATETIME     NOT NULL,
    `LastUsedOn` DATETIME     NULL DEFAULT NULL,
    `RevokedOn`  DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (`TokenId`),
    UNIQUE KEY `TokenHash` (`TokenHash`),
    KEY `PersonLibrary` (`PersonId`, `LibraryId`),
    KEY `ExpiresOn` (`ExpiresOn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

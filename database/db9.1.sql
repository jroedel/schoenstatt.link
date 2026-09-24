-- db9.1 — drop every stored client IP address and User-Agent
--
-- @phase: post
-- @kind: ddl
-- @idempotent: yes
-- @destructive: yes
-- @tables: sch_visits, sch_changes, lib_checkouts, mailings
-- @verify: SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME) AS column_still_present FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='sch_visits' AND COLUMN_NAME IN ('IpAddress','UserAgent')) OR (TABLE_NAME='sch_changes' AND COLUMN_NAME='IpAddress') OR (TABLE_NAME='lib_checkouts' AND COLUMN_NAME IN ('CheckedOutIp','CheckedOutUserAgent','CheckedInIp','CheckedInUserAgent')) OR (TABLE_NAME='mailings' AND COLUMN_NAME='OpenedFromIpAddress'))
--
-- The decision (2026-09-24): this application stores no client IP addresses at all. Not
-- hashed, not truncated, not "for abuse" — none. The code stopped writing all eight
-- columns in the release this ships with; this drops them and the data already in them.
--
-- ## Why none of the four had a purpose
--
-- Nothing in the application reads an IP address for anything. The one piece of rate
-- limiting is keyed on the **user** — JUser\Service\LoginTokenService asks whether *this
-- user* was already sent a still-valid token in the last minute — and there is no
-- geolocation, no fraud check and no abuse heuristic anywhere. So:
--
--   * `sch_visits` feeds a view counter. `getVisitCounts()` returns total and pastMonth;
--     nothing selects, indexes or displays the other two columns. ~7.1M rows.
--   * `sch_changes` already carries `UpdatedBy`, a named account. The IP added nothing
--     that column does not say, and it was written raw and shown to every signed-in user
--     as a tooltip on the editor's username (#308, fixed in the previous release).
--   * `lib_checkouts` — docs/libraries.md already records that an IP is not an
--     authentication signal here and cannot become one, because checkout is deliberately
--     permissive. A borrower is not an account holder, so this tied a named person to a
--     raw address with no notice at all.
--   * `mailings.OpenedFromIpAddress` was never written. It is a column of an open-tracking
--     feature that recorded zero opens across 117 rows.
--
-- ## What is deliberately kept
--
-- `SionModel\Error\Redactor::ip()` still records a **truncated** address in the exception
-- store — /24 for IPv4, /48 for IPv6 — which is what separates "one visitor keeps hitting
-- this" from "everyone hits this" without storing an identifier. That is the one exception,
-- and it is disclosed in the privacy policy rather than left implicit.
--
-- The web server's own access log is a separate matter, outside this application and this
-- migration. Its retention is stated in the policy; do not read this migration as making
-- it go away.
--
-- ## Why `@phase: post`
--
-- `pre` runs before the symlink swap, while the PREVIOUS release is still serving. That
-- release's `getCheckoutSelectPrototype()` names CheckedOutIp and CheckedInIp explicitly,
-- so dropping them first would break every circulation page for the length of the deploy.
-- Post-swap, the code that reads them is already gone.
--
-- `@destructive: yes` for the same reason in reverse: once this has run, a rollback to a
-- release that still SELECTs those columns is a broken site. tools/deploy.sh refuses such
-- a rollback, which is the guard added after db8.1 took the site down in exactly that way
-- (docs/incident-2026-08-17-stale-opcache.md).
--
-- ## THIS DOES NOT PHYSICALLY ERASE THE BYTES, and that matters for the claim
--
-- MariaDB 10.11 drops a column with ALGORITHM=INSTANT when it can, and it can here — no
-- algorithm was requested, so it picked the fastest, which is why four tables including a
-- 288 MB one finished in 161 ms. An instant drop records that the column is gone; it does
-- NOT rewrite the rows already on disk. The values stay in the existing records, and in
-- any backup taken before today, until the table is rebuilt.
--
-- So after this migration the application cannot read an IP address and no query can
-- return one — which is the security and the minimisation win — but "the data is erased"
-- is not yet true. Making it true is `ALTER TABLE <t> FORCE` (or OPTIMIZE TABLE) on each
-- of the four, and that is deliberately NOT in this file: on production `sch_visits` is
-- ~1.3 GiB, a rebuild copies the table, and this migration runs in the post-deploy phase
-- of an unattended push-to-deploy. A multi-minute table copy with a lock, on shared
-- hosting, started by nobody, is not something to discover at 3am.
--
-- It is a server maintenance task, tracked with the rollover tables below, and the
-- retention work (#253) is where backup expiry gets a number.
--
-- ## Not here
--
-- `sch_visits_rollover_2023-11-02` and `sch_visits_rollover_2025-07-17` carry the same two
-- columns and should go entirely, but no code has ever referenced them — they are a server
-- maintenance task, not a migration, and tools/migrate.sh globbing this directory is not a
-- reason to smuggle them in.

ALTER TABLE `sch_visits`
    DROP COLUMN IF EXISTS `IpAddress`,
    DROP COLUMN IF EXISTS `UserAgent`;

ALTER TABLE `sch_changes`
    DROP COLUMN IF EXISTS `IpAddress`;

ALTER TABLE `lib_checkouts`
    DROP COLUMN IF EXISTS `CheckedOutIp`,
    DROP COLUMN IF EXISTS `CheckedOutUserAgent`,
    DROP COLUMN IF EXISTS `CheckedInIp`,
    DROP COLUMN IF EXISTS `CheckedInUserAgent`;

ALTER TABLE `mailings`
    DROP COLUMN IF EXISTS `OpenedFromIpAddress`;

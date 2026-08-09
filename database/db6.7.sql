-- db6.7 — user_api_token, the registry that makes an issued JWT revocable
--
-- WHY
-- ---
-- Until now a JWT was mint-and-forget. LoginV1ApiController generated a `jti`,
-- put it in the payload and threw it away; nothing recorded that a token had
-- been issued. Three consequences that only became intolerable once we decided
-- to let an admin mint tokens with one click:
--
--   1. Nobody can say how many live tokens an account has, or when they were
--      issued, or by whom. The number is unbounded and unobservable.
--   2. The only kill switch is deleting the user_role_linker row — all-or-
--      nothing per *account*. One compromised agent credential means revoking
--      every credential that account holds.
--   3. A token outlives the admin session that created it by six months, and
--      nothing can shorten that.
--
-- Cheap issuance without an inventory is how you end up with credentials you
-- cannot count. So the registry lands with, not after, the button.
--
-- WHAT IS STORED
-- --------------
-- The `jti` claim only — never the JWT and never anything that could
-- reconstruct one. The row is a *statement about* a token, not a copy of it:
-- leaking this table tells an attacker that tokens exist and when they expire,
-- which is exactly what an auditor needs and exactly what a forger cannot use.
-- Signing still depends solely on ApiRequest.jwtAuth.cypherKey.
--
-- FAIL CLOSED
-- -----------
-- App\Api\BotIdentity refuses a v3 request whose token carries no `jti`, or
-- whose `jti` has no row here, or whose row is revoked. Not "refuses if
-- revoked" — refuses unless positively vouched for. An unregistered token is
-- indistinguishable from a forged one at the point of use, and treating the two
-- alike is the only way the registry means anything.
--
-- This is safe to switch on precisely because it lands before any bot account
-- exists in production. The v1 API is untouched: it does not consult this table,
-- so the tokens the mobile apps are already carrying keep working.
--
-- REVOCATION IS A TIMESTAMP, NOT A DELETE
-- ---------------------------------------
-- A revoked row stays, so "this token was revoked on the 9th by user 5" remains
-- answerable afterwards. Deleting it would make a revoked token and a token that
-- never existed look identical in the audit trail — the same mistake as not
-- having the table.
--
-- Expired rows are pruned when the same account is issued a new token; see
-- JUser\Model\ApiTokenTable::pruneExpired(). Revoked rows are never pruned.

CREATE TABLE IF NOT EXISTS `user_api_token` (
  `token_id`   INT(11)      NOT NULL AUTO_INCREMENT,
  -- The JWT `jti` claim. char(43), not varchar: Laminas\Math\Rand::getString()
  -- is asked for a fixed 43-char base62 string (~256 bits), and a fixed-width
  -- column makes a wrong-length value a write error rather than a silent
  -- shortening. UNIQUE because a repeated jti would make one revocation kill two
  -- tokens.
  `jti`        CHAR(43)     NOT NULL,
  `user_id`    INT(11)      NOT NULL,
  -- Free text so an admin can tell two of an agent's tokens apart ("nightly
  -- enrichment", "laptop test"). Never sent to the client, never signed.
  `label`      VARCHAR(100)     NULL DEFAULT NULL,
  `issued_on`  DATETIME     NOT NULL,
  -- The admin who pressed the button; NULL when the account signed itself in
  -- through the email flow, which is a real and different provenance.
  `issued_by`  INT(11)          NULL DEFAULT NULL,
  -- Denormalized from the token's own `exp`. Kept so the registry can be read,
  -- pruned and displayed without decoding anything; `exp` remains authoritative
  -- for verification, and php-jwt enforces it before we ever reach this table.
  `expires_on` DATETIME     NOT NULL,
  `revoked_on` DATETIME         NULL DEFAULT NULL,
  `revoked_by` INT(11)          NULL DEFAULT NULL,
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `uniq_user_api_token_jti` (`jti`),
  -- The admin screen's query: this account's tokens, newest first.
  KEY `idx_user_api_token_user` (`user_id`, `issued_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- No foreign key on user_id, deliberately, and it is worth saying why rather
-- than leaving it to look like an oversight: `user` rows are deleted through
-- JUser's own delete screen, and ON DELETE CASCADE would erase the evidence that
-- a deleted account once held live API tokens. An orphaned row is harmless —
-- BotIdentity resolves the account independently and refuses a token whose user
-- is gone — while a vanished row is an audit gap.

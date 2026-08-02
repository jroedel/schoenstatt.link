-- db6.4 (2026-08-02): passwordless authentication (magic links)
-- verification_token now stores a sha256 hex digest (64 chars) of the token,
-- never the token itself. Old 32-char plaintext tokens become unredeemable,
-- which is fine: they expire within a day and the flow reissues on demand.
ALTER TABLE `user` MODIFY `verification_token` CHAR(64) DEFAULT NULL;

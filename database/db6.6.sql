-- db6.6 — the sch_api_bot role, for automated agents writing through /api/v3
--
-- WHY
-- ---
-- Automated agents are about to augment and update the shrine database through the
-- v3 API. They authenticate with the site's existing JWT (JUser's
-- /api/v1/users/login-with-verification-token), which means an agent is a real
-- `user` row — that is what makes its edits attributable in sch_changes, and it is
-- why no second credential store is being built.
--
-- But an account is not a permission. The question this migration answers is: what
-- may an agent's token reach?
--
-- The tempting answer is "reuse sch_user", and it is wrong in a way worth writing
-- down. Registration grants sch_user by itself, and `route/association-edit` names
-- sch_user — so on this site *every registered account can already edit every
-- association through the web form*. An agent holding sch_user would therefore hold
-- the moderator surface too, and a leaked bot token would be an ordinary account
-- with a browser's reach. The blast radius of an automation credential should be the
-- automation, not the site.
--
-- So sch_api_bot is a role named by exactly one thing — App\Api\BotIdentity, which
-- the v3 endpoints consult — and by no ACL guard anywhere. A token whose account
-- holds it can read and PATCH associations over the API and can reach nothing else;
-- a token whose account does not hold it is refused by the API with a 401 no matter
-- what else the account can do.
--
-- PARENT
-- ------
-- None. Every other sch_* role hangs off `user` or another sch_* role and inherits
-- its permissions; this one deliberately inherits nothing, so the set of things a bot
-- may do is the empty set plus whatever names sch_api_bot explicitly. `is_default` is
-- 0 for the same reason — registration must never hand it out.
--
-- AFTER THIS MIGRATION
-- --------------------
-- Creating an agent is two steps and both are deliberate:
--   1. register the bot's email address like any other account;
--   2. grant it this role, through the users screen or by an INSERT into
--      user_role_linker.
-- Revoking one is deleting that link. Nothing here creates an account, because a
-- credential that exists before anyone asked for it is a credential nobody is
-- watching.

INSERT INTO `user_role` (`role_id`, `is_default`, `parent_id`, `create_datetime`)
SELECT 'sch_api_bot', 0, NULL, NOW()
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id` FROM `user_role`) AS existing
                  WHERE existing.`role_id` = 'sch_api_bot');

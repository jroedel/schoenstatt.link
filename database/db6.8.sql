-- db6.8 — the sch_api_translator role, and the end of "one role opens all of /api/v3"
--
-- WHY NOW
-- -------
-- db6.6 introduced sch_api_bot for agents maintaining the shrine database, and made
-- an argument that was true at the time: "a leaked bot token reaches the v3 API and
-- nothing else". The word doing the work in that sentence is *the API*, and it was
-- exact only while the API held one resource.
--
-- v3 is about to hold a second, unrelated one: the translation phrases, which an
-- agent will read for context and write back. Reusing sch_api_bot for it would mean a
-- translation agent could PATCH shrines and a shrine agent could rewrite every string
-- the site renders in four languages — neither of which anyone would grant on
-- purpose, and neither of which any screen would show as having been granted. The
-- security claim would have to be restated as "reaches every resource v3 ever grows",
-- which is not a boundary, it is a promise to keep remembering.
--
-- So the role stops being a property of the API and becomes a property of the
-- resource. App\Api\BotIdentity now takes the role it must find rather than naming one
-- constant, and each route declares its own. sch_api_bot keeps its exact meaning —
-- associations — so every token already issued keeps working and means no more than
-- it did when it was issued. That is the reason to do this before the second resource
-- ships rather than after: afterwards it is a re-issue for every agent in existence.
--
-- WHAT THIS ROLE OPENS
-- --------------------
-- GET and PATCH on /api/v3/phrases, and nothing else. Like sch_api_bot it is named by
-- no ACL guard, no route and no menu, so it grants nothing in a browser: an account
-- holding it and no other role cannot sign in to anything worth reaching. It is
-- deliberately *not* `translator`, the role config/autoload/acl.global.php names on
-- route/jtranslate — that role opens the admin translation GUI, and an automation
-- credential that also opens an admin screen is the thing db6.6 was written to avoid.
--
-- PARENT
-- ------
-- None, and is_default is 0, for the reasons db6.6 gives at length: a bot's
-- permissions should be the empty set plus what explicitly names it, and registration
-- must never hand this out.
--
-- AFTER THIS MIGRATION
-- --------------------
-- Creating a translation agent is the same two steps as creating a shrine agent —
-- register the account, grant it this role on the users screen — and the two grants
-- are independent. An account may hold both if someone decides one agent should do
-- both jobs; the point is that it has to be decided.

INSERT INTO `user_role` (`role_id`, `is_default`, `parent_id`, `create_datetime`)
SELECT 'sch_api_translator', 0, NULL, NOW()
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `role_id` FROM `user_role`) AS existing
                  WHERE existing.`role_id` = 'sch_api_translator');

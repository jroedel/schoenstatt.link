# Documentation

| document | what it covers |
| --- | --- |
| [DEPLOY.md](DEPLOY.md) | how a deploy actually runs, the two-identity SFTP arrangement, server facts, post-deploy smoke checks, rollback |
| [exception-reporting.md](exception-reporting.md) | how production failures reach your inbox, the on-disk store, the capture/privacy profile, and the `fetch-` / `clear-exceptions.sh` scripts |
| [caching.md](caching.md) | the APCu layer: why an oversized cache item wipes the whole segment, the `max_cached_item_size` budget, `/sm/cache-status`, and what to watch when adding a cached call |
| [api-v3.md](api-v3.md) | the read/write API for automated agents: shrines and translation phrases, how a bot gets a token, why each resource has its **own** role, the field contract, and why validation is the web form's |
| [api-change-requests-response.md](api-change-requests-response.md) | the answer to the ten requests the translation agent filed on 2026-08-10: what changed, the two places its diagnosis was wrong (association public notes are translated *by design*; only one of the two corpus routes was live), and the API additions it should start using — the history endpoint, `_note`, and why "fill gaps, never overwrite" can relax |
| [strangler.md](strangler.md) | the Symfony/laminas-mvc split: which front controller is live and how to switch, what the bridge preserves and why, and how to add a Symfony route |
| [authorization-migration.md](authorization-migration.md) | access control across the laminas/Symfony split: how a ported route declares which ACL resource governs it, the two denial branches, the traps (getIdentity() is not the identity, isAllowed() starts a session, a null role means public), and how the bridge makes the eventual Security migration incremental |
| [view-scripts.md](view-scripts.md) | migrating `.phtml` view scripts to Twig: what a Symfony-served request loses, which view helpers survive without an MvcEvent, the locale trap, and a running log of what each port taught us |
| [translation-migration.md](translation-migration.md) | cutting JTranslate's scope and moving the rest onto `symfony/translation`: why the precompiled-PHP-array format is unbeatable and stays, why the per-request database tax was 63% of a laminas page, why the permission trouble is really "build artifacts in the source tree", and the adapter seam that keeps 428 `.phtml` call sites untouched. The library's own README and UPGRADE.md describe what it is now |
| [database-charset.md](database-charset.md) | the InnoDB + `utf8mb4_unicode_520_ci` standard: why that collation and not MySQL's or MariaDB 11's default, why the connection charset is half the job, which 16 tables are server maintenance rather than migrations, and the traps (error 1833 vs `FOREIGN_KEY_CHECKS`, silent `TEXT` promotion, how to actually test for double-encoded latin1) |
| [php-85.md](php-85.md) | PHP 8.5 and the dependency ceiling behind it: why no laminas-mvc version admits it, why the platform pin stays at production's version, and why the same obstacle blocks FrameworkBundle |
| [BACKLOG.md](BACKLOG.md) | the forward-only modernization roadmap — strategic direction (Symfony via strangler), queued work, open bugs and decisions |
| [acl-rules.md](acl-rules.md) | generated: every role, route guard and rule, with inheritance expanded and Symfony-served routes flagged. Regenerate with `tools/acl-table.php` and diff after any authorization change |
| [acl-baseline.json](acl-baseline.json) | generated: the same facts sorted for `diff`. The parity oracle an authorization change is checked against |
| [history.md](history.md) | the modernization journal — closed work, corrections, and reusable techniques (golden-master diffing, cycle detection, deploy-day lessons) |

Operational instructions for working *on* this codebase — the local Docker
capsule, how to run the test suites, coding standard, architecture — live in
[`../CLAUDE.md`](../CLAUDE.md), which is the single source of truth for those and
is kept current deliberately.

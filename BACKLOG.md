# Modernization backlog

Working items for the state-of-the-art overhaul (see CLAUDE.md, "Modernization
mandate"). Ordered roughly by intended sequence, not priority.

## Finish SchoenstattTable ACL providers (dormant 2020 WIP)

`Schoenstatt\Model\SchoenstattTable` implements the BjyAuthorize resource and
rule provider interfaces (`getResources()`/`getRules()`), but the feature was
never finished: a duplicate `'bjyauthorize'` config key silently discarded the
provider registration since 2020, and `getRules()` grants access to roles
(`sch_international_leader`, `sch_institute_member`) that don't exist in the
production database — activating it fatals at boot (see commit 988c24d and its
follow-up disabling the registration in
`module/Schoenstatt/config/module.config.php`).

To finish, someone must decide the intended semantics: which real roles should
be allowed on person/association resources, create any missing roles in
`user_role`, re-enable the registration, and cover the behavior with tests.
Also consider the boot-time cost: both methods query all persons/associations
on every ACL build.

## Dead/orphaned ACL guard entries (config rot found 2026-08-01)

Route-config analysis found BjyAuthorize guard entries referencing route names
that don't exist (no-ops today, confusing tomorrow):

- `new-home` (module/Application config)
- `home` (module/Books config)
- `change-password` in `config/autoload/juser.global.php` — the real zfcuser
  child route is `zfcuser/changepassword`, so the change-password page may be
  unintentionally blocked (BjyAuthorize is default-deny). Verify intent before
  fixing.
- `sign-in-no-cookies` route (module/Application) exists but is whitelisted in
  no guard — blocked for everyone under default-deny. Dead feature or bug?
- Bare `checkouts` route (module/Books) is likewise not whitelisted (only its
  `checkouts/library` child is).

## Broken pages found during smoke-suite characterization (2026-08-01)

- `/en/dictionary` → 404: the route in `module/Books/config/module.config.php`
  (~line 1280) declares `DictionaryController` with no `'action'` default, so
  dispatch falls to a nonexistent `indexAction`. One-line config fix; verify
  against production intent first (production may 404 identically).
- `/en/blog` renders PHP notices into the page body ("Trying to access array
  offset on value of type null", vendor/erusev/parsedown-extra line 241) —
  user-visible warning text inside post content.

## Phase 2: safety net

- [x] HTTP smoke/characterization suite (38 tests green against the time
  capsule; excluded: `/en/associations/do-work` (side effects), `/en/dictionary`
  (broken, see above)).
- [x] PHPUnit 9.6 + PHPStan 1.12 phars pinned via `tools/fetch.sh`.
- [x] PHPStan level 0 green with `phpstan-baseline.neon` (56 legacy errors).
- [x] CI v1: PHP 7.4 syntax lint (GitHub Actions).
- [ ] Expand CI: prove `composer install` works from the 2020 lock in CI (eight
  VCS forks), then add the PHPStan job.
- [ ] Pull a fresh 2026 production dump (current one is 2021-06-24) and
  re-verify the smoke suite against it.

## Phase 3: migration ladder

Each rung verified by the Phase 2 suite:

1. Latest ZF3-compatible dependency versions.
2. ZF → Laminas via `laminas/laminas-migration`.
3. Replace abandoned packages (SwiftMailer → symfony/mailer, PHPExcel →
   PhpSpreadsheet, ZfcUser/BjyAuthorize → LM-Commons successors, …) and
   re-evaluate each pinned personal VCS fork in composer.json.
4. PHP 8.0 → 8.1 → … → 8.4, one version at a time.

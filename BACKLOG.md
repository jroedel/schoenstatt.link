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

## Phase 2: safety net (next up)

- HTTP smoke/characterization tests over the working routes (homepage, login,
  guarded redirects, public sections) running against the Docker time capsule.
- PHPUnit harness; PHPStan with generated baseline; wire both into CI.
- Pull a fresh 2026 production dump (current one is 2021-06-24) before
  finalizing characterization tests.

## Phase 3: migration ladder

Each rung verified by the Phase 2 suite:

1. Latest ZF3-compatible dependency versions.
2. ZF → Laminas via `laminas/laminas-migration`.
3. Replace abandoned packages (SwiftMailer → symfony/mailer, PHPExcel →
   PhpSpreadsheet, ZfcUser/BjyAuthorize → LM-Commons successors, …) and
   re-evaluate each pinned personal VCS fork in composer.json.
4. PHP 8.0 → 8.1 → … → 8.4, one version at a time.

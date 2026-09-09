# Documentation

Every document states what is true now and the rules. Dated narrative lives in git
history. Working conventions (capsule, test suites, coding standard, git) are in
[`../CLAUDE.md`](../CLAUDE.md).

| document | what it covers |
| --- | --- |
| [laminas-exit.md](laminas-exit.md) | **the plan**: removing every `laminas/*` package, in order, with the dependency picture measured; step 0 (laminas-mvc) in detail; the rules every step follows; how a Symfony route, its authorization and its Twig template are built |
| [BACKLOG.md](BACKLOG.md) | open work, open bugs, open product decisions |
| [DEPLOY.md](DEPLOY.md) | how a deploy runs: atomic releases, the OPcache reset and revision gate, the migration ledger, rollback, smoke checks, the per-account `php.ini` baseline |
| [caching.md](caching.md) | APCu and OPcache: the persistent cache and its flush, the dependency map and its invariants, `/en/sm/cache-status`, the oversized-item hazard |
| [translation.md](translation.md) | how the site gets translated: phrase discovery, text domains, the Twig checklist, retirement vs retraction, catalog files |
| [exception-reporting.md](exception-reporting.md) | how production failures reach the inbox: store, privacy profile, rate limit, scripts |
| [api-v3.md](api-v3.md) | the API for automated agents: tokens and roles, endpoints, field and validation contracts, the v1/v2 410 |
| [libraries.md](libraries.md) | the lending feature: roles and per-library checks, checkout, borrower links, notices, deleting a library |
| [library-imports.md](library-imports.md) | cataloguing from a spreadsheet: the template, the three rules, the console command |
| [sitemap.md](sitemap.md) | the static sitemap and the five-language canonical/hreflang contract |
| [database-charset.md](database-charset.md) | InnoDB + `utf8mb4_unicode_520_ci`: why, the connection charset, the traps |
| [shrine-data.md](shrine-data.md) | the shrine/association dataset: what a shrine earns, correctness workstreams, decisions taken |
| [timeline-and-corpus.md](timeline-and-corpus.md) | the Kentenich timeline and the Schmiedl text corpus: data, settled design, what Part 2 waits for |
| [reviews.md](reviews.md) | the review/comment feature: tabled, what exists, the live exposure |
| [acl-baseline.json](acl-baseline.json) | generated: every role, guard and rule, sorted for `diff`. Regenerate with `tools/acl-table.php --format=json` after any authorization change |

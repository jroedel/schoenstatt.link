# Documentation

| document | what it covers |
| --- | --- |
| [DEPLOY.md](DEPLOY.md) | how a deploy actually runs, the two-identity SFTP arrangement, server facts, post-deploy smoke checks, rollback |
| [exception-reporting.md](exception-reporting.md) | how production failures reach your inbox, the on-disk store, the capture/privacy profile, and the `fetch-` / `clear-exceptions.sh` scripts |
| [caching.md](caching.md) | the APCu layer: why an oversized cache item wipes the whole segment, the `max_cached_item_size` budget, `/sm/cache-status`, and what to watch when adding a cached call |
| [BACKLOG.md](BACKLOG.md) | the modernization backlog — what is done, what is queued, and the reasoning behind the sequencing |

Operational instructions for working *on* this codebase — the local Docker
capsule, how to run the test suites, coding standard, architecture — live in
[`../CLAUDE.md`](../CLAUDE.md), which is the single source of truth for those and
is kept current deliberately.

# Deploying schoenstatt.link

Deployment is phploy over **SFTP as a restricted deploy account**
(port-22 jail, no exec — its port-222 shell was intentionally revoked
2026-08-03, so a phploy vulnerability could move files but never run
anything). Server-side commands still happen, but as explicit `ssh`
invocations from `pre-deploy[]`/`post-deploy[]` hooks running under
*your own* shell account (port 222). Everything is plain `post-deploy[]`
(never `post-deploy-remote[]`) so the exact execution order is under our
control — mixing the two makes the order unpredictable.

**Never run `phploy --submodules`/`-m`** — its directory purge recursively
deletes freshly-uploaded trees (it took out `module/JUser/src` on
2026-08-02; see docs/BACKLOG.md "Deploy ops").

## Before the next deploy: check the API signing key

One-time prerequisite for the firebase/php-jwt 7 upgrade (2026-08-03). v7
rejects HMAC keys shorter than the digest size, so an
`ApiRequest.jwtAuth.cypherKey` under **32 bytes** breaks every authenticated API
request. The key lives in untracked server-side config, so nothing in the repo
can tell us how long production's is. It does not fail at boot — the site looks
fine and only the API dies — so check it first, over the port-222 shell account:

```bash
ssh -p 222 <admin>@dedi2934.your-server.de \
  'php -r "\$c = include \"public_html/schoenstatt.link/config/autoload/local.php\";
   printf(\"%d bytes\n\", strlen(\$c[\"ApiRequest\"][\"jwtAuth\"][\"cypherKey\"] ?? \"\"));"'
```

Prints byte count only, never the key. If it is under 32, lengthen it *before*
deploying — note that replacing the key invalidates every JWT already issued
(they last six months), so API clients would have to sign in again.

## Before the next deploy: two API endpoints start requiring a token

The authorization-bypass fix (2026-08-03) makes `GET /api/v1/libraries/:id` and
`GET /api/v1/libraries/:id/pending-labels` enforce the JWT their route config
always asked for; until now they answered anyone. Any client that has been
calling them without an `Authorization: Bearer` header will get 401 after this
deploy — the label-printing workflow is the one to check. Tokens come from
`POST /api/v1/login`. Nothing else changes: `/api/v1/literature`,
`/api/v1/associations` and the public dictionary reads stay open, and
`/api/v1/libraries/:id/books` was already gated.

## Before deploying a PHP-version rung

**Flip the konsoleH PHP version first, then deploy — never the other way
round.** Composer writes `vendor/composer/platform_check.php` from the
`require.php` constraint and PHP evaluates it on *every* request, so a release
whose lock requires 8.4 will hard-fatal every page on an 8.3 server. There is no
graceful degradation and no partial outage: it is the whole site. The reverse
order is safe, because the new PHP running the previous release is a combination
the capsule verifies before the rung lands.

Also copy the per-version `php.ini` across (see Server facts below) — the new
version reads a different file.

## The deploy

```bash
git checkout master && git pull origin master
git submodule update --init --recursive

php8.0 phploy.phar
```

The phploy run does, in order (`phploy.ini.dist` is the committed
template: `config.sh` copies it to the gitignored `phploy.ini`, where the
TODOs get real values):

1. `pre-deploy[]`: ssh — back up the server's `public/.htaccess` to
   `data/htaccess-backups/htaccess-<timestamp>`. The file is tracked and
   clobbered on every deploy (since 2026-08-03); the backup is the escape
   hatch, outside the docroot, ~2.6 KB per deploy.
2. Diff-uploads the superproject against the server's `.revision` (SFTP).
3. `purge[] = "data/config/"` — empties the merged-config/module-map cache
   (production runs with config caching on; this is why config changes
   take effect).
4. `post-deploy[]` hooks:
   1. `bash tools/deploy-submodules.sh` — rsync `--delete` of the three
      submodule trees over the shell account (clean-tree guard built in;
      no-ops fast when the pointers didn't move).
   2. ssh — `php composer.phar install --no-dev --no-interaction
      --optimize-autoloader && php bin/console cache:clear-config && php
      bin/console cache:flush-persistent`. All three run server-side in one
      session, in that order because `bin/console` needs symfony/console in
      `vendor/`.
      - `cache:clear-config` deletes the merged-config and module-map cache
        files, re-clearing what a visitor may have re-cached from the
        half-deployed tree since step 3. It derives the paths from the module
        listener options, so a changed cache key cannot leave it deleting
        nothing.
      - `cache:flush-persistent` requests `/en/sm/clear-persistent-cache`
        over HTTP, which flushes the APCu storage adapter
        (`apcu_clear_cache()`) — the whole web APCu segment, no process kills
        needed. **It has to be an HTTP request:** an APCu segment belongs to
        the SAPI that created it, so a CLI process sees its own (or, with the
        default `apc.enable_cli=0`, none) and could never flush the FastCGI
        pool's. Verified 2026-08-04 — a CLI `apcu_clear_cache()` left all 21
        web-segment entries in place. Running it on the server means the
        maintenance key comes from the app's own config and never appears in
        `phploy.ini`, a shell history or an access log.
   3. wget `/en/associations/do-work` with an `X-Api-Key` header —
      post-deploy data maintenance. Needs the fully-deployed site. Still a
      wget because the work itself has not been ported to a command yet.
   4. `git tag -f deploy/$(date +%Y%m%d-%H%M)` — local tag recording
      exactly what went live (`git tag -l 'deploy/*'` answers "what's
      deployed?"). Never pushed.
   5. `SMOKE_PROD_CACHE_KEY=<api key> bash tools/smoke-prod.sh` — the
      scripted smoke checks (next section). A failure ends the deploy
      loudly with a non-zero exit. It runs after the server-side steps,
      so a passing run means the *fully* deployed site is healthy — no
      expected-failure window.

## Running the server-side steps by hand

If a hook fails mid-run (or you deploy from a machine without the shell
key), finish in an interactive SSH session (port 222) in this order:
sync submodules (`tools/deploy-submodules.sh <user@host> <port>`, or
tar-over-SFTP + extract), then in the app dir

```bash
php composer.phar install --no-dev --no-interaction --optimize-autoloader
php bin/console cache:clear-config
php bin/console cache:flush-persistent
```

then re-run the `do-work` wget and `bash tools/smoke-prod.sh` locally.

`bin/console list` shows everything available. `cache:flush-persistent`
takes `--url` when the configured `sion_model.canonical_base_url` is not
the host you mean, and reads the key from `SCH_MAINTENANCE_KEY` when the
local config has none — prefer that over `--key`, which lands in shell
history.

## Server facts worth remembering

- SSH port **22 is a restricted SFTP jail** (no exec — rsync/scp fail with
  "exec request failed on channel 0"). The deploy account's full shell on
  port 222 was **revoked 2026-08-03**; phploy connects over 22, SFTP only.
  Server commands run as `ssh` hooks (and `deploy-submodules.sh`) under
  your own shell account on port 222 — two different identities by design.
- phploy needs `php8.0` locally (newer CLIs lack mbstring).
- `phploy.ini` + `.phploy` hold credentials — never committed. `config.sh`
  seeds them from the committed templates (`phploy.ini.dist`,
  `.phploy.dist`); the hooks above live in `phploy.ini` under
  `[production]`.
- Web PHP is FastCGI with a per-account php.ini at
  `/home/httpd/php84-ini/ourlink/php.ini` (**per PHP version**: the 8.3-era
  file was under `php83-ini/`, the 7.4-era one under `php74-ini/`). This is the
  trap when flipping the konsoleH PHP version: the new version reads a *different*
  file, so anything tuned in the old one silently reverts to defaults — copy it
  across as part of the flip. php.ini changes are the ONE case that
  needs `pkill -u ourlink -f php` (workers re-read ini on respawn);
  deploys never do.

## Smoke checks after deploying

- `bash tools/smoke-prod.sh` (the last post-deploy hook, also runnable any
  time) covers everything scriptable: homepage, both 404 flavors, the
  sitemap, and a random sample of *cold* sitemap pages (the fatal-200
  regression class — fixed URLs can't catch it). It also guards the
  transport layer: response compression (br/gzip) and the static-asset
  Cache-Control policies from the now-tracked `public/.htaccess` fail the
  deploy on regression; HTTP/2 only WARNs (hoster-provided, not ours to
  fix). Non-zero exit on any failure.
- With `SMOKE_PROD_CACHE_KEY` set (any `sion_model.api_keys` value — the
  same one the `do-work` hook sends), it also polls
  `/en/sm/cache-status`, passing the key as an `X-Api-Key` header rather
  than in the URL, and WARNs — without failing — when the APCu
  segment is ≥80% full or has ever expunged. Until the production
  `apc.shm_size` raise lands, expect this to be the early-warning signal
  that the 32M default is saturating (first live reading 2026-08-03:
  2 expunges within hours of the PHP 8.3 flip).
- Still manual: a sign-in round trip with a real email.

## Reading production exceptions

Failures are recorded per distinct exception under `data/exceptions/` on the
server, and the first occurrence of each is emailed to
`webmaster@schoenstatt.link`. Pull them down and clear them with:

```bash
bash tools/fetch-exceptions.sh              # mirror + summary table
less data/exceptions-prod/<fp>/first.txt    # the write-up
bash tools/clear-exceptions.sh --yes <fp>   # fixed it? clearing re-arms notification
```

Both scripts use the shell account on port 222 (port 22 is the SFTP jail, no
exec). **[exception-reporting.md](exception-reporting.md) is the full reference** —
what counts as one failure, the capture/privacy profile, the configuration keys,
troubleshooting, and the limitations.

Two things about it that bear on deploying specifically:

- **A deploy must clear `data/config/`** for newly registered services to be
  seen. The existing post-deploy hooks already do this twice; it is called out
  because the symptom is confusing — `Module.php` picks up changes immediately
  (it is a class file) while the merged service map stays stale, so you get
  `Unable to resolve service ...` for a service that is plainly registered in
  the config you are looking at.
- **Failures before the container exists** — a broken merged config, a module
  that will not load — are recorded but *cannot* be emailed: the recipients live
  in the very configuration that failed to build. They land in the store and in
  `data/logs/bootstrap-fatal.log`, so check there when a deploy goes quiet.

## Rollback

`php8.0 phploy.phar --rollback` reverts the superproject files (SFTP, so
this still works unchanged) — but the hooks don't re-run, so follow the
by-hand list above: check out the matching older superproject commit
locally so the submodule pointers roll back too, re-sync the submodule
trees, and run `php composer.phar install --no-dev` in your SSH session
to reinstate the older lock. A clobbered `.htaccess` can be restored from
`data/htaccess-backups/`. The DB migrations so far are
backward-compatible.

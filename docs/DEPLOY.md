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

## Before the v3 API can be used: one migration and one account per agent

The code shipped 2026-08-09. Three things it deliberately does **not** do for you,
because none of them should happen without someone deciding it:

1. ~~**Run `database/db6.6.sql`** on production.~~ **Done 2026-08-09.** It creates the
   `sch_api_bot` role, and nothing else on the site names that role — so until it existed,
   every agent request was a 401. The script is idempotent (`INSERT … WHERE NOT EXISTS`),
   so re-running it on any environment that lags is safe.

   If the role was inserted by raw SQL rather than through the create-role screen, flush
   the persistent cache: `UserTable::getRolesValueOptions()` is APCu-cached under
   `roles-value-options` and invalidated by the `user-role` entity, which a direct
   `INSERT` does not touch — so the role stays missing from the users screen's Roles
   multiselect until `php bin/console cache:flush-persistent` runs. The role itself works
   regardless; `BotIdentity` reads `user_role` directly.
2. **Run `database/db6.7.sql`** on production, and run it *before* deploying the code
   that reads it. It creates `user_api_token`, the registry that makes an issued token
   revocable, and `App\Api\BotIdentity` refuses any token whose `jti` has no row there
   — so a deploy that lands ahead of the table turns every v3 request into a 401. The
   table is empty and harmless on a server running the old code, which is why this
   order is the safe one. `CREATE TABLE IF NOT EXISTS`, so re-running it is safe.

   Nothing breaks for the mobile apps: the v1 API does not consult the registry, and
   the tokens already in the field keep working.

3. **Create each bot account and grant it the role.** Register the address like any
   other account, then grant `sch_api_bot` through the users screen or:

   ```sql
   INSERT INTO user_role_linker (user_id, role_id)
   SELECT <user_id>, id FROM user_role WHERE role_id = 'sch_api_bot';
   ```

   Deleting that row cuts the account off from the API immediately — the role is checked
   on every request, not baked into the token. It is the blunt instrument, though: it
   revokes *every* token the account holds. For one token, use the revoke button on
   `/en/users/:id/api-tokens`.

Tokens are issued from that same screen; see [api-v3.md](api-v3.md) for the whole
flow, including why the button only appears for accounts holding `sch_api_bot`.

**Until `SYMFONY_KERNEL` is flipped globally, v3 answers only behind the canary
cookie** — which an agent will not send. The canary is how to verify the endpoints
against production data before the flip, not a way to run agents.

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

## The two front controllers, and the cookies that override them

`public/.htaccess` picks one front controller per request, from a site-wide default plus
two per-visitor overrides. It is deployed with the file — nothing to enable by hand.

| cookie | front controller | what it is for |
|---|---|---|
| *(none)* | the site default — **laminas-mvc today** | every visitor and every agent |
| `sl_symfony_canary=1` | `App\Kernel` | verifying ported routes against production before the flip |
| `sl_symfony_canary=0` | `Laminas\Mvc\Application` | the way back for one person after the flip |

Both overrides are deployed now, so the global flip is one *added* line rather than an
edit; see "Flipping the Symfony kernel on globally" below.

To have the post-deploy smoke run exercise it, add the cookie to the hook's environment
in `phploy.ini` (beside `SMOKE_PROD_CACHE_KEY`):

```
post-deploy[] = "SMOKE_PROD_CACHE_KEY=… SMOKE_PROD_CANARY_COOKIE='sl_symfony_canary=1' bash tools/smoke-prod.sh"
```

That adds ~35 checks. The variable is only a switch now — its *value* is not read, because
there are two cookies and the script names both itself. What it asserts is the **pair**:
whichever kernel is the site default really serves ordinary traffic, and the override
really reaches the other one. It probes which is the default rather than assuming, so the
same hook keeps working across the flip instead of failing loudly on the one day it most
needs to be believed. Leaving the variable unset skips the whole block, which is the
default.

Two things worth checking through the canary after a deploy that touches ported routes,
both public and side-effect-free:

```bash
curl -H 'Cookie: sl_symfony_canary=1' https://schoenstatt.link/api/v3/schema        # 200 JSON
curl -H 'Cookie: sl_symfony_canary=1' https://schoenstatt.link/api/v3/associations  # 401
```

The first proves the whole ServiceBridge path works in production — the schema is
generated from live config and a live database query — and the second proves the token
gate is on. A 302 to `/en/…` from either means the canary cookie did not take effect,
not that v3 is broken.

Checking a *signed-in* ported page is still manual and is the one thing the canary buys
that a global flip could not. Sign in as an administrator and use the **Switch kernel**
item in the navbar — it offers whichever kernel you are *not* currently on, and says which
you have moved to — then load `/en/sm/view-changes`, `/en/sm/data-problems`,
`/en/sm/phpinfo`, `/en/admin` and one association edit form. Click it again to clear the
override and go back to the site default; closing the browser does the same, since it is a
session cookie.

The item is visible to `sch_administrator` only, and it needs cookie consent: without it
the site strips every `Set-Cookie` and the toggle reports that instead of appearing to
work.

**Never add a `SetEnv SYMFONY_KERNEL` line to `.htaccess`, for either value.** mod_env
runs after all of mod_setenvif, so it wins regardless of order and both cookies stop
working silently. `test/Integration/KernelCanaryTest` fails on it.

## Flipping the Symfony kernel on globally

The one change that makes every visitor — and every automated agent, which sends no
cookie — reach `App\Kernel` instead of `Laminas\Mvc\Application`. It is what the v3 API is
waiting for. Everything it needs is already deployed; the flip is one line.

**Add it above the two cookie overrides in `public/.htaccess`:**

```apache
SetEnvIf Request_URI ".*" SYMFONY_KERNEL=1
```

Order and directive are both load-bearing, and each failure mode is silent — a default
written *below* an override overwrites it (mod_setenvif takes the last match), and a
`SetEnv` beats every override whatever the order. Both are pinned by
`test/Integration/KernelCanaryTest`, which is why the flip should be a commit rather than
a hand-edit on the server.

### Before

- [ ] `SMOKE_PROD_CANARY_COOKIE` is wired into the `phploy.ini` smoke hook, and the last
      deploy's run passed. That is the pre-flip evidence: it renders every public ported
      route through the Symfony kernel against production's own data, ICU and
      translations, and checks the three `LaminasResponseConverter` rules on a bridged
      page behind the real TLS proxy — the one thing the capsule cannot reproduce.
- [ ] `database/db6.6.sql` is applied and at least one agent account holds the API-bot
      role (see the v3 prerequisites above). Without it every agent request 401s the
      moment the flip makes v3 reachable.
- [ ] The signed-in walk-through above has been done through the canary, in a
      non-English locale as well as English. `tools/port-baseline.php` is the mechanical
      version; `docs/strangler.md` has the procedure and the known differences.

### After

- [ ] Re-run `bash tools/smoke-prod.sh` (with the canary variable set). It flips its own
      assertions automatically: the default is now Symfony and the `=0` cookie must
      reach laminas.
- [ ] Watch `data/exceptions` — `tools/fetch-exceptions.sh`. Ported routes now report
      through the configured pipeline including notification, so a new failure class
      arrives as email rather than silence.
- [ ] `/en/sm/cache-status` for APCu saturation: the Twig compile cache is on disk, not
      APCu, but ported routes touch different cache keys than the laminas twins did.

### Rolling back

In order of how much they cost:

1. **One person, no deploy.** Set `sl_symfony_canary=0` — the **Switch kernel** navbar
   item does it — and that visitor is back on laminas immediately. Enough to compare a
   suspect page against its laminas twin.
2. **Everyone, no deploy.** Delete the added line from the server's
   `public_html/schoenstatt.link/public/.htaccess`. Takes effect on the next request; no
   pool restart, because `.htaccess` is read per request. Note that **the next deploy
   overwrites it** — the pre-deploy hook copies the server's file into
   `data/htaccess-backups/` precisely because of that — so this buys time, it does not
   end the incident.
3. **Everyone, durably.** Revert the flip commit and deploy. This is the only rollback
   that survives the next deploy, and the reason to keep the flip as its own commit that
   touches nothing else.

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
      - That URL, and `/en/sm/cache-status`, are the two routes ported to the
        Symfony kernel (see [strangler.md](strangler.md)). Production still
        serves them from laminas-mvc, because `SYMFONY_KERNEL` is unset there;
        both implementations coexist and answer the same URL with the same
        payload. What differs is the refusal: laminas answers `302` to the
        sign-in page, the Symfony controller answers `401` with a JSON body.
        `cache:flush-persistent` reports either as a rejected key, so flipping
        the flag needs no change to the hook.
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
  `/en/sm/cache-status` (both APCu **and** OPcache), passing the key as an `X-Api-Key` header rather
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
  - Step 3 is what clears it, and it runs *after* the upload in step 2, so
    between them the new files meet the old service map. That window is the
    reason `layout.phtml` asks whether the `servingNote` helper is registered
    before calling it: an unresolved view helper inside a layout throws after
    the response is assembled, which is a blank HTTP 200 on every
    laminas-rendered page rather than one missing footer line. Any future
    layout-level helper wants the same guard.
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

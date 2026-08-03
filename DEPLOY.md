# Deploying schoenstatt.link

Deployment is phploy over **SFTP only**, plus manual server-side steps in
a separate interactive SSH session. The deploy account's shell access over
port 222 was intentionally revoked (2026-08-03), so phploy can move files
but can never *run* anything on the server.

**Never run `phploy --submodules`/`-m`** — its directory purge recursively
deletes freshly-uploaded trees (it took out `module/JUser/src` on
2026-08-02; see BACKLOG.md "Deploy ops").

## The deploy

```bash
git checkout master && git pull origin master
git submodule update --init --recursive

php8.0 phploy.phar
```

The phploy run does, in order:

1. Diff-uploads the superproject against the server's `.revision` (SFTP).
2. `purge[] = "data/config/"` — empties the merged-config/module-map cache
   (production runs with config caching on; this is why config changes
   take effect).
3. `post-deploy[]` hooks — all HTTP or local now, no remote exec
   (`phploy.ini.dist` is the committed template: `config.sh` copies it to
   the gitignored `phploy.ini`, where the TODOs get real values; the
   retired remote-exec hooks live there commented, for reference):
   1. wget `/sm/clear-persistent-cache?key=…` — SionModel's endpoint
      flushes the APCu storage adapter, i.e. `apcu_clear_cache()`: the
      whole web APCu segment. No process kills needed.
   2. wget `/en/associations/do-work?key=…` — post-deploy data
      maintenance. Needs the fully-deployed site.
   3. `git tag -f deploy/$(date +%Y%m%d-%H%M)` — local tag recording
      exactly what went live (`git tag -l 'deploy/*'` answers "what's
      deployed?"). Never pushed.
   4. `SMOKE_PROD_CACHE_KEY=<api key> bash tools/smoke-prod.sh` — the
      scripted smoke checks (next section). A failure ends the deploy
      loudly with a non-zero exit.

## Manual server-side steps (separate SSH session)

Needed only when the corresponding inputs changed; a routine
superproject-only deploy skips all of this.

- **Submodules changed** (`module/SionModel`, `module/JUser`,
  `module/JTranslate` pointers moved): sync their trees yourself. From a
  machine whose SSH identity still has exec, `tools/deploy-submodules.sh
  <user@host> <port>` does rsync `--delete` with a clean-tree guard;
  otherwise upload a tarball over SFTP and extract it in your session.
- **composer.lock changed**: in the app dir, `php composer.phar install
  --no-dev --no-interaction --optimize-autoloader`.
- **After either of the above**: clear the config cache again —
  `rm -f data/config/module-*-cache.*.php` in the app dir — because a
  visitor may have re-cached the merged config between phploy's purge and
  your manual steps.
- Then re-run what fired too early: the two wget endpoints and
  `bash tools/smoke-prod.sh` locally. (phploy's hooks run right after the
  file sync, so on lock- or submodule-changing deploys expect the in-run
  smoke pass to fail first — that's the half-deployed window, not a
  regression. It must pass on the re-run.)

## Server facts worth remembering

- SSH port **22 is a restricted SFTP jail** (no exec — rsync/scp fail with
  "exec request failed on channel 0"). The deploy account's full shell on
  port 222 was **revoked 2026-08-03**; phploy connects over 22, SFTP only.
  Server commands go through your own separate SSH session.
- phploy needs `php8.0` locally (newer CLIs lack mbstring).
- `phploy.ini` + `.phploy` hold credentials — never committed. `config.sh`
  seeds them from the committed templates (`phploy.ini.dist`,
  `.phploy.dist`); the hooks above live in `phploy.ini` under
  `[production]`.
- Web PHP is FastCGI with a per-account php.ini at
  `/home/httpd/php83-ini/ourlink/php.ini` (per PHP version: the 7.4-era
  file was under `php74-ini/`). php.ini changes are the ONE case that
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
  hook reuses the clear-persistent-cache key), it also polls
  `/en/sm/cache-status` and WARNs — without failing — when the APCu
  segment is ≥80% full or has ever expunged. Until the production
  `apc.shm_size` raise lands, expect this to be the early-warning signal
  that the 32M default is saturating (first live reading 2026-08-03:
  2 expunges within hours of the PHP 8.3 flip).
- Still manual: a sign-in round trip with a real email.

## Rollback

`php8.0 phploy.phar --rollback` reverts the superproject files (SFTP, so
this still works unchanged). Everything else is the manual list above in
reverse: check out the matching older superproject commit locally so the
submodule pointers roll back too, re-sync the submodule trees, and run
`php composer.phar install --no-dev` in your SSH session to reinstate the
older lock. The DB migrations so far are backward-compatible.

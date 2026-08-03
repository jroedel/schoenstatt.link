# Deploying schoenstatt.link

Deployment is phploy plus hooks in `phploy.ini` — one command end to end.
**Never run `phploy --submodules`/`-m`** — its directory purge recursively
deletes freshly-uploaded trees (it took out `module/JUser/src` on
2026-08-02; see BACKLOG.md "Deploy ops").

## The deploy

```bash
git checkout master && git pull origin master
git submodule update --init --recursive

php8.0 phploy.phar
```

That single phploy run does, in order:

1. Diff-uploads the superproject against the server's `.revision` (SFTP).
2. `purge[] = "data/config/"` — empties the merged-config/module-map cache
   (production runs with config caching on; this is why config changes
   take effect).
3. `post-deploy[]` hooks, in this order (order matters):
   1. `tools/deploy-submodules.sh` — rsyncs the three `module/` submodules
      over SSH port 222 (`--delete`, clean-tree guard; falls back to
      tar-over-SFTP if rsync ever disappears from the server).
   2. `ssh -t -p 222 …` — re-clears `data/config/` (closing the race where
      a visitor re-caches config between the purge and the submodule
      sync) and runs `php composer.phar install --no-dev` on the server.
      A no-op when the lock didn't change.
   3. wget `/sm/clear-persistent-cache?key=…` — SionModel's endpoint
      flushes the APCu storage adapter, i.e. `apcu_clear_cache()`: the
      whole web APCu segment. No process kills needed.
   4. wget `/en/associations/do-work?key=…` — post-deploy data
      maintenance. Must stay LAST: it needs the fully-deployed site.

## Server facts worth remembering

- SSH port **22 is a restricted SFTP jail** (no exec — rsync/scp fail with
  "exec request failed on channel 0"); the **full shell is on port 222**.
- phploy needs `php8.0` locally (newer CLIs lack mbstring).
- `phploy.ini` + `.phploy` hold credentials (from `config.sh`) — never
  committed; the hooks above live in `phploy.ini` under `[production]`.
- An SSH key for `ourlink@…` (`ssh-copy-id -p 222 …`) keeps the hooks from
  prompting for passwords mid-deploy.
- Web PHP is FastCGI with a per-account php.ini at
  `/home/httpd/php74-ini/ourlink/php.ini`. php.ini changes are the ONE
  case that needs `pkill -u ourlink -f php` (workers re-read ini on
  respawn); deploys never do.

## Smoke checks after deploying

- `bash tools/smoke-prod.sh` runs everything scriptable: homepage, both 404
  flavors, the sitemap, and a random sample of *cold* sitemap pages (the
  fatal-200 regression class — fixed URLs can't catch it). Non-zero exit on
  any failure, so wire it in as the LAST post-deploy hook in `phploy.ini`
  (after do-work):

      post-deploy[] = "bash tools/smoke-prod.sh"

- Still manual: a sign-in round trip with a real email.

## Rollback

`php8.0 phploy.phar --rollback` for the superproject (hooks run again,
including the submodule sync — check out the matching older superproject
commit first so the submodule pointers roll back too); `php composer.phar
install --no-dev` reinstates the older lock. The DB migrations so far are
backward-compatible.

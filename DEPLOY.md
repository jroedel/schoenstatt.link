# Deploying schoenstatt.link

Deployment is phploy (superproject) + a tar-over-SFTP script (the three
submodules). **Never run `phploy --submodules`/`-m`** — its directory purge
recursively deletes freshly-uploaded trees (it took out `module/JUser/src`
on 2026-08-02; see BACKLOG.md "Deploy ops").

## Prerequisites (one-time, per machine)

- `php8.0` for phploy (the newer CLIs lack mbstring; the phar predates PHP 8.1).
- `phploy.ini` + `.phploy` from `config.sh` (credentials — never committed).
- An SSH key installed for `ourlink@…` makes the scripts non-interactive
  (`ssh-copy-id`); with password auth you'll be prompted a few times.

## The deploy

```bash
git checkout master && git pull origin master
git submodule update --init --recursive

php8.0 phploy.phar                # superproject: diff-upload + hooks (below)
./tools/deploy-submodules.sh      # the three module/ submodules
```

If `composer.json`/`composer.lock` changed, dependencies must be reinstalled
on the server (an interactive shell if remote exec is blocked):

```bash
ssh ourlink@dedi2934.your-server.de
cd public_html/schoenstatt.link && php composer.phar install --no-dev --no-interaction
```

## What the phploy.ini hooks already handle

- `purge[] = "data/config/"` — empties the merged-config + module-map cache
  after upload. Production runs with config caching on (`APP_ENV` unset),
  so this purge is mandatory; it's why config changes take effect.
- `post-deploy[]` wget to `/sm/clear-persistent-cache?key=…` — SionModel's
  cache-clear endpoint calls `flush()` on the APCu storage adapter, which is
  `apcu_clear_cache()`: the ENTIRE web APCu segment is emptied. No process
  kills needed after a deploy.
- `post-deploy[]` wget to `/en/associations/do-work?key=…` — post-deploy
  data maintenance. Keep it LAST in the hook order: it must run against the
  fully-deployed site.

`pkill -u ourlink -f php` is only ever needed to pick up **php.ini** changes
(e.g. `/home/httpd/php74-ini/ourlink/php.ini`), not for deploys.

## Smoke checks after deploying

- `https://schoenstatt.link/en/` renders; sign-in round trip with a real email.
- A couple of *cold* pages from the sitemap (the fatal-200 regression).
- `/en/no-such-page` → the normal 404 page; `/api/no-such-thing` → JSON 404.
- No `Notice:`/`Fatal error` text anywhere; errors belong in the logs now.

## Rollback

`php8.0 phploy.phar --rollback` for the superproject; re-run
`tools/deploy-submodules.sh` from the previous commit for the submodules;
`php composer.phar install --no-dev` again if the lock changed. The DB
migrations so far are backward-compatible.

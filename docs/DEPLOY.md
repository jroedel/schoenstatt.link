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

## Before the next deploy: phrase-table hygiene (schoenstatt.link#59)

The branch answering the translation agent's ten change requests. Two library
migrations, one data file, one retirement pass, and one production task that is not a
deploy step at all.

**The code is order-forgiving on purpose.** Unlike the 2026-08-10 run below, nothing here
selects a column the old schema lacks: every read *and* write of
`trans_translations_history` is behind a `SHOW TABLES` check, verified by hiding the table
and driving both paths. So a deploy that lands before the schema degrades to "no history
shown, none recorded" rather than 500ing the translation admin area. Run it in the order
below anyway — the point of the guard is that a mistake is survivable, not that the order
does not matter.

### The order

1. **Merge the two submodule PRs first** (`jroedel/laminas-jtranslate#19`,
   `jroedel/laminas-juser#11`), then the superproject PR. Already done for the first two;
   the pointers on the branch reference their merge commits on `modernization`.

2. **JTranslate migrations 005 and 006.** 005 rewrites four CRLF phrases to LF and merges
   what that collapses; 006 creates `trans_translations_history`. 006 is DDL, so it needs
   the credentials the 003/004 run used — see the two-pass note below. Preview from the
   capsule, where the code already exists:

   ```bash
   docker compose exec -T app php bin/console jtranslate:migrate --status
   docker compose exec -T app php bin/console jtranslate:migrate --pretend
   ```

   **005's SQL is exact from the capsule** — it touches no new column and depends only on
   the data, so what `--pretend` prints is what production will run. Both are re-runnable:
   005 selects on `phrase LIKE '%\r%'` and 006 is a `CREATE TABLE IF NOT EXISTS` behind a
   `hasTable()` check.

   005 covers **every project in the shared table**, which is deliberate and argued in its
   docblock. Two `patres` rows have their line endings rewritten, and `patres` runs
   JTranslate 1.0.x with no normalization — if one of its templates emits CRLF it will
   re-insert its own row until that installation moves to this line. Two rows, one
   sentence each.

3. **The code deploy.** `php phploy.phar` as usual. The `post-deploy[]` hooks already
   clear the config cache, flush APCu and run `jtranslate:export-catalogs`, so the
   catalogs rebuild without being asked.

4. **`database/db7.3.sql`.** Ordinary app credentials — it is four `UPDATE`s. It renames
   three corrected source strings *in place* so their translations follow them (correcting
   a typo otherwise abandons the phrase, since the text is the identity) and retires four
   dead ones. It must run **after** step 2: the renames compute hashes the way
   `PhraseIdentity` now does, over normalized line endings.

   ```bash
   ssh -p 222 <admin>@dedi2934.your-server.de \
     'cd public_html/schoenstatt.link && mysql -u<user> -p <db> < database/db7.3.sql'
   ```

5. **Rebuild the catalogs again**, because step 4 changed three catalog *keys*:

   ```bash
   ssh -p 222 <admin>@dedi2934.your-server.de \
     'cd public_html/schoenstatt.link && php bin/console jtranslate:export-catalogs \
      && php bin/console cache:flush-persistent'
   ```

6. **Retire the text corpus**, and only now — the code fix in step 3 is what stops the
   rows coming back on the next page view of a text. Dry-run first; the counts are 219
   and 90 in the capsule and production is within a few rows.

   ```bash
   ssh -p 222 <admin>@dedi2934.your-server.de \
     'cd public_html/schoenstatt.link && php bin/console jtranslate:retire --origin-route=text --dry-run'
   ```

   then the same without `--dry-run`, and again for `--origin-route=texts`. Reversible with
   `--undo`.

### Not a deploy step: the four leaked API tokens

`juser/user/api-tokens` used to append the freshly minted JWT to its success message, and
the messengers translate the finished message — so real tokens became phrase rows,
readable by any account holding `sch_api_translator`, and were copied into the English row
and the exported catalogs on disk. The code path is fixed in step 3; **the rows are not**,
and retiring is not enough, because the requirement is that the strings cease to exist.

After the deploy: find them (`phrase LIKE '%eyJ%'` on route `juser/user/api-tokens`),
**revoke the `jti` each one contains** in `user_api_token`, delete the phrase rows, and
rebuild the catalogs so the files on disk lose them too. The four known ids are capsule
ones and that database is disposable; production has its own or none.

### What changes for anyone watching the site

- **Breadcrumbs are translated.** `/it/shrines` reads "Santuari" where it read "Shrines".
- **Validator messages are translated as templates**, so the phrase table now holds
  `'%hostname%' is not a valid hostname for the email address` with the placeholder
  intact, in the `default` text domain rather than scattered across four. The ~19 rows
  keyed by an *interpolated* message are orphaned and worth retiring; nobody has.
- **Phrase discovery starts working on Symfony-served routes**, which is every route from
  the `.htaccess` flip onward. It was queueing misses and throwing them away. Expect a
  step change in new phrases — one smoke run records 170 — and roughly double the rate on
  ported pages, because the Twig layer looks a miss up in the page's domain and then in
  `default` and both are now recorded. The second row arrives pre-translated.
- **`/admin/translations`** marks phrases with history, and the edit screen shows the
  thread.

## Done 2026-08-11: breadcrumb data labels and `database/db7.4.sql`

Answered change requests §11–§13 (PR #62, jtranslate#21). **Applied to production
2026-08-11**, in the order below, and verified live. Kept rather than deleted because the
ordering constraint is the reusable part and it is the opposite of what it looks like.

**Deploy the code *first*, then run the migration.** The migration retires phrase rows that
the code fix stops arriving; run it first and the next crawl of a publication page files
them again and clears `retired_on` doing it — discovery un-retires whatever the site still
looks up, which is the property that makes retirement safe and here makes the order matter.

### What it actually retired

| statement | what | rows |
|---|---|---|
| 1 | publication titles | **3,698** (14.4 s) |
| 2 | composition names | 302 |
| 3 | library names | not captured |
| 4 | association names in `Application`/`default` | 108 |
| 5 | the composed labels above them | 24 |
| 6 and 7 | db7.3's three `en_US` rows | **0** |

Two of those are worth reading. **3,698 is larger than the 3,086 the reporting agent
counted** the day before, and larger again than the 436 it counted a few hours before that —
the corpus was still capturing the catalogue as crawlers walked it, which is what the
"essentially at its ceiling" reading of those two counts underestimated. And **6 and 7
touching nothing is the expected result**, not a failure: the reporter had already corrected
those three rows through the v3 API. They exist for every other environment.

1. **The code deploy.** `php phploy.phar` as usual. The `post-deploy[]` hooks clear the
   config cache, flush APCu and rebuild the catalogs. **The APCu flush is load-bearing this
   time**: the navigation branches are cached there, `apc.ttl` is 0 so they never expire on
   their own, and the fix reads them back out of the cache before flagging — a stale branch
   is *harmless* by design, but the flush is what makes the new labels appear at once.

2. **`database/db7.4.sql`.** Ordinary app credentials — five `UPDATE`s, one `INSERT` into
   the history table, one more `UPDATE`. Re-runnable; every statement is guarded on the
   state it changes.

   ```bash
   ssh -p 222 <admin>@dedi2934.your-server.de \
     'cd public_html/schoenstatt.link && mysql -u<user> -p <db> < database/db7.4.sql'
   ```

   Statements 1–5 retire record names the breadcrumb filed as phrases (§12). Statements 6
   and 7 set three `en_US` rows to the text db7.3 renamed the key to (§13) — the reporting
   agent already did this on production through the API, so expect them to change nothing
   there and everything on a restored dump.

3. **Rebuild the catalogs**, because step 2 changed English text:

   ```bash
   ssh -p 222 <admin>@dedi2934.your-server.de \
     'cd public_html/schoenstatt.link && php bin/console jtranslate:export-catalogs \
      && php bin/console cache:flush-persistent'
   ```

4. **Tell the translation agent that `null` no longer deletes.** `PATCH /api/v3/phrases`
   answers 422 to `{"de": null}` and takes `{"_retract": ["de"]}` instead (§11.3, adopted
   at the consumer's own request). It is published as `writable.retract` in
   `GET /api/v3/schema/phrase`, so a client that reads the schema at request time finds it;
   one that hardcoded `null` gets a 422 and no data loss, which is the point.

### Verified live, 2026-08-11

Fetched over plain HTTPS after the migration, no credentials involved:

- `/it/literature/de` and `/it/SL202012L/eine-schule-der-kindlichkeit` — 200, and the
  publication's crumb reads `Letteratura / German Schoenstatt Literature / Eine Schule der
  Kindlichkeit`: the interface label translated, the title untouched.
- `/it/SL100458A/schoenstatt-shrine-mont-sion-gikungu` — 200, crumb
  `Santuari / Africa / Santuario di Schoenstatt Mont Sion Gikungu`. The third one is the
  `nameByLocale` change: that name used to render in English *and* be filed as a phrase.

Worth knowing what this does **not** prove: that no new title rows are arriving. Nothing
observable from a rendered page distinguishes a phrase that was filed from one that was not,
which is the whole reason §12 went unnoticed for a day. Confirming it takes one query —

```sql
SELECT COUNT(*) FROM trans_phrases p JOIN sch_publications r ON r.Title = p.phrase
WHERE p.project = 'Schoenstatt' AND p.retired_on IS NULL;
```

which should stay at or near zero, and any growth in it is this defect returning.

### What changes for anyone watching the site

- **A shrine's breadcrumb reads in the visitor's language.** `/it/…/mont-sion-gikungu` says
  *Santuario di Schoenstatt Mont Sion Gikungu* rather than the English name — the label is
  `nameByLocale` now, honouring `IsNameTranslateable` like every other screen.
- **A publication's, composition's and library's breadcrumb stops being translated at all**,
  which is what it looked like before 2026-08-10 anyway. Those labels are the record's own
  title.
- **Breadcrumb labels are HTML-escaped.** They can now be a moderator's free text, and were
  emitted raw.
- **The translator's worklist loses about 3,000 rows**, and any count of "how much is left
  to translate" taken from the v3 API drops with it.

## Done 2026-08-10: JTranslate's phrase-table migrations

Applied to production and deployed. Recorded here rather than deleted, because the
sequence is the template for the next library migration and two of its constraints are
not obvious.

**What ran, in this order:**

1. **JTranslate migrations 001, 003, 004.** Widened `trans_phrases.phrase` and
   `trans_translations.translation` to `TEXT`, added `phrase_hash BINARY(32)` under
   `UNIQUE (project, text_domain, phrase_hash)`, added `retired_on`, converted
   `modified_by` to `INT UNSIGNED`, and merged every duplicate phrase — keeping the
   lowest id and **moving translations across** rather than cascading them away.
   001 was a `CREATE TABLE IF NOT EXISTS` no-op; it appeared pending only because
   production had no `jtranslate_migration` table.
2. **The code deploy.** Never before step 1 — see the ordering note below.
3. `jtranslate:migrate` for **002** (the GUI phrase seed, pure `INSERT`s, ordinary app
   credentials), then `jtranslate:export-catalogs`, then a cache clear.
4. **`database/db7.2.sql`**, removing the blog's phrases, then another catalog rebuild
   and cache clear.

**The numbers.** 7,801 phrase rows → 2,693 (`Schoenstatt` 6,895 → 1,787); 12,957
translation rows → 7,846. The backfill hashed all 7,801 rows; 5,108 duplicate phrases and
5,111 redundant translations were deleted, and **7 translations were repointed** onto
surviving rows — those 7 existed only on duplicates and a dedup-by-deletion would have
destroyed them silently. Total statement time about 1.2 seconds.

**Two constraints worth reusing.**

- **The schema has to move before the code.** The new code selects `phrase_hash` and
  `retired_on`, so against the old schema every request hitting a missing translation,
  plus the admin listing and the v3 API, throws `Unknown column`. Deploying first breaks
  the site.
- **Which means `--pretend` cannot be run on production to get the SQL**, because that
  needs the new code deployed. Take a schema-only dump of the affected tables
  (`mysqldump --no-data`), load it into a scratch database in the capsule, point the
  console at it with a `config/autoload/zz-scratch.local.php` overriding the `db` key
  (`*.local.php` merges after `local.php`, so `zz-` wins), and generate the SQL there.
  Delete that override afterwards. The output of a schema migration depends only on the
  schema, so it is exact.

  A data migration is different: **002 could not be previewed at all** until 003 had
  actually run, because it reads `phrase_hash` to decide what is missing and previewing
  executes nothing. `jtranslate:migrate --pretend` reports that and prints everything
  else rather than aborting, so it is a two-pass procedure by design. 002 was applied
  after the deploy with ordinary credentials instead.

**Migrations are not applied in numeric order.** 002 seeds data and runs last;
`MigrationRunner::MIGRATIONS` is the sequence and the numbers only identify.

**Two phpMyAdmin traps**, both hit during this run. A multi-statement batch containing a
`FROM information_schema.TABLES` query switches phpMyAdmin's tracked "current database"
for every statement after it — so unqualified names then resolve inside
`information_schema` and fail with `#1109`. Schema-qualify every table
(`ourlink_db1.trans_phrases`) and avoid `DATABASE()`. And a `mysqldump` taken without
`--databases` carries no `USE` statement but does carry `DROP TABLE IF EXISTS`, so it
applies to whatever database the client is connected to — never load one without naming
the target database explicitly.

Keep `trans_phrases_blog_backup` / `trans_translations_blog_backup` until the site has
been browsed in all four locales; then drop them. They hold the 39 phrases and 78
translations `db7.2.sql` deleted, including the 39 non-English translations that were
knowingly given up.

## Before the v3 API can be used: one migration and one account per agent

The code shipped 2026-08-09; the phrase endpoints followed. Four things it
deliberately does **not** do for you, because none of them should happen without
someone deciding it:

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

3. **Run `database/db6.8.sql`** on production, before deploying the phrase endpoints.
   It creates `sch_api_translator`, the role `/api/v3/phrases` is gated on, and nothing
   else on the site names it — so until it exists every translation-agent request is a
   401. Idempotent, like db6.6.

   Same cache note as db6.6: a raw `INSERT` leaves `roles-value-options` stale, so run
   `php bin/console cache:flush-persistent` or the role will not appear in the users
   screen's Roles multiselect.

   **Why a second role rather than reusing `sch_api_bot`.** A translation agent can
   rewrite every string the site renders in five languages; a shrine agent can rewrite
   the shrine database. Neither is a reason to be able to do the other, and while
   `BotIdentity` named one role in a constant the question could not even be asked —
   the reach of every credential would have widened silently each time v3 grew an
   endpoint. See [api-v3.md](api-v3.md) and the header of the migration itself.

4. **Create each bot account and grant it the role it needs.** Register the address
   like any other account, then grant the role through the users screen or:

   ```sql
   -- shrines
   INSERT INTO user_role_linker (user_id, role_id)
   SELECT <user_id>, id FROM user_role WHERE role_id = 'sch_api_bot';

   -- translations
   INSERT INTO user_role_linker (user_id, role_id)
   SELECT <user_id>, id FROM user_role WHERE role_id = 'sch_api_translator';
   ```

   One role per agent unless one agent genuinely does both jobs.

   Deleting that row cuts the account off from the API immediately — the role is checked
   on every request, not baked into the token. It is the blunt instrument, though: it
   revokes *every* token the account holds. For one token, use the revoke button on
   `/en/users/:id/api-tokens`.

Tokens are issued from that same screen; see [api-v3.md](api-v3.md) for the whole
flow, including why the button only appears for accounts holding one of the roles named
in `juser.api_token_roles` — which is `sch_api_bot` and `sch_api_translator`, and which
**must be extended whenever a new API role is introduced**, or the account is refused
everywhere and no screen will issue it a token.

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
curl -H 'Cookie: sl_symfony_canary=1' https://schoenstatt.link/api/v3/schema/phrase # 200 JSON
curl -H 'Cookie: sl_symfony_canary=1' https://schoenstatt.link/api/v3/phrases       # 401
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
cookie — reach `App\Kernel` instead of `Laminas\Mvc\Application`. It is what the v3 API was
waiting for.

**Committed 2026-08-10**, above the two cookie overrides in `public/.htaccess`:

```apache
SetEnvIf Request_URI ".*" SYMFONY_KERNEL=1
```

**It is in the repository, not yet in production.** It takes effect on the next deploy,
and the checklist below is what has to be true before that deploy runs — not before the
merge.

Order and directive are both load-bearing, and each failure mode is silent — a default
written *below* an override overwrites it (mod_setenvif takes the last match), and a
`SetEnv` beats every override whatever the order. Both are pinned by
`test/Integration/KernelCanaryTest`, which is why the flip is a commit rather than a
hand-edit on the server.

**The capsule cannot verify any of this.** `docker/apache-vhost.conf` sets
`SYMFONY_KERNEL` with `SetEnv`, and mod_env runs after all of mod_setenvif, so every
kernel line in `.htaccess` — the default and both cookies — is masked locally. Measured:
a request carrying `sl_symfony_canary=0` is served by Symfony in the capsule both with
and without the flip line. Production's vhost has no such `SetEnv`, which is why
`.htaccess` governs there. The behaviour has to be checked against production, through
the "After" list below.

### Before

- [ ] `SMOKE_PROD_CANARY_COOKIE` is wired into the `phploy.ini` smoke hook, and the last
      deploy's run passed. That is the pre-flip evidence: it renders every public ported
      route through the Symfony kernel against production's own data, ICU and
      translations, and checks the three `LaminasResponseConverter` rules on a bridged
      page behind the real TLS proxy — the one thing the capsule cannot reproduce.
- [ ] `database/db6.6.sql` **and `database/db6.8.sql`** are applied, and at least one
      agent account holds the relevant API role (see the v3 prerequisites above).
      Without it every agent request 401s the moment the flip makes v3 reachable —
      db6.6 for `/api/v3/associations`, db6.8 for `/api/v3/phrases`.
- [ ] `database/db6.9.sql` is applied, or two `pt_BR` strings render in English. Unrelated
      to the flip; it is simply the other migration waiting on a deploy.
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
   suspect page against its laminas twin. This is now the escape hatch rather than a
   curiosity, which is why it was deployed ahead of the flip and exercised first.
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
   3. ssh — `php bin/console jtranslate:export-catalogs`. Rebuilds every
      compiled `*.lang.php` from the database.
      - **Why a deploy needs it at all.** The catalogs are gitignored build
        output, so phploy never uploads them; the copies on the server are
        whatever the application last wrote for itself, which happens only
        when a translator saves a phrase or the discovery path finds one that
        already had a translation in another text domain. Nothing else ever
        rebuilt them, which is exactly how they drifted far enough from the
        database to be worth untracking. A deploy that changes phrases, or a
        migration that removes some, leaves them stale until this runs.
      - **It cannot fail the deploy**, by design: the hook swallows the exit
        status and prints a warning instead. A stale catalog only means some
        strings render in English, and the translations themselves are safe in
        the database. Aborting the deploy — or skipping the hooks after it —
        would be the worse outcome, so re-run it by hand if you see the
        warning.
      - It writes as the **ssh** account, not as the web-server user. That is
        safe because each catalog goes to a temporary file and is `rename()`d
        into place, and `rename()` needs write permission on the *directory*,
        not on the existing file — so the web server can still replace a
        catalog this hook created. `TranslationsTable::writeCatalogAtomically()`
        documents why; do not "simplify" that write.
   4. wget `/en/associations/do-work` with an `X-Api-Key` header —
      post-deploy data maintenance. Needs the fully-deployed site. Still a
      wget because the work itself has not been ported to a command yet.
   5. `git tag -f deploy/$(date +%Y%m%d-%H%M)` — local tag recording
      exactly what went live (`git tag -l 'deploy/*'` answers "what's
      deployed?"). Never pushed.
   6. `SMOKE_PROD_CACHE_KEY=<api key> bash tools/smoke-prod.sh` — the
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
php bin/console jtranslate:export-catalogs
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

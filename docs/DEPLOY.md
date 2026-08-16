# Deploying schoenstatt.link

```bash
./tools/deploy.sh
```

That is the whole thing: preflight, build, migrate, warm, swap, verify. It runs
`rsync` over your own shell account on port 222 and needs no PHP locally.

**Deployment is atomic.** A release is built, composer-installed and warmed in a
directory nothing is serving, and goes live when one symlink is replaced by a
single `rename(2)`. There is no window in which a visitor meets a half-deployed
tree — which there was, on every deploy, until 2026-08-15: 17 of the 21 real
exception fingerprints measured on 2026-08-14 were deploy artifacts, arriving in
nine bursts, one per deployed revision (see docs/BACKLOG.md "Deploy ops" for the
evidence, kept because the measurement is what justified the redesign).

**phploy is retired.** It wrote file-by-file over SFTP straight into the live
docroot, which is what made the window unavoidable; nothing about that transport
was atomic and no amount of care in the application prevented it. Its
`--submodules` mode also had a directory-purge bug that recursively deleted
freshly-uploaded trees (it took out `module/JUser/src` on 2026-08-02). The
restricted SFTP account on port 22 that phploy used is now unused by anything.

Configuration lives in **`.deploy.local`** (gitignored, mode 0600), seeded from
`.deploy.local.dist` by `config.sh`. It replaces both `phploy.ini` and
`.phploy`. Nothing in it is ever written to the server.

## Before the next deploy: the sitemap needs a cron entry

The sitemap became a set of static files in the docroot
([docs/sitemap.md](sitemap.md)). Two things have to happen on the server, and
until they do the sitemap works but never refreshes on its own.

1. **Add the cron entry** — in konsoleH, or `crontab -e` on the port-222 shell
   account. The path has to resolve *through* the `public/` symlink so the run
   always lands in whichever release is live — hence `cd -P` and then `cd ..`,
   rather than the tempting `public/..`, which the shell collapses logically
   back to the application directory without ever following the link.

   ```cron
   */15 * * * * cd -P ~/public_html/schoenstatt.link/public && cd .. && php bin/console sitemap:build >/dev/null
   ```

   A run with nothing to do costs ~0.11 s: one `MAX()` over an indexed column,
   then it exits. Only a run that finds new data walks the navigation (~1.2 s).

2. ~~**Mirror the build into `phploy.ini`'s `post-deploy[]`**~~ — **done, and no
   longer a manual step.** `tools/deploy.sh` runs `sitemap:build --force` as part
   of warming, *before* the swap, so a release goes live with a sitemap that
   already matches it. `--force` because a deploy can change which pages exist
   without changing a single database row — the ACL, a route, or the filtering
   rules — and none of that moves `MAX(sch_changes.UpdatedOn)`.

**Delete the old files after the first deploy.** They are outside the docroot,
and no deploy will remove them:

```bash
ssh -p 222 <admin>@dedi2934.your-server.de \
  'cd public_html/schoenstatt.link && rm -rf data/sitemap'
```

**Then resubmit in Search Console.** The advertised URL changed from
`/en/sitemap.xml` to `/sitemap.xml`, and the old one is what Search Console has
on file. The prefixed URL still answers, so nothing breaks if this is forgotten —
it just goes on reporting the old file's errors.

**Expect the index to grow, slowly.** This deploy also stops every non-English
page from declaring the English URL as its canonical, so the four other languages
become indexable for the first time — up to ~28,000 URLs that Google has been
told to ignore. Two things follow:

- **It is gradual.** Google re-crawls and re-evaluates each page's canonical on
  its own schedule; a jump in "Indexed" pages over weeks is the expected shape,
  not days. The `<lastmod>` values now in the sitemap are what should make it
  faster than it otherwise would be.
- **Watch two Search Console reports** rather than the sitemap one. Under Pages,
  "Alternate page with proper canonical tag" should *fall* sharply — that bucket
  was holding the non-English copies. Under Experience → International
  Targeting, hreflang errors should stay at zero; if "no return tags" appears,
  the two layouts have drifted apart and `test/Smoke/CanonicalLinkSmokeTest` is
  the thing that should have caught it.

Spot-check after the deploy that a non-English page points at itself:

```bash
curl -sS https://schoenstatt.link/de/SL100319A | grep -oE '<link[^>]*canonical[^>]*>'
```

It must name a `/de/` URL. If it says `/en/`, the deploy did not take.

Verify, once deployed:

```bash
curl -sSI https://schoenstatt.link/sitemap.xml | grep -iE 'accept-ranges|set-cookie'
curl -sS https://schoenstatt.link/sitemap.xml | grep -o '<loc>[^<]*</loc>'
```

`Accept-Ranges: bytes` with **no** `Set-Cookie` means Apache is serving the file
rather than PHP. Do not look for an `ETag`: this hoster sends none on any static
file, which is what made the first version of this check fail on a deploy that
had worked. Every `<loc>` must be
`https://schoenstatt.link/sitemap-*.xml` — one under a subdirectory again means
Google discards the whole sitemap.

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

## ~~Before the next deploy: two API endpoints start requiring a token~~ — moot 2026-08-14

~~The authorization-bypass fix (2026-08-03) makes `GET /api/v1/libraries/:id` and
`GET /api/v1/libraries/:id/pending-labels` enforce the JWT their route config
always asked for.~~

Both endpoints, and the other 24 in `/api/v1` and `/api/v2`, were **deleted** on
2026-08-14. The deploy note is kept rather than removed because it names the one
workflow that would have noticed — label printing — and the same question applies to
the deletion: nothing has called it since 2022, so nothing should break, but that is
the thing to check if a report arrives. Every former v1/v2 URL now answers a JSON
**410 Gone** with a `Link: </api/v3/schema>; rel="successor-version"` header.

~~**This deploy also removes files, not only changes them.**~~ **Landed — verified
against production 2026-08-14.** `public/api/` (the OpenAPI document and the 2020
Swagger UI bundle, ~6.7 MB) is gone from the server: `/api/v1.yaml` and `/api/v2.yaml`
now reach PHP and answer the 410, which they could not do while Apache was still
serving the files. `tools/smoke-prod.sh` keeps checking those two URLs, because a
future deploy that skips deletions is the same hazard.

**Correction shipped the same day: the successor Link pointed at a 404.** The header
said `</api/v3>`, and `/api/v3` is not a route — it 302s to `/en/api/v3` and lands on
the very 404 handler that emits the 410. So a caller doing exactly the right thing with
an RFC 5829 header arrived nowhere, and every string assertion on the header passed
regardless. It now names `/api/v3/schema`, the public discovery document, and both
`smoke-prod.sh` and `ApplicationSmokeTest` **fetch the advertised target** rather than
pattern-matching the header — the only check that can tell a live successor from a dead
one.

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

3. **The code deploy.** `php phploy.phar` as it then was (phploy was retired 2026-08-16). The `post-deploy[]` hooks already
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

1. **The code deploy.** `php phploy.phar` as it then was (phploy was retired 2026-08-16). The `post-deploy[]` hooks clear the
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
which is the whole reason §12 went unnoticed for a day. Confirming it takes a query, and it
is **statement 2 of `database/db7.5.sql`** — use that rather than writing one, for two
reasons learned by getting both wrong on 2026-08-11:

- **Count phrases, not join rows.** `JOIN sch_publications ON Title = phrase` multiplies one
  phrase by every edition sharing that title — nine of them are called *150 preguntas sobre
  Schoenstatt* — so a naive `COUNT(*)` reported 51 where the answer was about a dozen. Use
  `EXISTS`.
- **Zero is the wrong expectation.** A few live rows are *collisions*: interface strings that
  happen to equal some book's title, on routes that have nothing to do with publications.
  Five survive in the capsule, all pre-2020 and all translated into other languages.
- **Read the route and the text domain, not the translation count.** "Untranslated" is not a
  reliable marker of a filed title: discovery *copies* translations onto a new phrase from
  any row of the same project holding the same text in another domain, so a colliding title
  arrives pre-translated in four languages within seconds. What the breadcrumb determines and
  a translation cannot forge is where the row came from — route `publication` in
  `Application` or `default` is the breadcrumb's own two-step lookup and nothing else. That
  is how the last six rows were identified after db7.4 had spared them; see `db7.6.sql`.

That query found the one instance db7.4 could not, described below.

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

## Done 2026-08-11: `database/db7.5.sql`, one crumb db7.4 could not see

**Deployed and applied 2026-08-11.** Statement 1 reported **0 rows** on production, which was
not the expected outcome and is explained under db7.6 below: it required the crumb's text to
equal some `sch_publications.Title`, and production's row for that book does not carry the
same string. The code fix landed and is verified live — `/it/literature/150-preguntas-sobre-schoenstatt`
renders the crumb untranslated — so nothing further is being filed; only the existing rows
remained, and db7.6 retires them.

Running the standing check above on production the same day turned up **one surviving
instance** of §12, on a route db7.4 had no reason to look at.

`/literature/150-preguntas-sobre-schoenstatt` is Symfony-served, and its breadcrumb does not
come from the navigation at all — `App\Controller\OneFiftyPreguntasController` hands the
trail to the Twig layout, which translates a crumb unless the controller passes
`'translate' => false`. The label is a book's title, so the page filed it in `Books` and in
`default` on 2026-08-10: the day discovery started working on ported routes, i.e. the same
repair that made §12 visible in the first place.

So the lesson db7.4 taught about the laminas partial has an exact counterpart on the Symfony
side, and it is worth stating as a rule: **a crumb whose label is data needs
`'translate' => false`, in both layouts.** Every other ported controller was checked and is
clean — their labels are `Literature`, `Libraries`, `Admin`, `Shrines`, `World`, `Wayside
shrines`, `Music`, and `AssociationEditController` already passes `false` for the record's
name.

Same order as before, and for the same reason — discovery clears `retired_on`, so a render
between the retirement and the fix undoes it:

1. **The code deploy** (the `'translate' => false`).
2. **`database/db7.5.sql`.** Statement 1 retires the rows; statement 2 prints the standing
   check, so the run leaves the current picture on screen.

   ```bash
   ssh -p 222 <admin>@dedi2934.your-server.de \
     'cd public_html/schoenstatt.link && mysql -u<user> -p <db> < database/db7.5.sql'
   ```

3. **Rebuild the catalogs** as in step 3 above.

## Done 2026-08-11: `database/db7.6.sql`, six rows and a corrected guard

**Applied to production 2026-08-11.** Statement 2 retired **6 rows** and the standing check
came back to the three pre-2020 rows alone. Data only, no code change.

The six were three phrases filed twice each under route `publication` in `Application` and
`default`, between 02:41 and 03:19 UTC that morning: §12 rows that db7.4 had spared.

It spared them because its third condition — *nothing has ever translated this beyond
English* — is weaker than it reads. `TranslationsTable::writeMissingPhrasesToDb()` **copies
translations onto a newly discovered phrase** from any row of the same project holding the
same text in another text domain. So a title that collides with an already-translated
string arrives pre-translated in four languages seconds after it is filed, and then looks
exactly like the interface strings the condition exists to protect. The agent's §12 uses the
same test and describes it as exact; it is not, and that is worth knowing before trusting it
again.

**The durable signal is the route and the text domain.** Nothing but the breadcrumb's own
two-step lookup files a phrase under route `publication` into `Application` or `default` —
the page's real interface strings live in `Books` and `Schoenstatt` — and a translation
cannot forge either field.

db7.6 also retires the crumb db7.5 reported **0 rows** for: db7.5 required the text to equal
some `sch_publications.Title`, but that label is a literal in the controller and production's
row for the book carries different text, so the title test was the wrong test for it.

Statement 1 prints what statements 2 and 3 will retire, and statement 4 prints the standing
check afterwards, so one run leaves both the evidence and the current picture on screen.

### What the six turned out to be, and why the remaining three must stay

The three phrases were `Santuario del Padre`, `Schoenstatt` and `Heiligtum der Berufung`,
each carrying translations in **all five languages**. The rows they inherited those from are
the three the check still shows, and they are not "interface strings that collide with a
title" as this file previously guessed. They are **shrine names**:

| id | phrase | de | en | es |
|---|---|---|---|---|
| 6785 | `Santuario del Padre` | Heiligtum des Vaters | Santuario del Padre | Santuario del Padre |
| 6815 | `Heiligtum der Berufung` | Heiligtum der Berufung | Vocation Shrine | Santuario de la Vocación |

Their text domain is `Schoenstatt`, which is `SchoenstattTable::TRANSLATOR_DOMAIN` — the
domain the association-name feature translates in (see the reply's §6). Their
`origin_route` of `publications/publication` only records where the string was *first seen*
in 2019, on a book named after the shrine.

So **do not retire the rows the check keeps showing.** They are the §6 feature, and the
steady state of this check is "the shrine names that double as book titles", plus
`Schoenstatt` on `libraries/library`. What is *not* that steady state is a row under route
`publication` in `Application` or `default`.

It also tells us what the site did before the fix, which nobody had noticed: a publication
whose title equalled a shrine's name got the **shrine's translation** as its breadcrumb,
because the lookup hit. On an Italian page the book `Heiligtum der Berufung` was labelled
*Santuario della Vocazione* — a confidently wrong title, which is §12's own argument for why
titles must not pass through `translate()` at all.

### The ported crumb: resolved, and it was already done

Statement 3 reported **0 rows**, as db7.5's had, and two explanations offered here for that
zero were both wrong — first that production's title text differs, then that the page had
never been rendered with a miss. The row's own `retired_on` settled it:

```
translation_phrase_id  text_domain  origin_route  added_on             retired_on
13417                  Application  publication   2026-08-11 01:13:10  2026-08-11 09:17:31
13418                  default      publication   2026-08-11 01:13:10  2026-08-11 09:17:31
```

Its `origin_route` is **`publication`**, not the ported page's route. Nine editions in
`sch_publications` are titled *150 preguntas sobre Schoenstatt*, so an ordinary publication
show page filed the text hours earlier, and **db7.4 retired it at 09:17** — twenty minutes
before db7.5 went looking. The ported page has never filed a phrase on production at all; the
capsule's rows came from rendering it locally.

Nothing is outstanding, and the code fix still earns its place: it is what stops that page
filing one the first time somebody visits it in a language with a gap.

The reusable part is the trap. **`origin_route` records where a phrase was first *seen*, and
that is frequently not the page you are reasoning about** — the same string reached the table
by a route nobody was looking at, and both wrong explanations came from assuming the route
would name the page whose bug it was. When a targeted search comes back empty, look the row up
by *text* alone before theorising; `retired_on` and `origin_route` then answer the question
directly.

## Done 2026-08-11: `database/db7.7.sql`, the §2/§3 residue

**Applied to production 2026-08-11: 18 rows retired**, and the closing check left exactly the
two live literals — `Error in form submission, please review.` in `JTranslate` and in
`Application`. Data only, no code change, no ordering constraint: the code fixes it depends on
had been live since 2026-08-10.

Reported from the translation GUI on 2026-08-11: rows like `Error in form submission, please
review: security, mainShowDisplay, viewRole, checkoutPersonListKind` on the library routes,
with the reasonable question of whether the code was still broken. It is not. Every call site
now passes either a fixed literal — `'Error in form submission, please review.'`, with a full
stop where the old rows have a colon and a field list — or a `TranslatableMessage` whose
template is what reaches `translate()`. What the fixes could not do is remove the rows already
written, and nobody had.

28 rows in the capsule, none of them translatable and none of them reachable (nothing
translates a finished message any more, so the interpolated form can never be a lookup key
again):

| shape | rows | routes |
|---|---|---|
| `Error in form submission, please review: <fields>` | 6 | `libraries/create`, `libraries/library/checkout`, `publications/create`, `associations/create` |
| `The following book id's are invalid: <ids> Please try again.` | 10 | `libraries/library/checkout` |
| `File not imported due to duplicate withinLibraryIds: <ids>.` | 1 | `library-imports/library-import/edit` |
| `Assignment Id: <n>` | 4 | `sion-model/view-changes` |
| `'<host> ' is not a valid hostname for the email address` | 6 | `zfcuser/register` |

The last group is §2's privacy smell — six strangers' mistyped mail domains. Only a domain
name, no local part, so retiring is enough; if they should cease to exist, that is a `DELETE`
and a decision, not this file.

**Production had neither of the last two groups.** It retired 18 rows where the capsule
retired 28: the six hostnames and the four `Assignment Id:` rows are capsule-only, and the six
hostnames are the same six the change-request report named — `gmail.com`, `hotmail.com`,
`miuandes.cl`, `uc.cl`, `yahoo.com.br`, `yahoo.de`. So that report was reading the capsule for
§2, exactly as it was for §1's four JWTs, and **no live registration data was ever in the
production phrase table.** Worth knowing before anyone treats §2 as a production privacy
incident; the code defect behind it was real either way.

`Assignment Id:` has **no call site left in any module**: that code is gone, so those four are
residue of a removed feature rather than of a fixed one.

Every statement excludes phrases containing a literal `%`, which is the template guard — it is
what keeps the live `'…withinLibraryIds: %s.'` out of a pattern that would otherwise match it.
Statement 1 prints what will be retired and statement 3 prints what remains of the same
shapes, which should be the fixed literal and nothing carrying a value.

```bash
ssh -p 222 <admin>@dedi2934.your-server.de \
  'cd public_html/schoenstatt.link && mysql -u<user> -p <db> < database/db7.7.sql'
```

Then rebuild the catalogs, as after any phrase change.

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

To have the post-deploy smoke run exercise it, set it in `.deploy.local`:

```sh
DEPLOY_CANARY_COOKIE=sl_symfony_canary=1
```

`tools/deploy.sh` passes it through as `SMOKE_PROD_CANARY_COOKIE`.

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
link on `/en/admin` — it offers whichever kernel you are *not* currently on, and says which
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

- [ ] `DEPLOY_CANARY_COOKIE` is set in `.deploy.local`, and the last
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

1. **One person, no deploy.** Set `sl_symfony_canary=0` — the **Switch kernel** link on
   `/en/admin` does it — and that visitor is back on laminas immediately. Enough to compare a
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

## Database migrations

`tools/deploy.sh` applies them; `tools/migrate.sh` is the same runner standalone.

```bash
./tools/deploy.sh --migrations                    # applied vs pending
./tools/migrate.sh plan                           # just the pending, with headers
./tools/migrate.sh apply --phase=post --env=capsule   # rehearse before production
```

**Every `database/*.sql` is tracked in a `sch_migration` ledger** — filename,
sha256, phase, kind, when, by what, how long, and the rows each statement
affected. Before this existed, "what is applied?" was answered by reading prose
across four documents, and `db7.8`/`db7.9` could not be answered at all.

### Headers a new migration must carry

```sql
-- @phase: post
-- @kind: dml
-- @tables: trans_phrases
-- @verify: SELECT id FROM t WHERE <still wrong>
```

| header | required | meaning |
|---|---|---|
| `@phase` | yes | `pre` runs before the swap, `post` after. **No default** |
| `@kind` | yes | `dml` is wrapped in a transaction; `ddl` cannot be |
| `@tables` | yes | dumped to `data/deploy/backups/` before it runs; `none` is allowed and is a claim |
| `@idempotent` | `ddl` only | `yes` — your assertion that re-running is a no-op |
| `@verify` | no | must return **zero rows** afterwards, or the migration fails |

**`@phase` has no default because both orders are real here and guessing wrong
is silent.** JTranslate 003/004 were `pre`: the new code selects columns the old
schema lacks, so deploying first breaks the site. Every `db7.x` retirement is
`post`: run it before the code fix and the next page view re-files the rows and
clears `retired_on`, because phrase discovery un-retires whatever the site still
looks up.

### What makes a migration safe, in the order the guarantees matter

1. **For `dml`, the ledger row commits inside the same transaction as the
   change.** Applied-but-unrecorded and recorded-but-unapplied are both
   impossible, and a failure part-way rolls the whole file back. Every table
   this application touches has been InnoDB since `db7.0`/`db7.1`.
2. **`ddl` cannot have that.** MariaDB commits implicitly on DDL, so `ddl`
   migrations must declare `@idempotent: yes`. That is a forcing function, not
   a proof — write the guards.
3. **`@tables` are dumped before anything runs**, to `data/deploy/backups/` on
   your machine (gitignored — it is production data).
4. **`mysql` aborts on the first error**; `--force` is never used.
5. **Row counts are recorded per statement**, so the numbers this document used
   to carry by hand are recorded by the thing that did the work.
6. **`@verify` must return zero rows.** Write it as "select what is still
   wrong". A migration that runs but does not achieve what it claimed fails.
7. **An applied migration whose bytes changed aborts the deploy.** Editing one
   makes the ledger a liar: the same filename then means two different things
   across environments.

None of that says a migration is *correct*. Rehearse against the capsule, whose
data is days old and representative, and read the counts.

### Refusals, all verified against throwaway migrations

| situation | what happens |
|---|---|
| a statement fails half way through a `dml` file | transaction rolls back; **no** ledger row; earlier statements in the same file undone |
| an applied file's bytes changed | abort before anything runs |
| `@verify` still returns rows | fail, naming them; the change stays applied |
| no `@phase` | refuse, with the pre-vs-post explanation |
| `dml` containing DDL | refuse — the implicit commit would break the wrapper |
| `ddl` without `@idempotent: yes` | refuse |

### Credentials

Full-DDL credentials live in `.deploy.local` on the developer machine and are
used through an **SSH tunnel** to the server's own `127.0.0.1:3306`. They are
never written to the server, never passed in `argv` (where `ps` would expose
them on a shared host) — a `--defaults-extra-file` at mode 600 carries them —
and a compromise of the web application cannot reach a password it has never
seen. The account must be granted for `127.0.0.1`, because through a tunnel that
is where the connection appears to come from.

### The backfill, and the capsule

The 75 files that predate the ledger are recorded as applied **without being
run**:

```bash
./tools/migrate.sh backfill --through=db7.7
```

Everything after `--through` stays pending and *will be run* by the next apply.
`db7.8` and `db7.9` were deliberately left pending rather than backfilled,
because both are idempotent and running them settles the question of whether
they were ever applied to production — a question no document could answer. If
they were, every statement reports 0 rows and nothing changes.

`db7.9` had one unguarded statement, an `INSERT` into `sch_changes`; the capsule
had accumulated **three** copies of that audit row before anyone looked. It is
now guarded, which matters more than a stray duplicate normally would:
`sch_changes` is the only accurate modification time this application keeps, and
the sitemap reads it for `<lastmod>`.

Once production has the ledger, a fresh `database/dumps/` export carries it, and
the capsule stops drifting.

## The deploy

```bash
./tools/deploy.sh
```

It pulls master itself, so there is nothing to do first. The run is nine steps;
each prints, and any failure before the swap leaves production untouched.

| # | step | where | notes |
|---|---|---|---|
| 1 | **Preflight** | local | on `master`; working tree clean; `pull --ff-only`; the three submodules clean *and pushed*; local master not ahead of origin |
| 2 | **Verification** | local | `tools/ci-local.sh --ci` — lint, `composer --no-dev` rehearsal, PHPStan 0, unit, integration. `--skip-tests` to skip |
| 3 | **Build** | server | `rsync` into `releases/<ts>-<sha>/`, hardlinked against the previous release; `.revision` written |
| 4 | **Link shared** | server | `shared/data`, `shared/public`, `shared/config-autoload` symlinked in; `data/config` and `data/cache` created empty, per-release |
| 5 | **composer install** | server | `--no-dev --optimize-autoloader`, vendor seeded from the previous release |
| 6 | **Pre-migrations** | local→tunnel | `@phase: pre` against the live database while the OLD code still serves; a failure aborts before the swap |
| 7 | **Warm** | server | `jtranslate:export-catalogs`, merged-config cache, `sitemap:build --force` — in a tree nothing is serving |
| 8 | **Swap** | server | `ln -sfn` + `mv -Tf`: one `rename(2)` |
| 9 | **Post-swap** | server / local | `cache:flush-persistent` (APCu), `/en/associations/do-work`, then `@phase: post` migrations |
| 10 | **Verify** | local | `tools/smoke-prod.sh`; **a failure rolls back automatically** and exits non-zero |

Then it tags `deploy/<ts>` locally (never pushed — `git tag -l 'deploy/*'`
answers "what shipped?") and prunes to `DEPLOY_KEEP_RELEASES`, never removing
the live release or the one a rollback would reach for.

Three properties are worth stating because each replaces a hazard that used to
be real:

- **The release is exactly the committed tree.** The transfer list comes from
  `git ls-files --recurse-submodules`, not from the working directory, so local
  cruft — a stale `public/sitemap.xml`, a scratch dump, an editor backup —
  cannot reach production, and the submodules are ordinary directories in that
  list rather than a separate rsync pass. `tools/deploy-submodules.sh` was deleted
  with phploy on 2026-08-16; its clean-tree guard moved into
  preflight and gained the two checks it never had — that each submodule sits at
  the commit the superproject pins, and that the commit is actually pushed.
  The first of those is not pedantry: the release is built from the submodule
  *working trees*, so a submodule at a different commit ships code no commit
  describes.
- **Warming happens before the swap.** `jtranslate:export-catalogs` and
  `sitemap:build` used to run *after* the code went live, so every deploy had a
  window in which strings rendered in English and the sitemap belonged to the
  previous release. They now run against a tree no request can reach.
- **`data/config` is per-release.** A new tree can no longer meet a merged-config
  cache built from the old one — the pairing behind the `A plugin by the name
  "requestUri" was not found` burst on every deploy, and behind the
  `addRuleProvider(): … EventTextTable given` one. The `servingNote` guard in
  `layout.phtml` is no longer load-bearing, though it costs nothing and stays.

### What is not automatic

- The APCu flush **must** stay an HTTP request. An APCu segment belongs to the
  SAPI that created it, so a CLI `apcu_clear_cache()` flushes a segment nobody
  reads — measured 2026-08-04, all 21 web-segment entries survived it.
  `cache:flush-persistent` makes the request for us.
- `/en/associations/do-work` is still a URL rather than a command
  (`autoFillTimeZones()`, `updateAssociationMd5s()`); porting it is in
  docs/BACKLOG.md.
- A sign-in round trip with a real email.

## The layout on the server

```
public_html/schoenstatt.link/
  public -> releases/20260815-2207-956aabd/public   the swap; the only symlink
  releases/<ts>-<sha>/                              five kept; vendor hardlinked
  shared/data/{logs,exceptions,htaccess-backups,fonts,musicas,texts,scans,import}
  shared/public/{covers,associations,dh, …server-only docroot files}
  shared/config-autoload/{local.php,*.local.php}
```

`public/index.php` resolves `__DIR__/../vendor` through the symlink into **its
own** release, so replacing that one link swaps the entire tree — code, vendor,
config and compiled catalogs together. A request in flight when the swap happens
keeps serving from the old release directory, which still exists; that is why
old releases are pruned by count and not immediately.

**Shared versus per-release is a real distinction, not a tidiness one.**

| shared | per-release |
|---|---|
| `data/logs`, `data/exceptions`, `data/htaccess-backups` | `vendor/` |
| `data/fonts`, `data/musicas`, `data/texts`, `data/scans`, `data/import` | `data/config` (merged config + module map) |
| `public/covers`, `public/associations`, `public/dh` — 1.2 GB of uploads | `data/cache/twig` |
| `config/autoload/local.php` and `*.local.php` | the compiled `*.lang.php` catalogs |
| server-only docroot files (below) | `public/sitemap*.xml` |

Two traps here, both silent:

- **`data/publications` is tracked repo content**, not state — the
  150-preguntas source. Listing it as shared would not error: `ln -sfn` onto an
  existing directory creates the link *inside* it, so the release would get
  `data/publications/publications` and go on using the real directory.
  `tools/deploy.sh` refuses a shared entry that collides with tracked content
  rather than linking it.
- **`*.local.php` does not match `local.php`.** laminas' own autoload glob is
  `{,*.}local.php`, and the file holding the database credentials is the one
  without a prefix. A single-pattern loop skips it in silence and the swap takes
  the site down. Both scripts use both patterns.

### Server-only files in the docroot

phploy left unknown files in `public/` alone. **A release swap deletes them**,
because the new docroot is only what is in git. As of 2026-08-15 the server held
six such files:

| file | disposition |
|---|---|
| `BingSiteAuth.xml` | `shared/public/` — losing it de-verifies Bing Webmaster Tools |
| `google0e1110cae0fbf177.html` | `shared/public/` — same for Search Console |
| `pi-462149043bd9.php`, `pi-e0d4d959748e.php` | **inspect before deciding.** Two PHP files with random-hex names in the docroot is also what a webshell looks like |
| `test.png`, `Y0wb7nkgZN5J9YM0n.jpg`, `eDBH8Kmf8DL8` | junk; let the swap remove them |

Anything dropped into `shared/public/` is symlinked into every future release
automatically, so keeping a new one takes no code change. `tools/deploy-bootstrap.sh
--check` lists what is *not* claimed, which is exactly what the next swap would
delete; read that list before the first swap and after any manual server work.

## Cutover — converting the server to the release layout

One time, ever. Staged so each step is independently verifiable, and so the
risky part is last and reversible.

```bash
./tools/deploy-bootstrap.sh --check    # report only; nothing is modified
```

Read the "server-only docroot files NOT claimed" list. Add anything worth
keeping to `SHARED_PUBLIC` at the top of the script and re-run `--check` until
the list holds only things you are content to lose. Then:

```bash
./tools/deploy-bootstrap.sh --yes
```

This moves shared state into `shared/` and symlinks it back where it was. **The
site is still served from the flat tree and should behave exactly as before** —
verify that now, because every move is visible if it went wrong:

```bash
curl -sI https://schoenstatt.link/ | head -1
curl -s -o /dev/null -w '%{http_code}\n' https://schoenstatt.link/covers/
```

Open an association page and confirm its images load. Then:

Before swapping, **inventory anything scheduled**, because a cron entry that
`cd`s into the application directory will keep running the *old* flat tree after
the swap — see below:

```bash
ssh -p 222 ourlink@dedi2934.your-server.de 'crontab -l'
```

Also check konsoleH's own scheduler; entries added there do not appear in
`crontab -l`. Then:

```bash
./tools/deploy.sh --bootstrap
```

which builds the first release and performs the initial swap. That swap is a
`mv` plus a `ln`, not a `rename(2)` — a real directory cannot be atomically
replaced by a symlink — so it has a sub-second window. It is the only one, and
it never recurs. The old docroot is kept as `public.pre-atomic`; the way back is

```bash
cd ~/public_html/schoenstatt.link && rm public && mv public.pre-atomic public
```

### After the first swap: two things the swap does not do

**1. Re-point every scheduled job.** Only `public/` became a symlink. The
application directory still holds a complete copy of the old flat tree — `bin/`,
`config/`, `module/`, `src/`, `vendor/` — so anything that does
`cd ~/public_html/schoenstatt.link && php bin/console …` still runs, and runs the
**old code**, while writing through `public/` into the *live* release. That is a
worse failure than an outright break, because it works. The sitemap cron is the
known one; re-point it as in "Before the next deploy: the sitemap needs a cron
entry" above, using the `cd -P` form.

**2. Remove the old flat tree, once a deploy has succeeded.** Until it is gone
every path that used to work still works, against stale code. Preview first —
the four names to keep are the only four that matter:

```bash
ssh -p 222 ourlink@dedi2934.your-server.de \
  'cd public_html/schoenstatt.link && ls -A | grep -vE "^(releases|shared|public|public\.pre-atomic)$"'
```

That list is what the old layout left behind. Delete it only after
`./tools/deploy.sh` has run cleanly at least once and `public.pre-atomic` is no
longer wanted as an escape hatch, and delete it by name rather than with a
wildcard — `releases/` and `shared/` live in the same directory, and losing
`shared/` means losing 1.2 GB of uploaded media and every `*.local.php`.

**Why this works on this hoster, verified 2026-08-15.** Apache follows the
symlinked docroot *and* honours `.htaccess` at the symlink target — probed with
a symlink pointing outside the docroot, carrying an `.htaccess` that set a
header, and the header came back. That is the whole feasibility question: had
`<Directory>` been scoped to the literal docroot path, `.htaccess` would have
stopped applying, mod_rewrite with it, and nothing in the response would have
said so. The shell account is `ourlink:ourlink` (uid 1023) — **the same user web
PHP runs as** — so a release tree it writes is writable by the web server and
nothing needs to chmod anything.

## Running the server-side steps by hand

If a step fails mid-run, the release directory is still there and finishing it
by hand is safe — nothing is live until the swap. In an SSH session on port 222:

```bash
cd ~/public_html/schoenstatt.link/releases/<the-release>
php composer.phar install --no-dev --no-interaction --optimize-autoloader
php bin/console jtranslate:export-catalogs
php bin/console sitemap:build --force
cd ~/public_html/schoenstatt.link && ln -sfn releases/<the-release>/public public.swap && mv -Tf public.swap public
cd releases/<the-release> && php bin/console cache:flush-persistent
```

then run `bash tools/smoke-prod.sh` locally. `bin/console list` shows everything
available. `cache:flush-persistent` takes `--url` when the configured
`sion_model.canonical_base_url` is not the host you mean, and reads the key from
`SCH_MAINTENANCE_KEY` when the local config has none — prefer that over `--key`,
which lands in shell history.

`cache:clear-config` is no longer part of a deploy: each release starts with an
empty `data/config`, so there is nothing stale to clear. It remains useful after
editing config *on* the server.

## Server facts worth remembering

- SSH port **222** is the shell account and the only identity a deploy uses.
  Port **22 is a restricted SFTP jail** (no exec — rsync/scp fail with "exec
  request failed on channel 0"); it existed for phploy and nothing uses it now.
  The two-identity split was there so a phploy compromise could move files but
  never run anything; with phploy gone the threat it addressed is gone too, and
  every server-side step already ran under the shell account anyway.
- **The shell account is the web-server user** (`ourlink`, uid 1023, verified
  2026-08-15). An earlier note here said catalog writes happen "as the ssh
  account, not as the web-server user" — that was about the *SFTP deploy*
  account. `TranslationsTable::writeCatalogAtomically()` writes to a temp file
  and `rename()`s it anyway, which is correct for other reasons; do not
  "simplify" it.
- **No local PHP is needed.** phploy required `php8.0` because newer CLIs here
  lack mbstring; `tools/deploy.sh` uses only git, rsync, ssh and curl. `php8.0`
  is still needed for the migration runner, which uses pdo_mysql.
- Web PHP is FastCGI with a per-account php.ini at
  `/home/httpd/php85-ini/ourlink/php.ini` (**per PHP version**: the 8.4-era file
  was under `php84-ini/`, the 8.3-era one under `php83-ini/`, the 7.4-era one
  under `php74-ini/`). This is the trap when flipping the konsoleH PHP version:
  the new version reads a *different* file, so anything tuned in the old one
  silently reverts to defaults — copy it across as part of the flip. php.ini
  changes are the ONE case that needs `pkill -u ourlink -f php` (workers re-read
  ini on respawn); deploys never do.
- Disk: 269 GB free as of 2026-08-15. A release costs ~36 MB of code plus
  ~128 MB of vendor, and unchanged files hardlink to the previous release, so
  five releases cost far less than five times that.

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
  segment is ≥80% full or has ever expunged. The `apc.shm_size` raise has since
  landed (32M → **256M**, verified 2026-08-11), so a saturation warning now
  means something genuinely new rather than the old default filling up — treat
  one as a real signal, not as the known condition it used to be. (First live
  reading 2026-08-03, before the raise: 2 expunges within hours of the PHP 8.3
  flip.)
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

```bash
./tools/deploy.sh --rollback              # the previous release
./tools/deploy.sh --rollback --to <rel>   # a specific one
./tools/deploy.sh --releases              # what is on the server, and what is live
```

One symlink, one `rename(2)`, then an APCu flush and a smoke run. It reinstates
the previous release's code, `vendor/`, compiled catalogs and `.htaccess`
together, because all four live inside the release directory — the by-hand
checklist this section used to hold (re-check out the older superproject commit
so the submodule pointers roll back too, re-sync the submodule trees, re-run
`composer install --no-dev` to reinstate the older lock) is no longer needed.

A failed smoke run **rolls back on its own**; the failed release is kept for
inspection rather than pruned.

Three things it does not do:

- **It does not undo a database migration.** Code and schema roll back
  independently, which is why migration phase matters: a `pre` migration is
  designed to be compatible with the code that was live before it, so a rollback
  of the code alone is safe. The table snapshots the runner takes before each
  migration are in `data/deploy/backups/`.
- **It does not survive the next deploy** if what you actually want is to undo a
  commit. Revert it and deploy; that is the durable form.
- **It cannot reach a pruned release.** `DEPLOY_KEEP_RELEASES` is five, and the
  live release and its predecessor are never pruned regardless.

A clobbered `.htaccess` can still be restored from
`shared/data/htaccess-backups/` — the deploy copies the live one there before
every release goes out, which matters because the file is tracked and therefore
replaced by each swap.

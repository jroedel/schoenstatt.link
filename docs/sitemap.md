# The sitemap

`https://schoenstatt.link/sitemap.xml` is a **static file**, written by
`bin/console sitemap:build` and served by Apache. PHP is not in the request path.
Every rule here failed silently at least once; the checks at the bottom are how
you find out whether you have re-broken one.

## The files

| URL | contents |
| --- | --- |
| `/sitemap.xml` | the index; the only URL `robots.txt` and Search Console need |
| `/sitemap-pages.xml` | static pages (26), no `<lastmod>` |
| `/sitemap-associations.xml` | shrines and wayside shrines (~250) |
| `/sitemap-publications.xml` | ~6,500 records, ~24 MB — the big one |
| `/sitemap-compositions.xml` | ~335 |

Each record is published under all five locale prefixes with the whole set as
`xhtml:link` alternates, so a file's `<url>` count is five times its record count
(~35,400 `<loc>` entries in total). A section that outgrows 45,000 URLs or 45 MB
splits into `-2`, `-3` parts automatically; publications is the closest, at about
two thirds of the 50,000 the protocol allows.

## Rule 1: every file sits at the docroot root

The protocol ([sitemaps.org](https://www.sitemaps.org/protocol.html)): a sitemap
may only list URLs **at or below its own directory**, and the files an index names
must be in the index's directory or lower. Every URL here begins `/en/`, `/es/`,
`/de/`, `/pt/` or `/it/`, so a sitemap served from `/sitemap/` or `/en/` puts
every URL out of scope and Google discards the whole file with "URL not allowed" —
nothing in the response shows it.

So the files live at `/`, `public/robots.txt` names `/sitemap.xml`, and
`SitemapSmokeTest::testEverySitemapSitsAtTheDocrootRoot()` fails if either moves.
Every `<loc>` in the index must be `https://schoenstatt.link/sitemap-*.xml`.

## Rule 2: what is excluded, and the three access rules that decide it

| dropped | why |
| --- | --- |
| associations outside `AssociationController::PUBLIC_KINDS` | the controller 302s a guest to the home page — a soft-404 |
| guarded routes (`/admin`, `/movement`, `/libraries`, …) | 302 to the sign-in page |
| library 5 | `ViewRole = lib_user`; per-record ACL on the same route as the public ones |
| `#fragment` URLs | Google drops the fragment, so they duplicate listed pages |
| merged publications | their URL is a 301 to the surviving edition (~3,600 of ~10,100) |

Three separate rules decide publication, and only one is a route guard:

- **`GuestAccess::isRoutePublic()`** asks the ACL whether `guest` may reach
  `route/<name>`. Catches any guarded route added later, for free.
- **The association rule is not in the ACL.** `route/association` admits `guest`;
  `App\Controller\AssociationController` then redirects anonymous visitors away
  from every kind outside `PUBLIC_KINDS`. `tools/acl-table.php` cannot see it, so
  `GuestAccess` reads the constant directly and the two cannot drift.
- **The library rule is in the ACL but not on a route.** `LibraryTable::getRules()`
  makes each row a `library_<id>` resource whose `show` is granted to its
  `ViewRole`; `/libraries/1` and `/libraries/5` share one route and differ anyway.

Every filter **fails open**: an ACL that cannot be built, a resource it has never
heard of, a table read that throws — all publish the page. An over-broad sitemap
is recoverable; an empty one is a Google error in its own right.

Fail-open is only safe if something notices, and for merged publications nothing
else would (the file stays well-formed, Search Console reports "Page with
redirect" weeks later). `SitemapSmokeTest::testMergedPublicationsAreExcluded()`
therefore asserts **set equality against the database**: the published ids are
exactly the `publication_public` rows with `MergedIntoPublicationId IS NULL`, in
both directions. The set is not static — `copyPublicationToMainCorpus()` marks its
source row merged, so every "Copy into main corpus" mints a new redirect.
`PublicationsTable::getMergedPublicationIds()` restricts itself to
`ResourceId = 'publication_public'` on purpose: `getPublicationNavigationData()`
filters on the same value, so no other resource id reaches the sitemap.

## Rule 3: `<lastmod>` comes from `sch_changes`, never from `UpdatedOn`

The entities' `*UpdatedOn` columns are **not maintained** (a record edited three
times can still carry a 2019 stamp), so a `<lastmod>` built from them tells Google
the page has not changed in years. `sch_changes` is the record that is kept:
`SionTable::reportChange()` writes a row per changed field on every create,
update and delete, in **UTC**, which is why `SitemapWriter` renders `+00:00`. It
covers every record the sitemap publishes. A record with no change row gets no
`<lastmod>`, which is valid; static pages get none either, because stamping them
with the build time would claim all 26 change on every build.

## Rebuilding

Freshness is a fact about the data: `SitemapGenerator::isStale()` compares
`MAX(sch_changes.UpdatedOn)` against the mtime of `public/sitemap.xml`. So there
is **no hook in the write path** — editing a record makes the sitemap stale by
definition — and it could not be a cache entry: an APCu segment belongs to the
SAPI that created it, so a console process cannot flag anything to the web server.
A database column and a file mtime are the only clocks both SAPIs share.

```cron
*/15 * * * * cd -P ~/public_html/schoenstatt.link/public && cd .. && php bin/console sitemap:build >/dev/null
```

`cd -P` through the `public/` symlink is what lands the run in the **live
release**; `public/..` collapses logically and never follows the link. A no-op run
costs ~0.1 s (one `MAX()` over an indexed column); a rebuild ~1.2 s. `--force`
skips the check — `tools/deploy.sh` uses it while warming a release, because a
deploy can change which pages exist (ACL, routes, filters) without moving a single
database row. `--url` overrides the base URL and is what the smoke tests use. The
base URL otherwise comes from `sion_model.canonical_base_url`
(`config/autoload/sionmodel.global.php`), not from the request: a sitemap lists
canonical URLs, and whichever hostname reached the server is not necessarily the
published one.

**The route decides the file, not the page id** (`SitemapSection::forPage()`). A
route name is a route definition and cannot fall out of a cached navigation
branch; the id is needed only for `<lastmod>` and the per-locale slug, so losing
it degrades those to absent rather than filing the record in the wrong section and
having `SitemapWriter::removeOrphans()` delete a file a crawler knows about.
`SitemapWriterTest::testTheRouteDecidesTheSectionEvenWithNoId()` pins it. Adding a
field to a `PageBuilder` branch still needs `cache:flush-persistent`, because the
console and the web server each cache their own copy of the navigation — the
deploy does this; the capsule needs it by hand.

### The missing-file fallback

`App\Controller\SitemapController` answers `/sitemap.xml` **only when the file is
missing** (first deploy, or someone deleted it). It builds the files, and every
request after is Apache's. It deliberately does not check staleness: that would
put a 0.6 s navigation walk inside an arbitrary crawler request and repeat it
until something wrote the file.

Tell the two apart from the response: Apache sends `Accept-Ranges: bytes` and no
cookies; the PHP path cannot answer without a session, so it carries `Set-Cookie`,
`Pragma: no-cache` and a `no-store` `Cache-Control`, and no `Accept-Ranges`.
**Not by `ETag`**: the hoster sends none on any static file while the capsule's
Apache does, so an ETag check passes locally and fails against production.
Anything asserted about these responses must be verified against production.

## Rule 4: all five languages are indexed, and that is a two-place contract

A page whose canonical names another language's URL is telling Google "do not
index me, index that one"; an hreflang set that omits its own page is
non-reciprocal and Google discards the cluster. So, in `templates/layout.html.twig`:

- each language is **canonical for itself**;
- the hreflang set lists **all five plus `x-default`**, its own page included;
- `x-default` names the English URL;
- hreflang is **unconditional** — declared whether or not a translation exists,
  because the set must match what the pages publish and fewer breaks reciprocity.

`test/Smoke/CanonicalLinkSmokeTest` asserts all of it.

### The canonical is the *preferred* URL, because the slug selects nothing

`sw_id` selects a record; the slug does not, and nothing redirects between forms,
so `/en/SL100319A`, `/en/SL100319A/original-schoenstatt-shrine` and
`/en/SL100319A/anything` all answer 200 with the same page. A canonical that
echoed the request would make each an indexable page. Associations sharpen it:
their slug is stored **per locale** (`SlugEn`, `SlugEs`, `SlugDe`, `SlugPt`,
`SlugIt`, and most German slugs differ from the English one); publications and
compositions have one `Slug`, so their five URLs differ only in the prefix.

`App\View\PreferredUrls` answers "the one URL this record should be indexed
under, per language", assembled through the router. The record controllers hand
it to the layout as `locale_paths`; a page that sets none gets the current path
with each prefix swapped in, which is right for a static page.

**The sitemap uses the same builder**, via `SitemapEntry::tailFor()`. The
navigation tree is built for one locale, so without it the sitemap would advertise
`/de/SL100319A/original-schoenstatt-shrine` while the German page calls
`urheiligtum` canonical — a wasted crawl and "Alternate page with proper canonical
tag" on ~1,240 entries. `SitemapSmokeTest::testSitemapUrlsAreTheCanonicalOnes()`
samples the two against each other; nothing else would notice them drifting.

The contract is therefore two places — the Twig layout, and
`PreferredUrls` as consumed by the layout *and* the sitemap. Changing one without
the other silently de-indexes languages or advertises non-canonical URLs.

## Checking it

```bash
docker compose exec -T app php bin/console sitemap:build --url http://localhost:8080 --force
php composer.phar smoke -- --filter SitemapSmokeTest      # 10 tests, ~72k assertions
php composer.phar smoke -- --filter CanonicalLinkSmokeTest
php composer.phar unit  -- --filter SitemapWriterTest      # writer + section classification
```

After a deploy, confirm production is serving statically and in scope:

```bash
curl -sSI https://schoenstatt.link/sitemap.xml | grep -iE 'accept-ranges|set-cookie'
curl -sS  https://schoenstatt.link/sitemap.xml | grep -o '<loc>[^<]*</loc>'
```

`Accept-Ranges: bytes` and **no** `Set-Cookie` means Apache is serving the file.
If any `<loc>` in the index ever appears under a subdirectory, the sitemap is void.

`test/Integration/NavigationRouteParametersTest` fails if any navigation branch
declares an empty route parameter — a page whose href 404s is refused where it is
built, rather than caught by the sitemap's trailing-slash rule.

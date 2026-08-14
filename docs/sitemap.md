# The sitemap

`https://schoenstatt.link/sitemap.xml` is a **static file**, written by
`bin/console sitemap:build` and served by Apache. PHP is not in the request path.

This document exists because the sitemap was broken in five separate ways at
once, none of which was visible in the response, and one of which had quietly
voided the entire file for years. If you change anything here, the checks at the
bottom are how you find out whether you have re-broken it.

## The files

| URL | contents |
| --- | --- |
| `/sitemap.xml` | the index; the only URL robots.txt and Search Console need |
| `/sitemap-pages.xml` | static pages — 26 records, no `<lastmod>` |
| `/sitemap-associations.xml` | shrines and wayside shrines, 250 records |
| `/sitemap-publications.xml` | 6,477 records, ~24 MB — the big one |
| `/sitemap-compositions.xml` | 335 records |

Each record is published under all five locale prefixes with the whole set as
`xhtml:link` alternates, so a file's `<url>` count is five times its record
count. Total as of 2026-08-13: **35,440 `<loc>` entries**, down from 36,730 —
the difference is the 1,290 entries that should never have been there.

A section that outgrows 45,000 URLs or 45 MB splits into `-2`, `-3` parts
automatically. Nothing does today; publications is the closest at 32,385 URLs of
the 50,000 the protocol allows.

## Why every file is at the docroot root

This is the important one. The protocol:

> A Sitemap file located at `http://example.com/catalog/sitemap.xml` can include
> any URLs starting with `http://example.com/catalog/` but can not include URLs
> starting with `http://example.com/images/`.
> — [sitemaps.org](https://www.sitemaps.org/protocol.html)

and Google, on an index:

> Sitemaps that are referenced in the sitemap index file must be in the same
> directory as the sitemap index file, or lower in the site hierarchy.

Until 2026-08-13 the parts were served from `/sitemap/pages.xml` and every URL
in them began `/en/`, `/es/`, `/de/`, `/pt/` or `/it/` — all *above* the file's
own directory, so **all 36,730 URLs were out of scope**. robots.txt then broke
the same rule a second time by naming `/en/sitemap.xml`, putting the index in
`/en/` while the files it listed were in `/sitemap/`. Google reports both as
"URL not allowed", and the practical effect is that a structurally perfect
sitemap was ignored end to end.

So: the files live at `/`, robots.txt names `/sitemap.xml`, and
`test/Smoke/SitemapSmokeTest::testEverySitemapSitsAtTheDocrootRoot()` fails if
either moves. The locale-prefixed URL still answers, because Search Console and
every crawler have it on file.

## What is deliberately excluded

1,290 entries, each a different Google error:

| dropped | why | count |
| --- | --- | --- |
| associations outside `PUBLIC_KINDS` | `AssociationController` 302s a guest to the home page — a soft-404 pattern | 248 × 5 |
| `/admin`, `/movement`, `/libraries` | guarded routes, 302 to the login page | 3 × 5 |
| library 5 | `ViewRole = lib_user`; per-record ACL, same route as the public ones | 1 × 5 |
| `#fragment` URLs | Google discards the fragment, so they duplicate pages already listed | 6 × 5 |
| `/literature/` | assembled with an empty parameter; answered **404** | 1 × 5, **fixed at source 2026-08-14** |
| merged publications | their URL is a 301 to the surviving edition | 3,627 of 10,104 |

Two of these need care because they are not one rule:

- **`GuestAccess::isRoutePublic()`** asks the ACL whether `guest` may reach
  `route/<name>`. It catches any guarded route added later, for free.
- **The association rule is not in the ACL at all.** `route/association` is
  guarded `guest`, and the *controller* then redirects anonymous visitors away
  from every kind outside `AssociationController::PUBLIC_KINDS`. `tools/acl-table.php`
  cannot see it. The sitemap reads that constant directly so the two cannot drift.
- **The library rule is in the ACL but not on a route.** `LibraryTable` is a
  resource *and* rule provider: each row becomes a `library_<id>` resource whose
  `show` privilege is granted to that row's `ViewRole`. So `/libraries/1` and
  `/libraries/5` share one route and one guard entry and differ anyway.

Every one of these filters **fails open**. An ACL we cannot build, a resource
the ACL has never heard of, a table read that throws — all publish the page
rather than dropping it. An over-broad sitemap is recoverable; an empty one is a
Google error in its own right.

### The merged-publication exclusion is checked against every row

Fail-open is only a safe policy if something notices, and for the merged
publications nothing else would: the file stays well-formed, the build stays
silent, and Search Console reports "Page with redirect" weeks later.
`SitemapSmokeTest::testMergedPublicationsAreExcluded()` therefore asserts **set
equality against the database** — the published publication ids are exactly the
`publication_public` rows with `MergedIntoPublicationId IS NULL` — and fails in
both directions, because a missing edition is a page withdrawn from the index.
Measured 2026-08-14: 6,477 published, 3,627 excluded, 0 leaked, 0 missing.

Until then it asserted that one remembered id (`SL200417L`) was absent and its
survivor (`SL207340L`) present, which only ever caught total failure. **The set
is not static**: `copyPublicationToMainCorpus()` marks its source row merged, so
every use of "Copy into main corpus" mints a new redirect that has to leave the
sitemap — and that path, unreachable for years, works again as of 2026-08-14.

`getMergedPublicationIds()` restricts itself to `ResourceId =
'publication_public'`, which is not a narrowing bug: `getPublicationNavigationData()`
filters on the same value, so no other resourceId reaches the sitemap in the
first place. The three merged `publication_patres` / `publication_institute`
rows are absent because they were never candidates.

Its APCu entry cannot go stale for the built sitemap either, and not by luck: an
APCu segment belongs to the SAPI that created it, so the cron console process
starts with a cold cache and reads the database every run.

## `<lastmod>`, and why it does not come from `UpdatedOn`

Because that column is not maintained, and it looks like it is. Association 319
was edited three times on 2026-08-13; all eleven of its `*UpdatedOn` columns
still read `2019-08-15`. A `<lastmod>` built from it would tell Google the page
had not changed in seven years.

`sch_changes` is the record that *is* kept — `SionTable::reportChange()` writes a
row per changed field on every create, update and delete, and
`new \DateTime(null, new \DateTimeZone('utc'))` means those stamps are **UTC**,
which is why `SitemapWriter` renders them as `+00:00`. Measured 2026-08-13, it
covers every record the sitemap publishes: 498/498 associations, 10,166/10,166
publications, 335/335 compositions. A record with no change row simply gets no
`<lastmod>`, which is valid and honest.

Static pages get none either. Stamping them with the build time would tell every
crawler that all 26 change on every build.

## How it gets rebuilt

Freshness is a fact about the data, not a cached flag:
`SitemapGenerator::isStale()` compares `MAX(sch_changes.UpdatedOn)` against the
mtime of `public/sitemap.xml`. Two consequences worth knowing:

- **No hook in the write path.** Editing an association makes the sitemap stale
  by definition. Nothing has to remember to invalidate anything.
- **It could not have been a cache entry.** An APCu segment belongs to the SAPI
  that created it, so a stamp written by a console process is invisible to the
  web server. A database column and a filesystem timestamp are the only two
  clocks both SAPIs share.

### Cron

```cron
*/15 * * * * cd ~/public_html/schoenstatt.link && php bin/console sitemap:build >/dev/null
```

A run with nothing to do costs **~0.11 s** — one `MAX()` over an indexed column
plus PHP start-up — and never touches the navigation. A rebuild costs ~1.2 s.
`--force` skips the check; `--url` overrides the base URL and is what the smoke
tests use.

The base URL comes from `sion_model.canonical_base_url`, not from the request,
because a sitemap must list canonical URLs — whichever hostname happens to reach
the server is not necessarily the one the site is published under.

### The two builders must not disagree, and once they did

`bin/console sitemap:build` and the PHP fallback walk the same navigation tree, but
`PageBuilder` caches its branches in **APCu, which is per-SAPI**. So the console
and the web server can be looking at different navigation data, and on 2026-08-13
they were: `assoc_<id>` had just been added to the association branch, the console
built branches carrying it, and the web SAPI still served one cached before the
change.

The consequence was worse than a stale file. Section assignment read the page id,
so the web-side build filed all 250 associations under `sitemap-pages.xml`, and
`SitemapWriter::removeOrphans()` then **deleted `sitemap-associations.xml`**
because that run had not written it. Nothing failed; the sitemap was simply the
wrong shape and a file a crawler knew about was gone.

Two things follow, and both are now in place:

- **The route decides the file, not the id** (`SitemapSection::forPage()`). A route
  name is a route definition and cannot fall out of a cached branch — if it were
  missing there would be no URL to publish at all. The id is needed only for
  `<lastmod>` and the per-locale slug, so losing it now degrades those to absent
  instead of moving the record to the wrong file.
  `SitemapWriterTest::testTheRouteDecidesTheSectionEvenWithNoId()` pins it.
- **Adding a field to a `PageBuilder` branch still needs
  `cache:flush-persistent`.** That is already a deploy hook, which is why
  production was never affected; the capsule needed it by hand.

### The PHP fallback

`SitemapController` answers `/sitemap.xml` **only when the file is missing** —
first deploy, or someone deleted it. It builds the files and every request after
it is served by Apache. It deliberately does not check staleness: doing so would
put a 0.6 s navigation walk inside an arbitrary crawler request, and would repeat
it on every request until something wrote the file.

Tell the two apart from the response: Apache sends `Accept-Ranges: bytes` and no
cookies; the PHP path cannot answer without starting a session, so it always
carries two `Set-Cookie` headers plus `Pragma: no-cache` and a `no-store`
`Cache-Control`, and sets no `Accept-Ranges`.

**Not by `ETag`.** That was the first check written for this and it was wrong:
production sends no ETag on *any* static file — `/css/gen-basic.css`,
`/favicon.ico` and `/robots.txt` are all without one, the hoster has them off —
while Apache in the capsule does send them. The check passed locally, passed CI,
and failed against the live site on 2026-08-13 with `sitemap has no ETag, so PHP
is serving it` while the deploy had worked perfectly. Anything asserted about
these responses has to be verified against production, not just the capsule.

## Checking it

```bash
docker compose exec -T app php bin/console sitemap:build --url http://localhost:8080 --force
php composer.phar smoke -- --filter SitemapSmokeTest   # 10 tests, ~72k assertions
php composer.phar unit -- --filter SitemapWriterTest   # writer + id classification
```

After a deploy, confirm production is serving statically and in scope:

```bash
curl -sSI https://schoenstatt.link/sitemap.xml | grep -iE 'accept-ranges|set-cookie'
curl -sS https://schoenstatt.link/sitemap.xml | grep -o '<loc>[^<]*</loc>'
```

`Accept-Ranges: bytes` and **no** `Set-Cookie` means Apache is serving the file.
Every `<loc>` in the index must be `https://schoenstatt.link/sitemap-*.xml`. If
one ever appears under a subdirectory again, the sitemap is void.

## All five languages are meant to be indexed

Decided 2026-08-13, and it took three changes outside the sitemap. Until then
every page in every language declared the **English** URL as its canonical, which
reads to Google as "do not index this page, index that one" — so four fifths of
the site was being withdrawn from the index while the sitemap went on offering
all of it. The hreflang set made it worse by omitting the page's own language,
which makes a cluster non-reciprocal and Google discards it entirely.

Now, in both layouts (`templates/layout.html.twig` for ported routes,
`module/Application/view/layout/layout.phtml` for bridged ones — they must
agree):

- each language is **canonical for itself**;
- the hreflang set lists **all five plus `x-default`**, its own page included;
- `x-default` names the English URL.

`test/Smoke/CanonicalLinkSmokeTest` asserts all of it against both layouts. It
used to assert the opposite, by name — a characterization test that recorded the
bug faithfully and failed the moment the bug was fixed, which is what it was for.

### The slug is decorative, so the canonical is the *preferred* URL

`sw_id` selects a record; the slug does not, and the site does not redirect
between forms. So `/en/SL100319A`, `/en/SL100319A/original-schoenstatt-shrine`
and `/en/SL100319A/anything` all answer 200 with the same page. A canonical that
echoed the request would make each of those an indexable page in its own right.

Associations make it sharper: their slug is stored **per locale** (`SlugEn`,
`SlugEs`, `SlugDe`, `SlugPt`, `SlugIt`), and 428 of 498 German slugs differ from
the English one — the German menus link to `/de/SL100319A/urheiligtum`.
Publications and compositions have a single `Slug` column, so their five URLs
differ only in the prefix.

`App\View\PreferredUrls` answers "the one URL this record should be indexed
under, per language", assembled through the router. The three record controllers
hand it to the layout as `locale_paths`; a page that sets none gets the current
path with each prefix swapped in, which is right for a static page.

**The sitemap uses the same builder**, via `SitemapEntry::tailFor()`. That is not
tidiness: the tree is built for one locale, so without it the sitemap would
advertise `/de/SL100319A/original-schoenstatt-shrine` while the German page calls
`urheiligtum` canonical — legal, but a wasted crawl and a Search Console
"Alternate page with proper canonical tag" on ~1,240 entries.
`SitemapSmokeTest::testSitemapUrlsAreTheCanonicalOnes()` samples the two against
each other, because nothing else would notice them drifting apart.

### Still open

- **hreflang is unconditional**: all five locales are declared whether a
  translation of that page exists or not. Deliberate — the set has to match what
  the pages themselves publish, and claiming fewer would break reciprocity.
- ~~The navigation menu still shows the "Other Schoenstatt Literature" link that
  404s.~~ **Fixed at source 2026-08-14 — and the claim was wrong.** `PageBuilder`
  no longer builds that group at all, so the sitemap's trailing-slash rule has
  nothing left to catch here. Two things are worth keeping from it:
  - **Nothing ever rendered the link.** Measured against production before
    changing anything: both navbars stop at depth 0 (`setMaxDepth(0)` in the
    laminas layout, `navigation_items()` in the Twig one) and the group sat at
    depth 2; the Symfony breadcrumb omitted it; the laminas rendering of a
    publication page carries no breadcrumb at all; and the literature home lists
    twelve language links in both renderings and never a languageless one. So
    "the menu shows a 404" was inferred from the data structure, not observed —
    which is exactly the mistake this document exists to stop.
  - **The sitemap was the only thing catching it**, through a rule written for a
    different reason. That is not a guard. A page whose href 404s is now refused
    where it is built, and `test/Integration/NavigationRouteParametersTest`
    fails if any branch declares an empty route parameter again.

  The group's 25 children are re-parented onto the literature root rather than
  dropped, so they stay in the sitemap — verified, and `SitemapSmokeTest`'s set
  comparison against the database would fail if they went missing. They remain
  **orphans**: no page links to them. Giving them a real index page is the
  option that was considered and not taken; see BACKLOG.

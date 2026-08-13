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
| `/literature/` | assembled with an empty parameter; answers **404** | 1 × 5 |
| merged publications | their URL is a 301 to the surviving edition | 3,627, pre-existing |

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

### The PHP fallback

`SitemapController` answers `/sitemap.xml` **only when the file is missing** —
first deploy, or someone deleted it. It builds the files and every request after
it is served by Apache. It deliberately does not check staleness: doing so would
put a 0.6 s navigation walk inside an arbitrary crawler request, and would repeat
it on every request until something wrote the file.

Tell the two apart from the response: Apache sends `ETag` and
`Accept-Ranges: bytes`; the PHP path sends neither and sets session cookies.

## Checking it

```bash
docker compose exec -T app php bin/console sitemap:build --url http://localhost:8080 --force
php composer.phar smoke -- --filter SitemapSmokeTest   # 9 tests, ~72k assertions
php composer.phar unit -- --filter SitemapWriterTest   # writer + id classification
```

After a deploy, confirm production is serving statically and in scope:

```bash
curl -sSI https://schoenstatt.link/sitemap.xml | grep -iE 'etag|accept-ranges'
curl -sS https://schoenstatt.link/sitemap.xml | grep -o '<loc>[^<]*</loc>'
```

Every `<loc>` in the index must be `https://schoenstatt.link/sitemap-*.xml`. If
one ever appears under a subdirectory again, the sitemap is void.

## Known, deliberate, and not fixed here

Three things were found while fixing the above and left alone, because each is
an SEO policy decision rather than a defect in the file's validity:

1. **The pages' canonical tag always points at the English URL.** `/de/SL100319A`
   declares `<link rel="canonical" href=".../en/SL100319A">`. So Google is being
   told to index only the English copy while the sitemap offers all five, and
   the four non-English `<loc>` entries contradict the pages they point at.
   Fixing it means deciding whether the other locales are meant to be indexed at
   all — worth doing, and it is not a sitemap change.
2. **hreflang is unconditional.** All five locales are declared for every page
   whether a translation exists or not, and there is no `x-default`.
3. **The alternates carry the default locale's slug.** `slugByLocale` is
   per-locale in the database, but the tree is built once and each language's URL
   is the same tail with a different prefix, so `/de/SL209835L/<english-slug>`
   is what gets published. Those URLs answer 200 — the slug is decorative — but
   they are not the canonical form.

Also left: the navigation menu still shows the "Other Schoenstatt Literature"
link that 404s. Only the sitemap stops publishing it; fixing the menu means
changing `PageBuilder`, which changes what every visitor sees.

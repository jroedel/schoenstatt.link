# Authorization across the migration

How access control works while laminas-mvc and Symfony both serve this
application, what to do when you port a route, and the traps — each of which has
already cost somebody real time.

Read this **before porting any route that is not public.** 149 of 191 routes
restrict access; getting authorization wrong on one of them produces the worst
failure available here, because the page keeps working and simply admits
everyone. Nothing fails, no test goes red, and the only symptom is that the wrong
people can read something.

Companions: [strangler.md](strangler.md) for the front-controller split,
[view-scripts.md](view-scripts.md) for the view layer, and
[acl-rules.md](acl-rules.md) — generated — for the current state of every rule.

## One set of resources, two enforcers

The thing that makes this tractable: **both sides ask about the same ACL
resources.** There is no parallel authorization config, and there must not be.

| | laminas-mvc | Symfony |
|---|---|---|
| Enforcer | `BjyAuthorize\Guard\Route` | `App\Authorization\RouteGuard` |
| Hook | `MvcEvent::EVENT_ROUTE` | `kernel.request`, priority −16 |
| Resource asked about | `route/<laminas-route-name>` | the same string, declared per route |
| Default | deny | deny, and throw |

Guard entries live where they always did — `config/autoload/acl.global.php` and
the module configs. Porting a route does not move its guard entry, and does not
copy it. The route declares *which existing resource* governs it, so one entry
keeps governing both front controllers, and
[`tools/acl-table.php`](../tools/acl-table.php) stays a complete picture rather
than half of one.

## Declaring authorization on a ported route

Every Symfony route must say something. There are exactly three states, and the
third is a bug:

```php
// 1. checked — the normal case. Names an existing route/<name> resource.
RouteAccess::guardedBy('route/admin')

// 2. checked, denying as JSON — for machine endpoints, so a client that
//    followed a 302 would not get an HTML login page.
RouteAccess::guardedBy('route/something', DenialStyle::Json)

// 3. open — no check, stated deliberately, with the reason recorded.
RouteAccess::openToEveryone('why this needs no check')
```

Declaring nothing is the third state: `RouteGuard` throws
`UndeclaredRouteAccess`. **Openness has to be a statement, not a default** — the
reason string is printed in `acl-rules.md` under "Why the open ones are open",
which is where it gets reviewed.

`RouteAccess` travels as a route *default* under the `_access` key, so Symfony
copies it into the request attributes on match and the guard reads it there.

## Porting a guarded route: the checklist

1. **Find the laminas route's guard entry** and therefore its resource name.
   `grep` the ACL table, not the config — the table already resolves
   `route/<name>` and expands inheritance.
2. **Check who that actually admits.** The declared roles understate reach
   badly; see "Effective roles" below.
3. Declare `RouteAccess::guardedBy('route/<name>')` on the Symfony route.
4. **Leave the laminas route, its guard entry and its controller action alone.**
   Production runs with `SYMFONY_KERNEL` unset and still serves them.
5. **Test all three outcomes** — anonymous, signed in without the role, signed in
   with it. See "Testing" below. Two of the three are easy to forget and both
   have been wrong in development.
6. **Regenerate the snapshots** and read the diff:
   ```sh
   docker compose exec -T app php tools/acl-table.php > docs/acl-rules.md
   docker compose exec -T app php tools/acl-table.php --format=json > docs/acl-baseline.json
   git diff docs/acl-rules.md
   ```
   Zero warnings, and the route appears in the ported table with the resource and
   the grant you expected.

## The denial contract

`JUser\View\RedirectionStrategy::onDispatchError()` forks on **identity**, not on
authorization, and reproducing only one branch is the easy mistake:

| State | laminas | what the bridge does |
|---|---|---|
| No identity | `302`, `Location: <login>?redirect=<path>` | same |
| Identity, wrong role | `403`, renders `error/403` | `403`, Twig equivalent |

Measured on `/en/admin`: anonymous gives
`302 → /en/user/login?redirect=/en/admin`; signed in without the role gives `403`
with "You are not authorized to access admin."

Two details worth keeping:

- The laminas 403 renders **inside the layout** — `UnauthorizedStrategy` adds its
  ViewModel as a child, not a replacement.
- `error/403` resolves to `vendor/kokspflanze/bjy-authorize/view/error/403.phtml`
  — inside the abandoned package. The Symfony side has its own
  `templates/error/403.html.twig` rather than depending on it.

## Traps

Every one of these was discovered the expensive way.

**`Authorize::getIdentity()` is not the identity.** It returns the literal string
`'bjyauthorize-identity'` — the name of the meta-role — and is therefore *always
truthy*. Using it to choose the denial branch sends **every anonymous visitor to
the 403 page** instead of to sign-in. `JUser\AuthService` is what
`RedirectionStrategy` actually consults, and what the bridge consults.

**`isAllowed()` swallows unknown resources.** It catches
`Laminas\Permissions\Acl\Exception\InvalidArgumentException` and returns `false`,
so a **typo in a resource name silently denies everyone** rather than failing.
Safe, but invisible — which is why `acl-table.php` checks that every declared
resource actually exists and warns when one does not.

**`isAllowed()` starts a session.** `session_status()` goes NONE → ACTIVE across
the call, because the identity provider reads the session. Two consequences: a
signed-in user *does* get personalised content on a ported page (only the guard
was ever missing, never the identity), and PHP writes a `Set-Cookie` before any
listener runs — which without handling makes a ported page the only one on the
site setting a cookie without consent. `App\Http\GdprCookieListener` exists for
that. Reproduce it on anything you port.

**Roles are re-read on every request.** `bjyauthorize.cache_enabled` is false and
`ZfcUserZendDbPlusSelfAsRole::getIdentityRoles()` `SELECT`s from
`user_role_linker` each time. So granting a role takes effect immediately, with
no re-login — proven with one cookie jar and one `INSERT`, 403 then 200. A
comment in `tools/form-regression.php` used to claim the opposite; it was wrong
and is corrected.

**A `null` role means public, not guest.** `Acl::setRule` maps it to the
`allRoles` pseudo-parent, which `isAllowed()` consults for every identity. So
`['user', 'guest', null]` is simply *everyone* and the two named roles are
decoration. 17 guarded routes are public this way.

**Guards assign, they do not merge.** `AbstractGuard::__construct` does
`$this->rules[$resource] = [...]`, so when the merged config names one route
twice the **last entry wins outright** and the earlier is discarded silently.
Merge order is module config, then `config/autoload/*.global.php`, then
`*.local.php`.

**Effective roles, not declared roles.** `user_role.parent_id` points at the
*more privileged* ancestor, so granting `user` admits **34 of the 43 roles**.
Always read the effective column in `acl-rules.md`; the declared list routinely
understates reach by an order of magnitude.

**`UndeclaredRouteAccess` is loud in the log, not on the wire.** With
`display_errors` off — which is the capsule as well as production — an uncaught
exception renders as an **empty HTTP 200**, this application's fatal-200
signature. So an undeclared route serves nothing (verified: `size=0`, exception
recorded in `data/exceptions/`), but it does *not* look like an error to a
browser. Do not read a blank 200 as success. The second net is
`SymfonyRouteAuthorizationTest`, which walks the whole `RouteCollection` and
fails on any route declaring nothing — that is the one that catches it before a
human does.

## The oracle

`tools/acl-table.php` is the only check that can catch an authorization
regression, because a passing test suite cannot: a rule that stops matching makes
a page work for *more* people and nothing goes red.

It warns on, among others:

- `SILENT BYPASS RISK` — a Symfony route declaring no authorization.
- A declared resource that no guard entry defines, which denies everyone quietly.
- A restricted laminas guard shadowed by a Symfony route checking something other
  than that same resource.

Regenerate and diff after touching any route, guard, or role. `docs/acl-rules.md`
is for reading; `docs/acl-baseline.json` is sorted for `diff`.

## Testing

Use `test/Smoke/MagicLinkSignIn` — a trait with `signIn($jar, $roles)` that does
the magic-link flow through Mailpit and grants roles. Registration is open in the
capsule, so **every user must purge in `tearDown()`**: `purgeAccounts()` and
`purgeMail()`, both keyed on `emailPrefix()`. `test/Smoke/AdminAuthorizationSmokeTest`
is the worked example, asserting all three outcomes.

Assert all three. A test that only covers anonymous denial passes against a
bridge that denies *everyone*, including the people who should get in.

## Known difference, not yet fixed

On the **unprefixed** form of a restricted path, laminas does SlmLocale's
`302 → /en/admin` first and denies on the second hop; the bridge denies at once
with `?redirect=/admin`. Same access, same eventual destination, one fewer
redirect. Fixing it means moving the locale redirect into a listener above the
guard, which needs its own per-route declaration, because the maintenance
endpoints must not redirect. Recorded in [BACKLOG.md](BACKLOG.md).

## Where this is going

This bridge is **transitional**, and deliberately so. The destination is
Symfony's Security component — `access_control` plus voters — and BjyAuthorize is
abandoned (2.4.4 is the last release of its MVC line).

Two things to keep straight about that sequencing, because they have been got
wrong before:

- **Retiring BjyAuthorize does not unblock `symfony/framework-bundle`.** It caps
  `laminas-cache`, but so does laminas-mvc via `laminas-servicemanager ^3.20.0`,
  in every version it has. See [php-85.md](php-85.md).
- **The bridge is what makes the Security migration incremental.** Because both
  enforcers consult the same resources, resources can move to voters one at a
  time with the ACL table as the before/after oracle — rather than as one
  all-or-nothing switch across 149 routes.

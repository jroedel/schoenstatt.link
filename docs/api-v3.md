# The v3 API

The read/write API for automated agents maintaining the shrine database.

`/api/v3` exists because v1 and v2 cannot be written to and could not be made
writable. Both are still served, unchanged, for the map and mobile consumers that
read them.

## Why not extend v1 or v2

| | v1 / v2 | v3 |
|---|---|---|
| verbs | `GET` only — every write inherits `AbstractRestfulController`'s 405 | `GET`, `PATCH` |
| authentication | none; `authenticateApiKey()` exists with every call site commented out, and the guards admit `null` | bearer JWT, account must hold `sch_api_bot` |
| identity | none, so a write would record `UpdatedBy = NULL` | the bot's user id, into `sch_changes` |
| representation | schema.org `CatholicChurch` — a projection for drawing maps | the editable field set, the same names the moderator form uses |
| locale prefix | `/en/api/v1/…` (SlmLocale strips it) | none; the API emits no localized prose |

The representation is the deciding one. Nothing in a `CatholicChurch` maps back onto
the columns an edit writes, so an agent could not read a shrine, change a field and
write it back. Adding writes to v2 would have meant a second, contradictory vocabulary
inside the same document.

## Getting a token

An agent is a normal account with one extra role.

1. **Create the account** at `/en/users/create` (administrators only). Fill in the
   address, then in **Roles select `sch_api_bot` and deselect `sch_user`** — the form
   pre-selects the default roles, and `sch_user` opens the moderator edit form for
   every association. Leaving it ticked is the one mistake that undoes the whole
   separation below.
2. **Issue a token** at `/en/users/:id/api-tokens`. Give it a label saying what the
   agent is for, press *Issue token*, and copy the JWT out of the message: it is shown
   once and is not recoverable, because only its identifier is stored. Six months by
   default (`juser.api_jwt_lifetime`).
3. Hand it to the agent as `Authorization: Bearer <jwt>`.

The button appears only for accounts holding a role named in
`juser.api_token_roles`, which at this site is `sch_api_bot` and nothing else. That
restriction is the point: unrestricted, the screen would mint six-month bearer tokens
for *any* account including another administrator's — credentials that outlive the
session that made them and that no role or password change revokes.

### Revoking

Same screen. Revocation takes effect on the token's **next request** and is per
token, not per account, so one compromised agent credential does not mean cutting off
every agent that account runs. Revoked tokens stay listed as a record; expired ones
are cleared a day after they lapse.

This works because since `db6.7` every issued token has a row in `user_api_token`
keyed by its `jti` claim, and the API refuses any token whose `jti` is missing from
that table or marked revoked. **Fail closed, not fail open** — "we have no record of
issuing this" and "this was revoked" are the same answer, which is what makes the
registry worth having. Only the identifier is stored, never the token.

### The email flow still works

An agent that can read its own mailbox can sign itself in, and the mobile apps
already do:

```
POST /api/v1/users/request-verification-token   identity=<email>
POST /api/v1/users/login-with-verification-token identity=<email>&token=<code>
   → { "jwt": "...", "expiration": "2027-02-09T12:00:00Z" }
```

Tokens issued this way are registered and revocable exactly like minted ones; they
show as *(self, by email)* on the token screen. It is no longer the recommended path
for a bot, because it forces you to run a real deliverable mailbox for an account
that has nobody to read it — and that mailbox is then a permanent credential-recovery
path into the bot account.

### The role is the security boundary

Two things gate a write: the token must be one we still vouch for (above), and the
account must hold `sch_api_bot`. The role is named by **nothing else on this site** —
no ACL guard, no route, no menu. Two consequences, both deliberate:

- A leaked bot token reaches `/api/v3` and nothing else.
- A *human's* token cannot write through the API. This matters more than it looks:
  registration grants `sch_user`, and `route/association-edit` names `sch_user`, so
  **every registered account can already edit every association through the web
  form**. Reusing `sch_user` for bots would have made an automation credential a
  general-purpose site credential.

Refusals are uninformative on purpose: missing, malformed, expired, revoked,
unregistered, unknown user and missing role are all `401` with one message.

## Endpoints

### `GET /api/v3/schema`

Public — no token. The field contract, **generated from the same specification the
form and the `PATCH` endpoint validate with**, so it cannot drift from what is
actually enforced. Read this first; it is the reason you should not need to read
anything else.

```jsonc
{
  "version": 3,
  "entity": "association",
  "fields": {
    "openingHoursHuman": { "required": false, "maxLength": 500, "filters": ["ToNull"] },
    "kind":              { "required": true,  "enum": ["sch-shrine", "sch-wayside-shrine", ...] },
    "country":           { "required": false, "maxLength": 6, "enum": ["AF", "AL", ...] },
    "geoPoint":          { "required": false, "constraints": ["GpsPoint"], "filters": ["ToGeoPoint"] }
  }
}
```

The enumerations are live: `kind` lists the kinds this database has, `parentId` every
association id including inactive ones. Validate locally against them rather than
discovering by rejection.

### `GET /api/v3/associations`

The collection. `?kind=sch-shrine` for shrines, `?kind=sch-wayside-shrine` for wayside
shrines. `?limit=` (default 100, max 500) and `?offset=` page it.

### `GET /api/v3/associations/{identifier}`

One association, e.g. `/api/v3/associations/SL100319A`. Returns an `ETag`.

```jsonc
{
  "identifier": "SL100319A",
  "kind": "sch-shrine",
  "fields": { "name": "Original Schoenstatt Shrine", "country": "DE", ... },
  "meta": { "updatedOn": "...", "updatedBy": 5, "etag": "W/\"639df94…\"", "url": "..." }
}
```

Every key of `fields` is a key you may send back. That is the round-trip guarantee.

### `PATCH /api/v3/associations/{identifier}`

A JSON object of any subset of the writable fields.

```bash
curl -X PATCH https://schoenstatt.link/api/v3/associations/SL100319A \
  -H "Authorization: Bearer $JWT" \
  -H "Content-Type: application/json" \
  -H "If-Match: $ETAG" \
  -d '{"openingHoursHuman": "Sun-Sat 9-11am, 12-8pm"}'
```

```jsonc
{ "changed": ["openingHoursHuman"], "association": { ...the new representation... } }
```

`changed` lists the fields that actually moved. Sending a value that is already stored
is a `200` with `"changed": []` and no write — so a polling agent does not fill
`sch_changes` with noise.

| status | meaning |
|---|---|
| `200` | applied (or nothing to apply) |
| `401` | no usable token — missing, expired, **revoked**, or the account lacks `sch_api_bot` |
| `404` | no association has that identifier |
| `412` | `If-Match` no longer matches — re-read and re-apply |
| `422` | the change would leave the association invalid, or names an unknown field |
| `405` | wrong verb; the `Allow` header lists the right ones |

## Validation is the moderator form's, exactly

A patch is **merged onto the stored record and the whole record validated**, by the
edit form's own `InputFilter` — not a copy of its rules, the same object graph. An
agent is refused precisely what a moderator is refused, with the same messages:

```jsonc
{ "error": { "status": 422, "message": "The association would not be valid after this change.",
             "messages": { "kind": { "notInArray": "The input was not found in the haystack" } } } }
```

Merging first is what makes a partial update possible at all — `name` and `kind` are
required, so a bare `{"openingHoursHuman": "…"}` would otherwise fail on two fields you
never mentioned.

The one rule an agent is *not* held to is the CSRF token, which presupposes a browser
session. `test/Integration/AssociationValidationParityTest` asserts that this is the
only difference, field by field and validator by validator.

An unknown field name is a `422` rather than being ignored: an agent that misspells a
field and gets a `200` will keep misspelling it forever, and the shrine it believes it
is maintaining silently never changes.

## Concurrency

`GET` returns an `ETag`; `PATCH` honours `If-Match` and answers `412` when the record
has moved. Omitting `If-Match` is permitted and means a blind write.

**Send it.** Two agents editing the same shrine within one second is exactly the case
the tag exists for, and `UpdatedOn` has only second resolution — the tag is a hash of
the field document, so it moves when and only when something you can see changed.

One caveat that will bite anyone comparing tags by hand: Apache's `mod_deflate`
appends `-gzip` to the ETag of a compressed response, so the tag you receive is
`W/"639df94…-gzip"` while the tag the server computed is `W/"639df94…"`. The API
normalizes both sides — echo back whatever you were given and it will match.

## What agents cannot do

- **Create.** `POST` is not implemented. Creating an association decides its kind, its
  parent and its associated roles, and those are human decisions for now.
- **Delete.** Same.
- **Reach anything else.** The role opens no other door.

## Auditing what agents did

Every field an agent changes is a row in `sch_changes` under the bot's user id, so
`/sm/view-changes` shows agent edits beside human ones and each is attributable and
revertible. That is the safety model: agent writes are not reviewed *before* they land,
they are recorded so they can be found afterwards.

## Where the code is

| file | what |
|---|---|
| `config/symfony/routes.php` | the routes, and why they declare themselves open |
| `src/Api/BotIdentity.php` | token → user id → role check |
| `src/Http/AuthorizationHeader.php` | why the `Authorization` header needs finding |
| `src/Controller/Api/AssociationsV3Controller.php` | the endpoints |
| `src/Controller/Api/ApiSchemaController.php` | `/api/v3/schema` |
| `src/Schoenstatt/Association/AssociationResource.php` | the representation, merge and ETag |
| `src/Schoenstatt/Association/AssociationValidator.php` | the form's filter, headless |
| `src/Schoenstatt/Association/AssociationInputFilterSpec.php` | the rules themselves |
| `database/db6.6.sql` | the `sch_api_bot` role |
| `database/db6.7.sql` | `user_api_token`, and why a token is refused unless vouched for |
| `module/JUser/src/Service/ApiTokenService.php` | the one place a JWT is minted and recorded |
| `module/JUser/src/Model/ApiTokenTable.php` | the registry; uncached, on purpose |
| `module/JUser/src/Controller/UsersController.php` | `apiTokensAction`, `revokeApiTokenAction` |
| `test/Smoke/ApiV3SmokeTest.php` | the whole surface over HTTP |
| `test/Smoke/ApiTokenAdminSmokeTest.php` | issue in the browser → use against the API → revoke → refused |

## Is it live?

The v3 routes are served by the **Symfony** front controller, and production still runs
the laminas one for ordinary traffic. So v3 is **deployed and canary-gated**, not
dormant and not general: a request carrying `sl_symfony_canary=1` is served by the
Symfony kernel and reaches v3, everything else gets laminas and a 404. See the
canary section of [DEPLOY.md](DEPLOY.md).

Verified on production 2026-08-09:

```bash
# 200, the real schema
curl -H 'Cookie: sl_symfony_canary=1' https://schoenstatt.link/api/v3/schema

# 401 — the endpoint is reachable and the token gate works
curl -H 'Cookie: sl_symfony_canary=1' https://schoenstatt.link/api/v3/associations

# without the cookie: 302 to /en/… and then laminas' 404, which is correct
curl -i https://schoenstatt.link/api/v3/schema
```

**An agent cannot use v3 until `SYMFONY_KERNEL` is flipped globally**, because an
agent will not send a canary cookie — and should not be asked to, since the cookie is a
staging device, not an API contract. Until then the canary is how to prove the endpoints
work against real production data.

That flip is one line in `public/.htaccess`, and everything around it was prepared on
2026-08-09: the procedure, its two checklists and its three rollbacks are in
[DEPLOY.md](DEPLOY.md#flipping-the-symfony-kernel-on-globally). Note the v3 prerequisite
it names — `database/db6.6.sql` applied and at least one bot account holding the role, or
every agent request 401s the moment the endpoints become reachable.

# The library feature

How lending works, why the authorization looks too loose, and why it is not. Almost
everything surprising here is a deliberate decision that looks like an oversight.

## One feature, several kinds of library

| library | audience | how that is expressed |
|---|---|---|
| Bellavista (1) | Schoenstatt Fathers house | `CheckoutBooksRole = guest`, person list `patres-sion` |
| Colegio Mayor (3) | study house, its own community | `CheckoutBooksRole = lib_patres`, person list `patres-sion` |
| PUC (4) | inactive | checkouts off |
| Vaterhaus Investigation (5) | reference collection | `ViewRole = lib_user`, checkouts off |
| Schoenstatt Fathers Austin (6) | mostly priests, open to anyone who asks | `CheckoutBooksRole = guest`, person list `all-borrowers` |
| Schoenstatt University Men (7) | any student at UT Austin | `CheckoutBooksRole = guest`, person list `all-borrowers` |

Policy columns on `lib_libraries`:

- **`ViewRole`** — who may see the library; drives an `allow … show` rule.
- **`CheckoutBooksRole`** — who may borrow; drives an `allow … checkout` rule.
  **NULL emits no rule**, which under default deny means *nobody*.
- **`EnableCheckouts`** — a separate switch, so "no rule" and "switched off" cannot
  be confused.
- **`CheckoutPersonListKind`** — which list of people the checkout form offers.
- `DefaultCheckoutTimePeriodInDays`, `MaximumBookRenewals` (default 3),
  `DefaultCheckoutPersonId` — loan terms. Renewal is `LibraryTable::renewBook()`:
  it persists, counts, enforces the maximum, and an overdue book renews from *today*.

`Books\Model\LibraryTable::getRules()` turns those columns into ACL rules against a
`library_<id>` resource, one per row, at request time; `App\Acl\AclAssembler` calls
it as a dynamic rule provider. `App\Books\LibraryAclRules` is a second copy of the
mapping so `tools/acl-table.php` can report it, and
`test/Integration/LibraryAclRuleDriftTest` fails if the two disagree.

The admin option list is `LibraryTable::LIBRARY_GENERAL_ROLE_OPTIONS`: Public
(`guest`), Authenticated users (`lib_user`), Academic users (`lib_academic`),
Institute members (`lib_institute`), Patres (`lib_patres`). The roles chain —
`user → lib_user → lib_academic → lib_institute → lib_patres` — each inheriting
the last, so **naming a role admits it and everything below it**.

## `guest` means "every account", and that is deliberate

`getRules()` adds `user` alongside `guest` ("if it's free to guests, it should also
be open to users"), and `user` is the root of the chain. So a library set to
**Public is open to every signed-in account**. The route guard on
`libraries/library/checkout` is `lib_user`, which is `is_default = 1`, so it stops
anonymous visitors and nobody else. Between the two gates a Public library's
lending form is reachable by anyone with an account.

**This is intended.** In a religious community a checkout that is not effortless
does not happen — the book goes to someone's room and nothing is recorded. Low
friction was chosen over strict control; the accepted worst case is a bot marking
books checked out that were not, which is recoverable. Unrecorded loans are not.
Colegio Mayor (`lib_patres`) shows restriction is used where it is wanted.
`test/Smoke/LibraryCheckoutAuthorizationSmokeTest` pins **both** halves — open
where intended, closed where intended.

## Borrowers are not accounts, and cannot be

There is **no user-to-person link in this database**. `sch_persons` was built for
`/movement` to hold contact data for Schoenstatt leaders and was reused as the
borrower model. Two consequences:

1. **The checkout form asks *who* is borrowing** (a `personId` select plus book
   ids); nothing can infer it from the signed-in account.
2. **Borrowers reach their own books without an account**, through a scoped link
   in the overdue notice: `/library/my-books?t=…`. The token
   (`Books\Model\BorrowerTokenTable`, table `lib_borrower_tokens`) authorises
   exactly one person at one library, is stored as a **sha256 digest**, expires,
   and is deliberately **not single-use**. The page
   (`App\Controller\BorrowerCheckoutsController`) exists so that its authorization
   is ordinary code rather than a role, and **no `person_id` may ever appear in
   that URL**.

Notices go out via `bin/console books:send-notices --library=N [--dry-run]
[--all-borrowers] [--list]`; no API key or account is needed.

"Who may borrow" is answered by `CheckoutBooksRole` (may this visitor use the
lending form) and `CheckoutPersonListKind` (whose name may be picked). There is no
per-borrower permission and nowhere to put one.

## The person list, and the Patres import

- **`all-borrowers`** — people already marked as borrowers locally.
- **`patres-sion`** — the roster from the Patres API
  (`schoenstatt-fathers.link/api/persons`). On submit, `createCheckouts()` calls
  `PatresGateway::getSchoenstattPersonFromPatresPersonId($id, true, ['isBorrower' => true])`,
  which copies the person into `sch_persons` if not present.

That path **cannot overwrite an existing local record**: it imports only after the
local lookup missed, and both run the identical
`searchPersons(['dataSource' => 'patres-sion', 'dataSourceId' => …])` query. The
overwrite branch is `admin/import-father` (`App\Controller\ImportFatherController`,
`sch_administrator` only). What a Public `patres-sion` library lets any account do
is **create** a person from the roster, validated by this application's person
input filter — bound it there, not in the ACL.

## Where the checks live

`App\Books\LibraryPage` is the one implementation of "read `library_id`, load the
row, ask the ACL"; every page on the surface opens with it.

| what | check |
|---|---|
| see a library | `App\Controller\LibraryController` → `LibraryPage::refuse(…, 'show')` |
| use the lending form | `App\Controller\LibraryCheckoutController` → `LibraryPage::refuse(…, 'checkout')` |
| check in, mass checkout, admin menu, imports, `refresh-sort` | `'administrate'` — `lib_administrator` |
| borrower sees own loans | scoped token, not a role |

**A denied `show` flashes and redirects to `/libraries`; a denied anything-else
answers 403** — reproduced, not tidied. `BooksSmokeTest` pins the redirect for
library 5.

Route guards (`config/autoload/acl.global.php`, `guards`) are the outer gate and
the per-library rules the inner one. **Every guard on this surface names
`lib_user` (`is_default = 1`) except `libraries/library/delete`, which names
`lib_administrator`** — so the outer gate means "signed in" and the inner one is
the protection. With one qualification: **`administrate` is not per-library.**
`getRules()` emits `[['lib_administrator'], $resourceId, 'administrate']` for
*every* row unconditionally (there is no per-library administrator table), so a
library administrator anywhere is one everywhere. `show` and `checkout` are the
genuinely per-row ones, because they read `ViewRole` and `CheckoutBooksRole` off
the row.

**`refresh-sort` answers GET with a confirmation** and writes only on a
token-checked POST (`App\Controller\LibrarySortController`), because it rewrites
every book's sort text in the library.

## Deleting a library

`/libraries/{id}/delete` is `App\Books\LibraryDelete`, written rather than
generic because of the cascade: `SionTable::deleteEntity()` is a single-row
`DELETE`, and of the four tables carrying a library id only `lib_imports` has a
foreign key (`ON DELETE CASCADE`). `lib_books`, `lib_collections` and
`lib_borrower_tokens` have none, so a bare delete would leave a catalogue (PUC:
16,383 books) pointing at a library that no longer exists, with no error.

Rules of the cascade — one transaction, children first:

- **checkouts before books** — `lib_checkouts` has no library id and is reached by
  joining `lib_books`; then books, collections, borrower tokens, imports, the row;
- **cache invalidation after the commit**, not inside it;
- **the confirmation requires the library's name typed exactly**, case-sensitive —
  this one destroys a catalogue, so a CSRF token and a button is not enough;
- **the change log gets an aggregate**: one `entryDeleted` row carrying the name in
  `OldValue`, plus one counted row per dependent kind — not 16,383 book rows;
- outstanding checkouts (`CheckedInOn IS NULL`) are counted and shown separately
  because a book is physically out; they do not block the delete.

`enable_delete_action` stays commented out in the `library` entity spec so the
generic single-row delete can never handle the entity.

**A library created outside the application answers 403 to everyone until the
persistent cache is flushed.** `getRules()` derives its resources from the cached
`getObjects('library')` and the assembled ACL is itself cached in APCu; a write
through the application invalidates both, a migration or a DBA does not.
`test/Smoke/LibraryDeleteSmokeTest` hits `/sm/clear-persistent-cache` after
building its fixtures for exactly this reason.

## Not here, and broken-but-reproduced

- **No individual loan record page.** A library's loans are read through
  `checkouts/library/{current,overdue}`, a person's through `borrowers/borrower`.
- **No per-library staff list** — see `administrate` above.
- **Mass checkout offers the Schoenstatt Fathers at every library**, where the
  single-checkout form offers the library's own `CheckoutPersonListKind` list.
- **The borrower page announces a redirect it never performs**: its countdown
  script does not parse (a translated string interpolated into JavaScript
  unquoted) and its target is the hardcoded `/libraries/3`. Fixing the syntax
  without deciding the target would send every borrower to Colegio Mayor.

See also [library-imports.md](library-imports.md).

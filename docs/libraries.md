# The library feature

How lending works, why the authorization looks too loose, and why it is not.

Read this before changing anything about `lib_libraries`, `CheckoutsController`, or the
per-library ACL rules. Almost everything surprising here is a deliberate decision that
looks like an oversight, which is the worst combination for a codebase nobody has touched
in five years.

## One feature, several kinds of library

The system serves libraries with genuinely different audiences, and it was built to be
configured per library rather than to impose one model:

| library | audience | how that is expressed |
|---|---|---|
| Bellavista (1) | Schoenstatt Fathers house | `CheckoutBooksRole = guest`, person list `patres-sion` |
| Colegio Mayor (3) | study house, its own community | `CheckoutBooksRole = lib_patres`, person list `patres-sion` |
| PUC (4) | inactive | checkouts off |
| Vaterhaus Investigation (5) | reference collection | checkouts off |
| Schoenstatt Fathers Austin (6) | mostly priests, open to anyone who asks | `CheckoutBooksRole = guest`, person list `all-borrowers` |
| Schoenstatt University Men (7) | any student at UT Austin | `CheckoutBooksRole = guest`, person list `all-borrowers` |

The policy columns on `lib_libraries` are:

- **`ViewRole`** — who may see the library. Drives an `allow … show` rule.
- **`CheckoutBooksRole`** — who may borrow. Drives an `allow … checkout` rule. **NULL emits
  no rule at all**, which under default-deny means *nobody*, not everybody.
- **`EnableCheckouts`** — a separate on/off switch, so "no rule" and "switched off" cannot
  be confused for one another.
- **`CheckoutPersonListKind`** — which list of people the checkout form offers.
- `DefaultCheckoutTimePeriodInDays`, `MaximumBookRenewals`, `DefaultCheckoutPersonId` — loan
  terms.

`Books\Model\LibraryTable::getRules()` turns those columns into ACL rules against a
`library_<id>` resource, one resource per row, built at request time. `App\Books\
LibraryAclRules` is a second copy of that mapping so `tools/acl-table.php` can report them;
`test/Integration/LibraryAclRuleDriftTest` fails if the two disagree.

The admin option list is `LibraryTable::LIBRARY_GENERAL_ROLE_OPTIONS`: Public (`guest`),
Authenticated users (`lib_user`), Academic users (`lib_academic`), Institute members
(`lib_institute`), Patres (`lib_patres`). The roles form a chain —
`user → lib_user → lib_academic → lib_institute → lib_patres` — each inheriting the last,
so **naming a role admits it and everything below it**.

## `guest` does not mean "anonymous"

`getRules()` adds `user` alongside `guest`, with the comment *"if it's free to guests, it
should also be open to users"*. `user` is the root of that chain. So a library set to
**Public is open to every signed-in account** — 34 effective roles, 3,727 of 3,791 accounts
— not to anonymous visitors specially.

The route guard on `libraries/library/checkout` is `lib_user`, which is `is_default = 1`,
so it stops anonymous visitors and nobody else. Between the two gates, a Public library's
lending form is reachable by anyone with an account.

**This is intended.** In a religious community, a checkout that is not effortless simply
does not happen — the book goes to someone's room and nothing is recorded. A flexible
low-friction system was chosen over strict control, with the accepted worst case being
*a malicious bot marking books checked out that were not*, which is recoverable. Unrecorded
loans are not. Colegio Mayor (`lib_patres`) is the counter-example that shows restriction
is used where it is wanted.

`test/Smoke/LibraryCheckoutAuthorizationSmokeTest` pins **both** halves — open where
intended, closed where intended — because pinning only one invites the other to be
"corrected" by someone reading the ACL without this context.

## Borrowers are not accounts, and cannot be

There is **no user-to-person link in this database at all**. `sch_persons` was built for the
`/movement` route, to hold contact information for Schoenstatt leaders; when the library
feature came along those people and that table already existed, so it was reused rather than
a separate borrower model being built.

Two consequences follow, and both look like bugs until you know this:

1. **The checkout form asks *who* is borrowing.** It is not inferred from the signed-in
   account, because there is nothing to infer it from. `CheckoutsController::createAction()`
   takes a `personId` from a select and a set of book ids.
2. **Borrowers reach their own books without an account**, through a scoped token
   (`/library/my-books?t=…`, `Books\Model\BorrowerTokenTable`). That page is Symfony-side
   precisely so its authorization is ordinary code rather than a role, and **no `person_id`
   may ever appear in that URL**.

So "who may borrow from this library" is answered by `CheckoutBooksRole` at the level of
*may this visitor use the lending form*, and by `CheckoutPersonListKind` at the level of
*whose name may be picked*. There is no per-borrower permission and there is nowhere to put
one.

## The person list, and the Patres import

`CheckoutPersonListKind` selects the provider behind the form's person select:

- **`all-borrowers`** — people already marked as borrowers locally.
- **`patres-sion`** — the roster from the Patres project's API
  (`schoenstatt-fathers.link/api/persons`), which is the auto-import the internal libraries
  need. On submit, `createCheckouts()` calls
  `PatresGateway::getSchoenstattPersonFromPatresPersonId($id, true, ['isBorrower' => true])`,
  which copies that person into `sch_persons` if they are not here yet.

Worth knowing before treating that import as a hazard: **it cannot overwrite an existing
local record from this path.** `getSchoenstattPersonFromPatresPersonId()` only calls
`importRemotePerson()` after `getPersonInSchoenstattTable()` missed, and both run the
*identical* `searchPersons(['dataSource' => 'patres-sion', 'dataSourceId' => …])` query — so
a miss in the first is a miss in the second, and the create branch is the one that runs. The
overwrite branch belongs to `AdminController::importFatherAction()`, which is
`sch_administrator` only and where re-importing to refresh a record is the point. Since
2026-08-17 that action says which of the two happened instead of reporting both as "Person
successfully imported."

What a Public `patres-sion` library *does* allow any signed-in account to do is cause a
person to be **created** locally from the Patres roster. The fields that arrive are whatever
that project's API returns, validated against this application's person input filter. If
that ever needs bounding, the place to do it is the input filter, not the ACL.

## What is not here

- **There is no individual loan record page.** `checkouts`, `checkouts/checkout` and
  `checkouts/checkout/edit` were removed on 2026-08-17: they were reachable by nobody
  (no guard entry, so default-deny) and had nothing behind them either — the `checkout`
  entity spec has `index_template`, `show_action_template`, `edit_action_form` and
  `edit_action_template` all commented out. A library's loans are read through
  `checkouts/library/{current,overdue}` and a person's through `borrowers/borrower`.
  `/checkouts` is now an unmatchable prefix; `/checkouts/library/:id` still works.
- **There is no per-library staff list.** `administrate` is granted to `lib_administrator`
  globally, for every library. `getRules()` carries a `@todo` sketching a table of
  (resource, permission, rule type, id) that would fix this; nobody has built it, so a
  library administrator anywhere is a library administrator everywhere.
- **`libraries/library/delete` is unfinished**, not merely unguarded: `enable_delete_action`
  is commented out of the `library` entity spec along with its three companions.

## Where the checks actually live

| what | check |
|---|---|
| see a library | `LibrariesController::showAction()` → `isAllowed('library_<id>', 'show')` |
| use the lending form | `CheckoutsController::createAction()` → `isAllowed('library_<id>', 'checkout')` |
| check in, mass checkout, library admin | `'administrate'` — `lib_administrator` |
| borrower sees own loans | scoped token, not a role — `App\Controller\BorrowerCheckoutsController` |

Route guards are the outer gate and are listed in [acl-rules.md](acl-rules.md); the
per-library rules are the inner one and are in that same file under "Per-library rules".

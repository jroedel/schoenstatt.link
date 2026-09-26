# Personal data: what is kept, for how long, and what enforces it

The published policy is `templates/content/privacy.html.twig` (`/en/privacy`). This document
is its design: each store of personal data, the rule for it, and the code or job that
enforces the rule. A promise the policy makes must have a row here with a mechanism in it.

## Stores and their rules

| store | personal data in it | rule | enforced by |
| --- | --- | --- | --- |
| `sch_persons` contact columns | email, phones, web and social-media contacts, postal address, contact notes | erased five years after the person's last activity, unless something is still current (below) | `bin/console privacy:retention`, cron |
| `sch_changes` | the editor's account id; the old and new value of each edited field | rows are kept (the sitemap's `<lastmod>` and the edit history need them). A person's contact values are blanked when that person's contact data is erased, and for everyone once older than five years. Values of fields the schema no longer has are blanked (db9.4) | `privacy:retention`; db9.4 |
| `sch_visits` | none: which record was viewed, when | no IP address or User-Agent since db9.1, no account since db9.5 | — |
| `lib_checkouts` | who borrowed which book | kept: it is the loan record, and a borrower's last loan is what starts their retention clock | — |
| `lib_borrower_tokens` | a hash of a borrower's link, their person id, when it was last used | a link works for 30 days; the row is deleted a month after it expires | `privacy:retention` |
| error reports, `shared/data/exceptions/` | a shortened client address, the account id | 90 days without recurring | deploy housekeeping, [DEPLOY.md](DEPLOY.md) |
| migration snapshots, `shared/migration-snapshots/` | full copies of the tables a migration touched | bounded by age and count | deploy housekeeping, [DEPLOY.md](DEPLOY.md) |
| mail | none kept: `mailings` was dropped in db9.3 | — | — |

Nothing stores an IP address or a User-Agent string except the shortened address in an error
report ([exception-reporting.md](exception-reporting.md)). The web server's access log is
the host's, outside this application.

## The contact-data rule

A person's contact data is erased when **five years** have passed since their **last
activity**, which is the latest of:

- the end of their last assignment,
- their last library loan — checked out, renewed or returned,
- the last edit of their contact data, or the record's creation if it was never edited.

**Anything current keeps everything**: an assignment without an end date, or one ending in
the future, and a book not yet returned. The clock is the same for a person who never held an
assignment — a borrower or an author — so that nobody's data is kept indefinitely because a
kind of activity does not apply to them.

What is erased is exactly `App\Privacy\ContactRetention::CONTACT_FIELDS`. The name, tags,
country, notes and assignments stay: they are the register the site exists to keep, and the
policy's promise is about contact data. `ContactRetentionTest` fails on any `sch_persons`
column that is on neither that list nor its own list of columns kept on purpose, so a new
contact column cannot escape the rule unnoticed.

The erasure is plain SQL rather than `SchoenstattTable::updateEntity()`, because an update
through the table reports each changed field to `sch_changes` with its old value — erasing an
address that way would copy it into the change log again. It stamps `ContactInfoUpdatedOn`,
which restarts the clock for a record that is later edited again.

## Running it

```
php bin/console privacy:retention            # dry run: who is due, and how many change rows
php bin/console privacy:retention --apply    # erase, then flush the web cache over HTTP
```

A dry run changes nothing and prints person ids and dates, never a name or an address.
`--apply` runs in one transaction and then calls `cache:flush-persistent`, because the site
would otherwise keep serving erased values from APCu, which a console process cannot reach
([caching.md](caching.md), rule zero); a failed flush fails the run. The schedule is a cron
entry, [DEPLOY.md](DEPLOY.md#scheduled-jobs).

## Access and erasure

**Access.** A signed-in account downloads its own export from `/en/user/my-data` (the
account menu's *My data*); anyone else writes in, and an administrator runs
`php bin/console privacy:export --person=<id>` or `--account=<id>` and sends the JSON.
`App\Privacy\PersonalData` builds both, and an export of an account includes the person it
is linked to and the other way round. It holds the person row, assignments, loans, borrower
links (not their hashes), relationships, publications naming them, the sources of their
data (`sch_provenance`), the history of their record, and for each account its roles, API
tokens (not the secrets), comments, and a per-entity count of the edits it made to other
records — counted rather than listed, because those rows are about the other records.

**Erasure** is `php bin/console privacy:erase --person=<id>` or `--account=<id>`, a dry run
until `--apply`, which then flushes the web cache. `App\Privacy\Erasure` owns the rules:

- **A book still out refuses** the erasure: the loan is the library's record of its own
  property. Check it in or write it off first.
- **Everything linked to the person goes**: assignments, loans, borrower links,
  relationships naming them, provenance, their record's history, a spouse's link to them, a
  library's default borrower. So do their accounts.
- **An author keeps their name.** Someone named as the author, editor, translator or
  illustrator of a publication or text (the `IsAuthor` flag, `p<id>` in
  `sch_publications.Authors`, or an authorship relationship) keeps FirstName, LastName and
  the flag, because authorship of a published work is a public bibliographic fact.
  Everything else on the row is cleared, and the authorship relationships stay.
- **An account is deleted, and so is its name on everything it did**: every integer column
  whose name ends in `By` or `_by` is set to NULL where it holds the account's id. The
  column list comes from information_schema, so a new column is covered without anyone
  listing it. The `trans_*` tables are the exception, because they are shared with the
  patres application and hold its user ids too, so they are updated explicitly and only for
  this project's `project_name`. The account's comments are deleted.
- **Free text is reported, not rewritten.** Notes, translations and phrases that contain
  the person's name or email address are listed for someone to edit by hand.

Partial erasure is an edit: a moderator clears the field, and `privacy:retention` blanks its
old values in the change log once they are five years old. Nothing is kept to stop an erased
person being added again. A hash of their name and address would not be anonymous, because
anyone holding a candidate name can check it against the hash.

## Not covered by a rule

- **Associations' contact data** (`sch_associations`): an organisation's address and
  numbers, maintained by its moderators, not a person's.
- **Accounts** (`user`): an address that signs in. There is no record of the last sign-in,
  so no inactivity rule can be stated yet; an account goes when its owner asks.
- **`user_remember_me`**: a table from the old sign-in that nothing reads or writes; its
  removal is the server task in [BACKLOG.md](BACKLOG.md).
- **Free text** in public notes and the phrase table can mention a person; it is not
  structured, so only an erasure request can find it.

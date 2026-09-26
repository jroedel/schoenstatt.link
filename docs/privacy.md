# Personal data: what is kept, for how long, and what enforces it

The published policy is `templates/content/privacy.html.twig` (`/en/privacy`). This document
is its design: each store of personal data, the rule for it, and the code or job that
enforces the rule. A promise the policy makes must have a row here with a mechanism in it.

## Stores and their rules

| store | personal data in it | rule | enforced by |
| --- | --- | --- | --- |
| `sch_persons` contact columns | email, phones, web and social-media contacts, postal address, contact notes | erased five years after the person's last activity, unless something is still current (below) | `bin/console privacy:retention`, cron |
| `sch_changes` | the editor's account id; the old and new value of each edited field | rows are kept (the sitemap's `<lastmod>` and the edit history need them). A person's contact values are blanked when that person's contact data is erased, and for everyone once older than five years. Values of fields the schema no longer has are blanked (db9.4) | `privacy:retention`; db9.4 |
| `sch_visits` | which account viewed which record, when | no IP address or User-Agent since db9.1 | — |
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

## Not covered by a rule

- **Associations' contact data** (`sch_associations`): an organisation's address and
  numbers, maintained by its moderators, not a person's.
- **Accounts** (`user`): an address that signs in. There is no record of the last sign-in,
  so no inactivity rule can be stated yet.
- **Free text** in public notes and the phrase table can mention a person; it is not
  structured, so only an erasure request can find it.

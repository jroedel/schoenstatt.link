# SQL observations

Queries that look wrong, or look expensive, noticed while reading the code that issues
them. **Nothing here is a plan to change anything**: a query in this file has been seen and
measured, not decided about. The point is that the moment a query is understood is the
moment it is read, and that moment is a long way from whenever someone is free to act.

One item per finding: the statement, its owner, the number that makes it worth keeping, and
what is *not* known. A finding with no number is a suspicion and does not belong here yet —
measure it first, or leave it out. When an item is fixed it is deleted, not annotated; git
history holds the story.

Row counts are from the capsule's production export, read on the date given. `test/Db/sql-surface.txt`
records the statement shapes themselves; `./tools/sql-surface.sh` regenerates it.

**A read of a whole table is not on its own a finding.** Most of them are the caching
strategy: `SionTable::queryObjects()` sets a cache key **only** when `$query` and `$options`
are both empty (line 409), so "reads the table unfiltered" and "is cached in APCu under
`query-objects-<entity>`" are the same branch by construction — the table is read once and
every later caller is served from the cache, and adding a `WHERE` to one of those would
trade a cached read for an uncached one. What is worth a second look is an unfiltered read
that goes through some *other* path: a hand-built `Sql`/`Select` with no predicate, or a
gateway `select()` outside `queryObjects()`, where nothing caches the result and it is read
again on every request. Say which of the two an item is, and how it was determined.

## Open

- **Nothing is prepared server-side. Every statement arrives interpolated.** Measured
  2026-09-22 across a full HTTP pass and the integration suite: MariaDB's general log holds
  **1,120 `Query` and 0 `Prepare`**. PDO's `ATTR_EMULATE_PREPARES` defaults to on for MySQL
  and no `driver_options` entry turns it off — `config/autoload/local.php.dist` and
  `docker/local.docker.php` set only `MYSQL_ATTR_INIT_COMMAND`. So values are escaped in the
  client and pasted into the statement, the server parses every statement afresh, and every
  value appears in full in the general and slow logs. The escaping is PDO's and is not
  suspected of being wrong; what is worth deciding is whether the replacement connection
  keeps the default. **Not known:** whether anything in the tree depends on emulation —
  MySQL cannot bind a parameter in every position a literal may appear, and a query that
  interpolates a column or a `LIMIT` would break under real prepares.

- **`SionTable::getChanges()` orders by a non-unique column under a `LIMIT`.**
  `module/SionModel/src/Db/Model/SionTable.php:1568` — `SELECT * FROM sch_changes WHERE
  ChangedEntity IN (…) ORDER BY UpdatedOn DESC LIMIT 250`, no tiebreaker. Measured
  2026-09-22: **163,905 rows share 9,789 distinct `UpdatedOn` values**, an average of 17 rows
  per timestamp, and **one timestamp carries 1,161 rows**. Whenever the 250-row boundary
  lands inside a tie band the rows chosen are whatever the server returns that time, so
  `/sm/view-changes` can show a different set on two identical requests and a row can be
  invisible on every one of them. The boundary currently falls on a band of 1, which is why
  nothing has been seen. `ChangeID` is the primary key and is monotonic with insertion, so a
  tiebreaker is available. The same page's rows are cached, so what this produces when it
  bites is a cached order that flaps — the failure mode that has already cost this
  repository a round of phantom regressions in `tools/port-baseline.php`.

- **`SionTable::getEntityChanges()` has no `LIMIT`.** Same file, line 1511 —
  `SELECT * FROM sch_changes WHERE ChangedEntity = ? AND ChangedIDValue = ? ORDER BY
  UpdatedOn DESC`, every change ever recorded for one record, on a table of 163,905 rows.
  The index `ChangedEntity (ChangedEntity, ChangedIDValue)` serves the lookup, so the cost
  is proportional to one entity's history rather than to the table. **Not known:** the
  largest history any single entity has; that number decides whether this matters at all.

- **`getChanges()` filters in PHP *after* the database applied the `LIMIT`, and keys the
  filter differently from the query.** Same method: the `WHERE` is built from
  `getTableEntities()`, which emits `$entitySpec->name ?? $key`, and the loop at 1583 then
  drops any row whose `$entitySpecifications[$entity]` is missing or belongs to another
  class — looking the row up **by key**. A spec whose `name` differs from its key therefore
  matches the query and fails the loop, and its rows are discarded silently after the 250
  have already been taken, so the page shows fewer rows than it asked for and no error says
  why. Measured 2026-09-22: **0 of 23 entity specifications set a `name` that differs from
  its key**, so this is latent, not live. It becomes live the day someone adds one.

- **`relatedAssociations()` builds `Parent IN ()` from an empty result set, and throws.**
  `SchoenstattTable::relatedAssociations()` opens with
  `$query = ['parentId' => array_keys($objects)]` (line 561). `SionTable::queryObjects()`
  turns any array value into an `In` (line 437), and `SionModel\Db\Sql\Predicate\In`
  refuses an empty set, correctly, because `sch_associations.Parent IN ()` is a syntax
  error. So an association query that legitimately matches **nothing** raises
  `InvalidPredicate` instead of returning no rows. The same method guards its other array
  eight lines below — `if (! empty($interestingIds))` — so one of the two was simply
  missed. The live path is `shrinesOfKind()`, which calls `queryObjects()` with a `kind`
  and then `linkAssociations()` on whatever came back; only `getAssociations()` passes
  `$objectsAreEveryAssociation = true` and skips it. Measured 2026-09-23 in the capsule:
  both kinds it is called with have rows — `sch-shrine` 207, `sch-wayside-shrine` 43 — so
  this is **latent, not live**, and it needs one kind to reach zero rows to become a 500 on
  `/shrines`. It is not latent against an empty database, where it is what makes 13 of the
  integration suite's tests error. **Not known:** whether any other caller reaches
  `linkAssociations()` with an empty set from a narrower filter than `kind`.

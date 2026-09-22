<?php

declare(strict_types=1);

namespace App\Books;

use Books\Model\LibraryTable;
use SionModel\Db\Connection;
use Throwable;

use function is_array;

/**
 * Deleting a library, and everything that belongs to it.
 *
 * ## Why this is not `SionTable::deleteEntity()`
 *
 * That method is a single-row `DELETE` on the entity's own table, and for a library that
 * is not a delete — it is an orphaning. Measured on the capsule 2026-09-08:
 *
 * | library | books | collections | imports | tokens | checkouts |
 * | --- | --- | --- | --- | --- | --- |
 * | Bellavista (1) | 4,604 | 4 | 0 | 0 | 4 |
 * | Colegio Mayor (3) | 12,394 | 3 | 14 | 0 | 1,937 |
 * | **PUC (4)** | **16,383** | 0 | 0 | 0 | 0 |
 * | Vaterhaus (5) | 0 | 0 | 0 | 0 | 0 |
 * | Fathers Austin (6) | 183 | 0 | 0 | 0 | 1 |
 * | University Men (7) | 126 | 0 | 0 | 0 | 11 |
 *
 * Of the four tables that carry a library id, **only `lib_imports` has a foreign key** —
 * `lib_imports_lib_libraries_FK`, `ON DELETE CASCADE`. `lib_books.library_id`,
 * `lib_collections.LibraryId` and `lib_borrower_tokens.LibraryId` have none, so the bare
 * row delete leaves them behind pointing at a library that does not exist. Those books
 * then appear on no page, belong to no catalogue, and cannot be reached or removed by any
 * screen in the application. Nothing errors; the rows simply become unreachable mass.
 *
 * So the cascade is explicit here, in one transaction, children first. `lib_imports` is
 * deleted by name rather than left to its constraint, purely so the count can be reported
 * — the constraint would have done it either way.
 *
 * ## Why the laminas delete stays disabled
 *
 * The obvious alternative was to set `enable_delete_action => true` on the `library`
 * entity and let `SionController::deleteAction()` handle the row while this class handled
 * the children. It is not done, and the reason is the rollback path: `SYMFONY_KERNEL=0`
 * hands `/libraries/{id}/delete` back to `LibrariesController`, which inherits that
 * generic action — and it would then delete the library row **without** any of the
 * cascade below. Leaving `enable_delete_action` commented out means laminas answers "this
 * entity cannot be deleted, please check the configuration", which is the correct answer
 * for a front controller that cannot do the job properly.
 *
 * The cost of that decision is that the change-log row and the cache invalidation
 * `deleteEntity()` would have provided have to be made here by hand. Both are, and both
 * matter: {@see self::delete()}.
 *
 * ## The change log records an aggregate, not 16,383 rows
 *
 * `sch_changes` already holds 113,591 `book` rows. One `entryDeleted` per destroyed book
 * would add 16,383 more through `reportChange()`, which inserts them one statement at a
 * time — and would record the deletion of rows that, for PUC, have **zero** change-log
 * entries between them, having been bulk-loaded and never once touched through the
 * application.
 *
 * So the library gets a real `entryDeleted` row carrying its name in `OldValue` — so the
 * log stays readable after the row it names is gone — and each dependent kind gets one
 * row with its count. That is a deliberate trade: the log says "this library was deleted
 * and it took 16,383 books with it" rather than enumerating them.
 */
final class LibraryDelete
{
    /**
     * The entity caches this invalidates, in the order the deletes happen.
     *
     * `borrower-token` is absent because there is no such entity: `lib_borrower_tokens` is
     * managed directly by Books\Model\BorrowerTokenTable and is not a SionModel entity, so
     * nothing caches it and nothing depends on it.
     */
    public const INVALIDATES = ['checkout', 'book', 'collection', 'library-import', 'library'];

    public function __construct(private readonly LibraryTable $table, private readonly Connection $adapter)
    {
    }

    /**
     * What deleting this library would destroy.
     *
     * `outstandingCheckouts` is a subset of `checkouts` and is reported separately because
     * it means something different: a checkout with a null `CheckedInOn` is a book somebody
     * physically has. It does not block the delete — the human confirming is the one who
     * knows whether that matters — but it must be on the page rather than folded into a
     * total.
     *
     * @return array{
     *     books: int,
     *     collections: int,
     *     checkouts: int,
     *     outstandingCheckouts: int,
     *     borrowerTokens: int,
     *     imports: int
     * }
     */
    public function dependents(int $libraryId): array
    {
        return [
            'books'                => $this->count('SELECT COUNT(*) c FROM lib_books WHERE library_id = ?', $libraryId),
            'collections'          => $this->count(
                'SELECT COUNT(*) c FROM lib_collections WHERE LibraryId = ?',
                $libraryId
            ),
            'checkouts'            => $this->count(
                'SELECT COUNT(*) c FROM lib_checkouts co '
                . 'JOIN lib_books b ON b.book_id = co.BookId WHERE b.library_id = ?',
                $libraryId
            ),
            'outstandingCheckouts' => $this->count(
                'SELECT COUNT(*) c FROM lib_checkouts co '
                . 'JOIN lib_books b ON b.book_id = co.BookId '
                . 'WHERE b.library_id = ? AND co.CheckedInOn IS NULL',
                $libraryId
            ),
            'borrowerTokens'       => $this->count(
                'SELECT COUNT(*) c FROM lib_borrower_tokens WHERE LibraryId = ?',
                $libraryId
            ),
            'imports'              => $this->count(
                'SELECT COUNT(*) c FROM lib_imports WHERE LibraryId = ?',
                $libraryId
            ),
        ];
    }

    /**
     * Delete the library and everything below it, in one transaction.
     *
     * Returns what was actually removed, which is what the flash message reports. The
     * counts are taken **before** the deletes rather than from the statements' affected-row
     * counts, so that the checkout figure is the joined count rather than what MariaDB
     * reports for a subquery delete.
     *
     * Ordering is load-bearing in two places:
     *
     *  - **checkouts before books**, because the checkout delete reaches its rows by joining
     *    `lib_books` — that is the only place a checkout's library is recorded, since
     *    `lib_checkouts` carries a `BookId` and no library id of its own. Reverse the two
     *    and it silently deletes nothing: there are no book rows left to join against, and
     *    a join matching nothing is not an error.
     *  - **cache invalidation after the commit**, not inside it. Expiring a key for a write
     *    that then rolls back is how a cache ends up describing a state the database never
     *    reached.
     *
     * @param non-empty-string $libraryName recorded in the change log, because the row that
     *                                      carries the name is the one being deleted
     * @return array{
     *     books: int,
     *     collections: int,
     *     checkouts: int,
     *     outstandingCheckouts: int,
     *     borrowerTokens: int,
     *     imports: int
     * }
     */
    public function delete(int $libraryId, string $libraryName): array
    {
        $counts     = $this->dependents($libraryId);
        $this->adapter->beginTransaction();
        try {
            //Children first. lib_imports would go by its ON DELETE CASCADE anyway; doing
            //it explicitly keeps all five in one readable list and inside this transaction.
            $this->execute(
                'DELETE co FROM lib_checkouts co JOIN lib_books b ON b.book_id = co.BookId WHERE b.library_id = ?',
                $libraryId
            );
            $this->execute('DELETE FROM lib_books WHERE library_id = ?', $libraryId);
            $this->execute('DELETE FROM lib_collections WHERE LibraryId = ?', $libraryId);
            $this->execute('DELETE FROM lib_borrower_tokens WHERE LibraryId = ?', $libraryId);
            $this->execute('DELETE FROM lib_imports WHERE LibraryId = ?', $libraryId);

            $affected = $this->execute('DELETE FROM lib_libraries WHERE LibraryId = ?', $libraryId);
            if (1 !== $affected) {
                //Same guard `deleteEntity()` makes, and for the same reason: anything but
                //one row means the id did not identify what the caller thought it did, and
                //the children are already gone.
                throw new LibraryDeleteFailed(
                    "Deleting library $libraryId affected $affected rows, expected exactly 1."
                );
            }

            $this->recordChanges($libraryId, $libraryName, $counts);

            $this->adapter->commit();
        } catch (Throwable $e) {
            $this->rollBack();

            throw $e instanceof LibraryDeleteFailed
                ? $e
                : new LibraryDeleteFailed("Deleting library $libraryId failed: " . $e->getMessage(), 0, $e);
        }

        //After the commit. See the docblock.
        foreach (self::INVALIDATES as $entity) {
            $this->table->removeDependentCacheItems($entity);
        }

        return $counts;
    }

    /**
     * One `entryDeleted` row for the library, one counted row per dependent kind.
     *
     * `oldValue` on the library row is its name: every other way of finding out what
     * library 4 was called reads `lib_libraries`, and that row is what this is recording
     * the destruction of.
     *
     * @param non-empty-string   $libraryName
     * @param array<string, int> $counts
     */
    private function recordChanges(int $libraryId, string $libraryName, array $counts): void
    {
        $changes = [[
            'entity'   => 'library',
            'field'    => 'entryDeleted',
            'id'       => $libraryId,
            'oldValue' => $libraryName,
        ]];

        //Only the kinds that actually had rows. A zero is not a change, and six rows saying
        //"0 collections were deleted" would make the log harder to read, not more complete.
        //outstandingCheckouts is excluded: it is a description of the checkouts figure, not
        //a separate set of rows, and logging both would double-count.
        foreach (['books', 'collections', 'checkouts', 'borrowerTokens', 'imports'] as $kind) {
            if (0 === ($counts[$kind] ?? 0)) {
                continue;
            }
            $changes[] = [
                'entity'   => 'library',
                'field'    => $kind . 'Deleted',
                'id'       => $libraryId,
                'newValue' => (string) $counts[$kind],
            ];
        }

        //SionTable::reportChange() declares `@param string[][]`, which is not what any of
        //its callers pass — `deleteEntity()` hands it an int `id` too. The runtime shape is
        //correct; the docblock in the submodule is not, and correcting it there is a
        //separate change with its own review.
        /** @phpstan-ignore argument.type */
        $this->table->reportChange($changes);
    }

    private function count(string $sql, int $libraryId): int
    {
        $row = $this->adapter->select($sql, [$libraryId])->current();

        return null === $row ? 0 : (int) ($row['c'] ?? 0);
    }

    /** @return int rows affected */
    private function execute(string $sql, int $libraryId): int
    {
        return $this->adapter->execute($sql, [$libraryId]);
    }

    /**
     * A rollback that cannot itself become the reported failure.
     *
     * If the connection has already gone away, `rollback()` throws — and that exception
     * would replace the one that explains what actually went wrong. The original is what
     * the caller needs.
     */
    private function rollBack(): void
    {
        try {
            $this->adapter->rollBack();
        } catch (Throwable) {
            //deliberately swallowed; see above
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Privacy;

use RuntimeException;
use SionModel\Db\Connection;

use function addcslashes;
use function array_fill;
use function array_map;
use function count;
use function implode;
use function in_array;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function trim;

/**
 * The privacy policy's right to be forgotten, for one person or one account.
 *
 * {@see plan()} says what would happen and changes nothing; {@see apply()} does it in one
 * transaction. Both take the same target, so a dry run and the real one cannot disagree about
 * what they cover. docs/privacy.md has the rules; the ones that are judgement calls:
 *
 * - **A person with a book still out is refused.** The loan is the library's record of its
 *   own property; return or write off the book first.
 * - **An author keeps their name.** Someone named as the author, editor, translator or
 *   illustrator of a publication or text keeps FirstName, LastName and the author flag —
 *   authorship of a published work is a public bibliographic fact — and loses everything
 *   else. Anyone else's row is deleted.
 * - **An account is deleted, and its name comes off everything it did.** Every attribution
 *   column in the schema that holds its id — `UpdatedBy`, `CreatedBy`, `RecordedBy`,
 *   `issued_by` and the rest, found in information_schema rather than listed, so a column
 *   added later is covered — is set to NULL. Its comments are deleted.
 * - **The translation tables are shared between projects** (`trans_*` holds patres' rows too,
 *   with patres' own user ids), so they are never swept by column name: each is updated
 *   explicitly, scoped to this project's `project_name`.
 * - **Free text is reported, not rewritten.** Notes and translations that mention the
 *   person's name or address are listed for a person to edit: rewriting prose by pattern
 *   is how you corrupt it.
 *
 * Plain SQL for the reason {@see ContactRetention} gives; the caller flushes the web cache.
 */
final class Erasure
{
    /** Kept on an author's row; every other column is cleared. */
    private const AUTHOR_KEEPS = [
        'PersonId', 'FirstName', 'LastName', 'FirstNameWithoutAccents', 'LastNameWithoutAccents',
        'IsAuthor', 'UpdatedOn', 'CreatedOn',
    ];

    /** Authorship predicates: `person-<verb>-publication` and `person-<verb>-text`. */
    private const AUTHORSHIP = [
        'person-authored-publication', 'person-authored-text', 'person-edited-publication',
        'person-edited-text', 'person-illustrated-publication', 'person-translated-publication',
    ];

    /** Kept on an author's change-log rows, whose other values are blanked. */
    private const AUTHOR_FIELDS = ['firstName', 'lastName', 'isAuthor'];

    /** Where free text can name someone: table => [key column, text columns]. */
    private const FREE_TEXT = [
        'sch_persons'      => ['PersonId', ['PublicNotes', 'AdminNotes', 'ContactNotes']],
        'sch_associations' => ['AssociationId', ['PublicNotes', 'AdminNotes']],
        'sch_publications' => ['PublicationId', ['PublicNotes', 'AdminNotes', 'Description']],
        'relationships'    => ['RelationshipId', ['PublicNotes', 'AdminNotes']],
        'lib_checkouts'    => ['CheckoutId', ['AdminNotes']],
        'lib_libraries'    => ['LibraryId', ['ContactPerson', 'ContactEmail']],
        'comments'         => ['CommentId', ['Comment']],
    ];

    /**
     * @param string $project JTranslate's `project_name`: the rows of the shared translation
     *        tables that are this application's
     */
    public function __construct(private readonly Connection $db, private readonly string $project)
    {
    }

    /**
     * What erasing `$personId` (and its accounts) or `$userId` would do.
     *
     * @return array{
     *     person: ?array{id: int, author: bool, action: string},
     *     accounts: list<int>,
     *     counts: array<string, int>,
     *     mentions: list<array{table: string, column: string, id: int|string}>
     * }
     * @throws RuntimeException when there is nothing to erase, or a book is still out
     */
    public function plan(?int $personId, ?int $userId = null): array
    {
        [$person, $accounts] = $this->target($personId, $userId);
        $counts = [];
        $mentions = [];

        if (null !== $person) {
            $id = $person['id'];
            $counts['assignments'] = $this->countWhere('sch_assignments', 'PersonId = ?', [$id]);
            $counts['loans'] = $this->countWhere('lib_checkouts', 'PersonId = ?', [$id]);
            $counts['borrower links'] = $this->countWhere('lib_borrower_tokens', 'PersonId = ?', [$id]);
            $counts['relationships'] = $this->count(
                'SELECT COUNT(*) AS n FROM relationships r ' . $this->relationshipScope($person['author']),
                [$id, $id]
            );
            $counts['record history'] = $this->count(
                "SELECT COUNT(*) AS n FROM sch_changes WHERE ChangedEntity = 'person' AND ChangedIDValue = ?",
                [$id]
            );
            $counts['sources'] = $this->count(
                "SELECT COUNT(*) AS n FROM sch_provenance WHERE Entity = 'person' AND EntityId = ?",
                [$id]
            );
            $mentions = $this->mentions($id);
        }
        foreach ($accounts as $userId) {
            $counts['comments'] = ($counts['comments'] ?? 0)
                + $this->count('SELECT COUNT(*) AS n FROM comments WHERE CreatedBy = ?', [$userId]);
            foreach ($this->attributionColumns() as [$table, $column]) {
                $n = $this->countWhere($table, sprintf('`%s` = ?', $column), [$userId]);
                if ($n > 0) {
                    $key = 'attributions in ' . $table;
                    $counts[$key] = ($counts[$key] ?? 0) + $n;
                }
            }
            $n = $this->count(
                'SELECT COUNT(*) AS n FROM trans_translations t
                 JOIN trans_phrases p ON p.translation_phrase_id = t.translation_phrase_id
                 WHERE t.modified_by = ? AND p.project = ?',
                [$userId, $this->project]
            ) + $this->count(
                'SELECT COUNT(*) AS n FROM trans_translations_history
                 WHERE (written_by = ? OR replaced_by = ?) AND project = ?',
                [$userId, $userId, $this->project]
            );
            if ($n > 0) {
                $counts['attributions in translations'] = ($counts['attributions in translations'] ?? 0) + $n;
            }
        }

        return ['person' => $person, 'accounts' => $accounts, 'counts' => $counts, 'mentions' => $mentions];
    }

    /**
     * Erase what {@see plan()} describes for the same arguments.
     *
     * @return array{person: ?array{id: int, author: bool, action: string}, accounts: list<int>}
     */
    public function apply(?int $personId, ?int $userId = null): array
    {
        [$person, $accounts] = $this->target($personId, $userId);

        $ownTransaction = ! $this->db->inTransaction();
        if ($ownTransaction) {
            $this->db->beginTransaction();
        }
        try {
            if (null !== $person) {
                $this->erasePerson($person['id'], $person['author']);
            }
            foreach ($accounts as $account) {
                $this->eraseAccount($account);
            }
            if ($ownTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return ['person' => $person, 'accounts' => $accounts];
    }

    private function erasePerson(int $id, bool $author): void
    {
        $this->db->execute('DELETE FROM sch_assignments WHERE PersonId = ?', [$id]);
        $this->db->execute('DELETE FROM lib_checkouts WHERE PersonId = ?', [$id]);
        $this->db->execute('DELETE FROM lib_borrower_tokens WHERE PersonId = ?', [$id]);
        $this->db->execute('DELETE r FROM relationships r ' . $this->relationshipScope($author), [$id, $id]);
        $this->db->execute("DELETE FROM sch_provenance WHERE Entity = 'person' AND EntityId = ?", [$id]);
        $this->db->execute('UPDATE sch_persons SET SpousePersonId = NULL WHERE SpousePersonId = ?', [$id]);
        $this->db->execute(
            'UPDATE lib_libraries SET DefaultCheckoutPersonId = NULL WHERE DefaultCheckoutPersonId = ?',
            [$id]
        );

        if (! $author) {
            $this->db->execute("DELETE FROM sch_changes WHERE ChangedEntity = 'person' AND ChangedIDValue = ?", [$id]);
            $this->db->execute('DELETE FROM sch_persons WHERE PersonId = ?', [$id]);

            return;
        }

        $this->db->execute(
            "UPDATE sch_changes SET OldValue = NULL, NewValue = NULL
             WHERE ChangedEntity = 'person' AND ChangedIDValue = ?
               AND ChangedField NOT IN (" . self::placeholders(self::AUTHOR_FIELDS) . ')',
            [$id, ...self::AUTHOR_FIELDS]
        );
        $set = [];
        foreach ($this->personColumns() as $column => $nullable) {
            if (in_array($column, self::AUTHOR_KEEPS, true)) {
                continue;
            }
            $set[] = $nullable ? sprintf('`%s` = NULL', $column) : sprintf('`%s` = DEFAULT(`%s`)', $column, $column);
        }
        $this->db->execute('UPDATE sch_persons SET ' . implode(', ', $set) . ' WHERE PersonId = ?', [$id]);
    }

    private function eraseAccount(int $userId): void
    {
        $comments = array_map(
            static fn (array $row): int => (int) $row['CommentId'],
            $this->rows('SELECT CommentId FROM comments WHERE CreatedBy = ?', [$userId])
        );
        if ([] !== $comments) {
            $in = self::placeholders($comments);
            $this->db->execute(
                "DELETE r FROM relationships r JOIN predicates p ON p.PredicateKind = r.PredicateKind
                 WHERE p.SubjectEntityKind = 'comment' AND r.SubjectEntityId IN (" . $in . ')',
                $comments
            );
            $this->db->execute('DELETE FROM comments WHERE CommentId IN (' . $in . ')', $comments);
        }
        foreach ($this->attributionColumns() as [$table, $column]) {
            //Counted first: none of these columns is indexed, and an UPDATE that matches
            //nothing still scans and locks every row — two seconds on `texts` — while the
            //COUNT costs milliseconds. Most accounts touched a handful of tables.
            if (0 === $this->countWhere($table, sprintf('`%s` = ?', $column), [$userId])) {
                continue;
            }
            $this->db->execute(
                sprintf('UPDATE `%s` SET `%s` = NULL WHERE `%s` = ?', $table, $column, $column),
                [$userId]
            );
        }
        $this->db->execute(
            'UPDATE trans_translations t JOIN trans_phrases p ON p.translation_phrase_id = t.translation_phrase_id
             SET t.modified_by = NULL WHERE t.modified_by = ? AND p.project = ?',
            [$userId, $this->project]
        );
        $this->db->execute(
            'UPDATE trans_translations_history SET written_by = NULL WHERE written_by = ? AND project = ?',
            [$userId, $this->project]
        );
        $this->db->execute(
            'UPDATE trans_translations_history SET replaced_by = NULL WHERE replaced_by = ? AND project = ?',
            [$userId, $this->project]
        );
        $this->db->execute('DELETE FROM user_api_token WHERE user_id = ?', [$userId]);
        $this->db->execute('DELETE FROM user_role_linker WHERE user_id = ?', [$userId]);
        $this->db->execute('DELETE FROM user WHERE user_id = ?', [$userId]);
    }

    /**
     * @return array{0: ?array{id: int, author: bool, action: string}, 1: list<int>}
     */
    private function target(?int $personId, ?int $userId): array
    {
        if (null === $personId && null !== $userId) {
            $row = $this->db->select('SELECT PersID FROM user WHERE user_id = ?', [$userId])->current();
            if (null === $row) {
                throw new RuntimeException(sprintf('There is no account %d.', $userId));
            }
            $linked = (int) ($row['PersID'] ?? 0);
            if ($linked <= 0) {
                return [null, [$userId]];
            }
            $personId = $linked;
        }
        if (null === $personId) {
            throw new RuntimeException('Name a person or an account.');
        }

        if (null === $this->db->select('SELECT PersonId FROM sch_persons WHERE PersonId = ?', [$personId])->current()) {
            throw new RuntimeException(sprintf('There is no person %d.', $personId));
        }
        $out = $this->countWhere('lib_checkouts', 'PersonId = ? AND CheckedInOn IS NULL', [$personId]);
        if ($out > 0) {
            throw new RuntimeException(sprintf(
                'Person %d has %d book(s) still out. Check them in or write them off first: the loan is the '
                . "library's record of its own property.",
                $personId,
                $out
            ));
        }

        $author = $this->isAuthor($personId);
        $accounts = array_map(
            static fn (array $row): int => (int) $row['user_id'],
            $this->rows('SELECT user_id FROM user WHERE PersID = ? ORDER BY user_id', [$personId])
        );

        return [
            ['id' => $personId, 'author' => $author, 'action' => $author ? 'keep the name as an author' : 'delete'],
            $accounts,
        ];
    }

    private function isAuthor(int $personId): bool
    {
        $row = $this->db->select('SELECT IsAuthor FROM sch_persons WHERE PersonId = ?', [$personId])->current();
        if (null !== $row && (int) $row['IsAuthor'] === 1) {
            return true;
        }

        return $this->count(
            'SELECT COUNT(*) AS n FROM sch_publications WHERE Authors REGEXP ?',
            [AuthorLinks::pattern($personId)]
        ) > 0 || $this->count(
            'SELECT COUNT(*) AS n FROM relationships WHERE SubjectEntityId = ? AND PredicateKind IN ('
            . self::placeholders(self::AUTHORSHIP) . ')',
            [$personId, ...self::AUTHORSHIP]
        ) > 0;
    }

    /**
     * The relationships a person's erasure removes: every one naming them as a person, except
     * the authorship ones when they are kept as an author. Takes the person id twice.
     */
    private function relationshipScope(bool $author): string
    {
        $scope = "JOIN predicates p ON p.PredicateKind = r.PredicateKind
                  WHERE ((p.SubjectEntityKind = 'person' AND r.SubjectEntityId = ?)
                      OR (p.ObjectEntityKind = 'person' AND r.ObjectEntityId = ?))";
        if ($author) {
            $scope .= " AND r.PredicateKind NOT IN ('" . implode("', '", self::AUTHORSHIP) . "')";
        }

        return $scope;
    }

    /**
     * Free text that names the person or quotes their email address.
     *
     * @return list<array{table: string, column: string, id: int|string}>
     */
    private function mentions(int $personId): array
    {
        $person = $this->db->select(
            'SELECT FirstName, LastName, Email, Email2 FROM sch_persons WHERE PersonId = ?',
            [$personId]
        )->current();
        $needles = [];
        $name = trim(((string) ($person['FirstName'] ?? '')) . ' ' . ((string) ($person['LastName'] ?? '')));
        if (str_contains($name, ' ')) {
            $needles[] = $name;
        }
        foreach (['Email', 'Email2'] as $column) {
            if ('' !== (string) ($person[$column] ?? '')) {
                $needles[] = (string) $person[$column];
            }
        }
        if ([] === $needles) {
            return [];
        }

        $found = [];
        foreach (self::FREE_TEXT as $table => [$key, $columns]) {
            foreach ($columns as $column) {
                $where = implode(' OR ', array_fill(0, count($needles), sprintf('`%s` LIKE ?', $column)));
                $values = array_map(
                    static fn (string $needle): string => '%' . self::escapeLike($needle) . '%',
                    $needles
                );
                if ('sch_persons' === $table) {
                    $where = '(' . $where . ') AND PersonId <> ?';
                    $values[] = (string) $personId;
                }
                $sql = sprintf('SELECT `%s` AS id FROM `%s` WHERE %s', $key, $table, $where);
                foreach ($this->rows($sql, $values) as $row) {
                    $found[] = ['table' => $table, 'column' => $column, 'id' => $row['id']];
                }
            }
        }
        $likes = array_map(static fn (string $needle): string => '%' . self::escapeLike($needle) . '%', $needles);
        $any = static fn (string $column): string => implode(
            ' OR ',
            array_fill(0, count($likes), $column . ' LIKE ?')
        );
        foreach (
            $this->rows(
                'SELECT translation_phrase_id AS id FROM trans_phrases WHERE project = ? AND (' . $any('phrase') . ')',
                [$this->project, ...$likes]
            ) as $row
        ) {
            $found[] = ['table' => 'trans_phrases', 'column' => 'phrase', 'id' => $row['id']];
        }
        foreach (
            $this->rows(
                'SELECT t.translation_id AS id FROM trans_translations t
                 JOIN trans_phrases p ON p.translation_phrase_id = t.translation_phrase_id
                 WHERE p.project = ? AND (' . $any('t.translation') . ')',
                [$this->project, ...$likes]
            ) as $row
        ) {
            $found[] = ['table' => 'trans_translations', 'column' => 'translation', 'id' => $row['id']];
        }

        return $found;
    }

    /**
     * Every integer column named like an attribution — `…By` or `…_by` — in the schema.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function attributionColumns(): array
    {
        $rows = $this->rows(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME REGEXP BINARY '(By|_by)$'
               AND DATA_TYPE IN ('tinyint', 'smallint', 'mediumint', 'int', 'bigint')
             ORDER BY TABLE_NAME, COLUMN_NAME",
            []
        );

        $columns = [];
        foreach ($rows as $row) {
            //shared between projects: handled explicitly, scoped to this one
            if (! str_starts_with((string) $row['TABLE_NAME'], 'trans_')) {
                $columns[] = [(string) $row['TABLE_NAME'], (string) $row['COLUMN_NAME']];
            }
        }

        return $columns;
    }

    /**
     * @return array<string, bool> column => nullable
     */
    private function personColumns(): array
    {
        $columns = [];
        foreach (
            $this->rows(
                "SELECT COLUMN_NAME, IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sch_persons' ORDER BY ORDINAL_POSITION",
                []
            ) as $row
        ) {
            $columns[(string) $row['COLUMN_NAME']] = 'YES' === $row['IS_NULLABLE'];
        }

        return $columns;
    }

    /**
     * @param list<int|string> $values
     */
    private function countWhere(string $table, string $condition, array $values): int
    {
        return $this->count(sprintf('SELECT COUNT(*) AS n FROM `%s` WHERE %s', $table, $condition), $values);
    }

    /**
     * @param list<int|string> $values
     */
    private function count(string $sql, array $values): int
    {
        $row = $this->db->select($sql, $values)->current();

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param list<int|string> $values
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $values): array
    {
        $rows = [];
        foreach ($this->db->select($sql, $values) as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param list<mixed> $values
     */
    private static function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    private static function escapeLike(string $needle): string
    {
        return addcslashes($needle, '%_\\');
    }
}

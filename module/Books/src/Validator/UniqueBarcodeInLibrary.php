<?php

declare(strict_types=1);

namespace Books\Validator;

use SionModel\Db\Connection;
use SionModel\Db\Sql\Predicate\Operator;
use SionModel\Db\Sql\Select;
use SionModel\Validator\AbstractValidator;
use SionModel\Validator\Exception\RuntimeException;

use function is_string;
use function trim;

/**
 * No other book in this library already carries this barcode.
 *
 * `lib_books` has `UNIQUE (library_id, original_id)`, and `original_id` is what the form
 * calls `withinLibraryId` and a librarian calls the barcode. Until 2026-09-23 that index was
 * the only thing enforcing it: the specification gave the field the integer filters and a
 * number validator and nothing else, so a collision travelled all the way to MariaDB and
 * came back as `SQLSTATE[23000] … Duplicate entry '1-52355' for key 'library_id'` out of
 * `SionTable::updateHelper()` — a 500 on a page that had already told the editor their
 * submission was fine. **479 occurrences between 2026-08-17 and 2026-09-22**, one librarian,
 * with no way to see what was wrong (#286).
 *
 * ## What it compares, and what it deliberately does not
 *
 * The three terms are the index's two columns plus the row being edited. **`is_active` is
 * not one of them**: the index does not care, so an inactive book still occupies its
 * barcode, and a rule that ignored inactive rows would pass and then fail at the database —
 * which is the whole failure being fixed. Matching the constraint exactly is the point.
 *
 * An empty barcode is valid *here*. `withinLibraryId` is `required`, so an empty submission
 * is already reported by the required check, and answering it a second time with "that
 * barcode is taken" would be both wrong and confusing. Whether a barcode is mandatory is
 * `getInputFilterSpecification()`'s business, not this rule's.
 *
 * ## The comparison is a string one
 *
 * `original_id` is `varchar(20)`, and the filters ahead of this rule hand it an `int`.
 * Comparing the column to an integer makes MariaDB coerce the *column*, which both widens
 * the match (`' 42'` and `'42x'` are numerically 42, and neither collides in the index) and
 * throws the index away — a full scan of `lib_books` on every save. Casting the value to a
 * string instead asks exactly the question `UNIQUE (library_id, original_id)` answers.
 *
 * ## The library comes from the form, never from the submission
 *
 * `BookForm::setData()` overwrites `libraryId` with `$this->libraryOptions->libraryId`
 * before anything validates — measured: a POST carrying `libraryId=3` for a book in library
 * 1 still yields `1` from `getData()`. So the scope is a server-side fact, and this
 * validator is handed it rather than reading `$context`, where the submitted value would be
 * the attacker's.
 *
 * ## It is not the last line of defence
 *
 * Two editors can pass this validator at the same moment and one of them will still meet the
 * index. That is a narrow race and the database is right to refuse it; what this removes is
 * the ordinary case, where the barcode was simply already taken and the editor could have
 * been told so.
 */
final class UniqueBarcodeInLibrary extends AbstractValidator
{
    public const ERROR_TAKEN = 'barcodeTaken';

    private const TABLE  = 'lib_books';
    private const FIELD  = 'original_id';
    private const SCOPE  = 'library_id';
    private const KEY    = 'book_id';
    private const LABEL  = 'title';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::ERROR_TAKEN => 'Barcode %value% already belongs to another book in this library (%conflict%). '
            . 'Every barcode must be unique within a library.',
    ];

    /** @var array<string, mixed> */
    protected $messageVariables = [
        'conflict' => 'conflict',
    ];

    /** How the message names the book that already holds the barcode. */
    protected string $conflict = '';

    private ?Connection $adapter = null;

    private ?int $libraryId = null;

    /** The row being edited, which cannot collide with itself; null on a create. */
    private ?int $excludeBookId = null;

    public function setAdapter(mixed $adapter): static
    {
        if (! $adapter instanceof Connection) {
            throw new RuntimeException('UniqueBarcodeInLibrary needs a database connection');
        }

        $this->adapter = $adapter;

        return $this;
    }

    public function setLibraryId(mixed $libraryId): static
    {
        $this->libraryId = null === $libraryId ? null : (int) $libraryId;

        return $this;
    }

    public function setExcludeBookId(mixed $bookId): static
    {
        $this->excludeBookId = null === $bookId ? null : (int) $bookId;

        return $this;
    }

    /**
     * The query this validator runs, for one barcode.
     *
     * Public, and called with no argument, for the same reason `SionModel\Validator\Db`'s is:
     * `test/Rules/rule-surface.php` records the statement rather than a verdict, because a
     * changed `WHERE` here reads to a librarian as "that barcode is taken" and refuses a
     * perfectly good one — a verdict-based test cannot tell that from the barcode genuinely
     * being taken.
     */
    public function getSelect(mixed $value = null): Select
    {
        $select = (new Select(self::TABLE))->columns([self::KEY, self::LABEL]);
        $select->where(new Operator(self::FIELD, Operator::EQ, null === $value ? null : (string) $value));
        $select->where(new Operator(self::SCOPE, Operator::EQ, $this->libraryId));

        if (null !== $this->excludeBookId) {
            $select->where(new Operator(self::KEY, Operator::NEQ, $this->excludeBookId));
        }

        return $select;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        $this->setValue($value);

        if (null === $value || '' === $value) {
            return true;
        }

        if (null === $this->adapter || null === $this->libraryId) {
            //Not a silent pass. A uniqueness rule that cannot ask the database is the
            //failure mode this class exists to end, and the one JUser's forms met when they
            //read their adapter out of a static registry: the check vanished and the
            //submission validated clean.
            throw new RuntimeException(
                'UniqueBarcodeInLibrary needs both a connection and a libraryId; '
                . 'BookForm supplies them from its library options.'
            );
        }

        $row = $this->adapter->select($this->getSelect($value))->current();
        if (null === $row) {
            return true;
        }

        $this->conflict = $this->describe($row);
        $this->error(self::ERROR_TAKEN);

        return false;
    }

    /**
     * The conflicting book, as a librarian would recognise it.
     *
     * The title, because that is what the shelf says; the id as well, because two copies of
     * one work share a title and the editor needs to know which row to go and look at.
     *
     * @param array<string, mixed> $row
     */
    private function describe(array $row): string
    {
        /** @var mixed $title */
        $title = $row[self::LABEL] ?? null;
        $title = is_string($title) ? trim($title) : '';
        $id    = (int) ($row[self::KEY] ?? 0);

        return '' === $title ? "book #$id" : "$title, book #$id";
    }
}

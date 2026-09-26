<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ContainerFactory;
use App\Privacy\ContactRetention;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use SionModel\Db\Connection;
use Throwable;

use function array_column;
use function array_diff;
use function sprintf;
use function array_values;
use function in_array;
use function is_readable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The retention rule for contact data, against the real schema.
 *
 * Every case is its own fixture person, written inside a transaction that is rolled back,
 * so the test touches no row it did not make and leaves none behind. apply() joins the
 * caller's transaction rather than opening its own, which is what makes that possible.
 */
final class ContactRetentionTest extends TestCase
{
    /**
     * `sch_persons` columns that are deliberately NOT contact data. A new column has to be
     * put on one list or the other, so contact data cannot be added that retention forgets.
     */
    private const KEPT_COLUMNS = [
        'PersonId', 'LastName', 'FirstName', 'LastNameWithoutAccents', 'FirstNameWithoutAccents',
        'PersonTags', 'LifeCommunity', 'Title', 'TitleAutomatic', 'Country', 'DeathDate',
        'DeathDatePrecision', 'PublicNotes', 'PublicNotesUpdatedOn', 'PublicNotesUpdatedBy',
        'PersonalInfoUpdatedOn', 'PersonalInfoUpdatedBy', 'AdminTags', 'AdminNotes',
        'AdminNotesUpdatedOn', 'AdminNotesUpdatedBy', 'ContactInfoUpdatedOn', 'ContactInfoUpdatedBy',
        'DataSource', 'DataSourceId', 'DataSourceUpdatedOn', 'UpdatedOn', 'UpdatedBy', 'CreatedOn',
        'CreatedBy', 'SpousePersonId', 'PrimaryLocale', 'IsAuthor', 'IsBorrower',
    ];

    private Connection $db;
    private ContactRetention $retention;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $container = ContainerFactory::build($appConfig);
        try {
            /** @var Connection $db */
            $db = $container->get(Connection::class);
            $db->select('SELECT 1');
        } catch (Throwable $e) {
            self::markTestSkipped('no reachable database: ' . $e->getMessage());
        }
        $this->db = $db;
        $this->retention = new ContactRetention($db);
        $this->now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testEverySchPersonsColumnIsEitherContactDataOrKeptOnPurpose(): void
    {
        $columns = [];
        foreach (
            $this->db->select(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sch_persons'"
            ) as $row
        ) {
            $columns[] = (string) $row['COLUMN_NAME'];
        }
        $classified = [...array_values(ContactRetention::CONTACT_FIELDS), ...self::KEPT_COLUMNS];

        self::assertSame(
            [],
            array_values(array_diff($columns, $classified)),
            'a sch_persons column is neither ContactRetention::CONTACT_FIELDS nor KEPT_COLUMNS: '
            . 'decide whether it is a way to reach a person'
        );
        self::assertSame(
            [],
            array_values(array_diff($classified, $columns)),
            'a classified column no longer exists in sch_persons'
        );
    }

    public function testTheRuleFollowsLastActivityAndSparesAnythingCurrent(): void
    {
        //name => [due?, the fixture person]
        $cases = [
            'assignment ended six years ago'
                => [true, $this->person(contactYearsAgo: 7, assignmentEndedYearsAgo: 6)],
            'assignment ended four years ago'
                => [false, $this->person(contactYearsAgo: 7, assignmentEndedYearsAgo: 4)],
            'assignment with no end'
                => [false, $this->person(contactYearsAgo: 9, assignmentOpen: true)],
            'assignment ending in the future'
                => [false, $this->person(contactYearsAgo: 9, assignmentEndedYearsAgo: -1)],
            'borrower, last book back six years ago'
                => [true, $this->person(contactYearsAgo: 8, loanReturnedYearsAgo: 6)],
            'borrower, last book back two years ago'
                => [false, $this->person(contactYearsAgo: 8, loanReturnedYearsAgo: 2)],
            'borrower with a book still out'
                => [false, $this->person(contactYearsAgo: 9, loanOpenSinceYearsAgo: 9)],
            'no assignment, edited two years ago'
                => [false, $this->person(contactYearsAgo: 2)],
            'no assignment, never edited, old'
                => [true, $this->person(contactYearsAgo: null, createdYearsAgo: 6)],
            'nothing left to erase'
                => [false, $this->person(contactYearsAgo: 9, email: null)],
        ];

        $due = array_column($this->retention->due($this->now), 'personId');
        foreach ($cases as $name => [$expected, $personId]) {
            self::assertSame($expected, in_array($personId, $due, true), $name);
        }
    }

    public function testApplyErasesContactColumnsAndChangeLogValuesButKeepsTheRest(): void
    {
        $gone = $this->person(contactYearsAgo: 7, assignmentEndedYearsAgo: 6);
        $kept = $this->person(contactYearsAgo: 1, assignmentOpen: true);

        $this->change($gone, 'email', 'old@example.com', 'new@example.com', yearsAgo: 1);
        $this->change($gone, 'firstName', 'Old', 'New', yearsAgo: 1);
        $this->change($kept, 'phone1', '111', '222', yearsAgo: 1);
        $this->change($kept, 'phone1', '000', '111', yearsAgo: 6);

        $due = array_column($this->retention->due($this->now), 'personId');
        self::assertContains($gone, $due);
        self::assertNotContains($kept, $due);

        //Only the fixture: the database's own due persons would make the statement's IN
        //list as long as they are many, and tools/sql-surface.sh records its shape.
        $this->retention->apply($this->now, [$gone]);

        $row = $this->db->select('SELECT * FROM sch_persons WHERE PersonId = ?', [$gone])->current();
        foreach (ContactRetention::CONTACT_FIELDS as $column) {
            self::assertContains($row[$column], [null, 0, '0'], $column . ' survived');
        }
        self::assertSame('Fixture', $row['FirstName'], 'a kept column was erased');
        self::assertNotNull($row['ContactInfoUpdatedOn'], 'the erasure is not stamped');

        $keptRow = $this->db->select('SELECT Email FROM sch_persons WHERE PersonId = ?', [$kept])->current();
        self::assertSame('fixture@example.com', $keptRow['Email']);

        self::assertSame(
            [
                [$gone, 'email', null, null],
                [$gone, 'firstName', 'Old', 'New'],
                [$kept, 'phone1', null, null],
                [$kept, 'phone1', '111', '222'],
            ],
            $this->changes([$gone, $kept])
        );
    }

    /**
     * A fixture person with an email address, and whatever history the case needs. Years
     * are counted back from now; a negative one is in the future.
     */
    private function person(
        ?int $contactYearsAgo,
        int $createdYearsAgo = 10,
        ?int $assignmentEndedYearsAgo = null,
        bool $assignmentOpen = false,
        ?int $loanReturnedYearsAgo = null,
        ?int $loanOpenSinceYearsAgo = null,
        ?string $email = 'fixture@example.com',
    ): int {
        $this->db->execute(
            //split so that the part tools/sql-test-literals.php reads is a prefix of what the
            //server receives, whichever of the values are strings: that is how
            //tools/sql-surface.sh knows this statement is the test's and not the application's
            'INSERT INTO sch_persons (FirstName, LastName, Email, CellPhoneHasWhatsApp, ContactInfoUpdatedOn, '
            . 'CreatedOn)'
            . ' VALUES (?, ?, ?, ?, ?, ?)',
            [
                'Fixture',
                'Retention',
                $email,
                null === $email ? 0 : 1,
                null === $contactYearsAgo ? null : $this->yearsAgo($contactYearsAgo),
                $this->yearsAgo($createdYearsAgo),
            ]
        );
        $personId = $this->db->lastInsertId();

        if ($assignmentOpen || null !== $assignmentEndedYearsAgo) {
            $this->db->execute(
                'INSERT INTO sch_assignments (RoleId, PersonId, StartDate, EndDate)' . ' VALUES (?, ?, ?, ?)',
                [
                    0,
                    $personId,
                    $this->yearsAgo(20, 'Y-m-d'),
                    $assignmentOpen ? null : $this->yearsAgo((int) $assignmentEndedYearsAgo, 'Y-m-d'),
                ]
            );
        }
        if (null !== $loanReturnedYearsAgo) {
            $this->db->execute(
                'INSERT INTO lib_checkouts (PersonId, BookId, CheckedOutOn, CheckedInOn)' . ' VALUES (?, ?, ?, ?)',
                [$personId, 0, $this->yearsAgo($loanReturnedYearsAgo + 1), $this->yearsAgo($loanReturnedYearsAgo)]
            );
        }
        if (null !== $loanOpenSinceYearsAgo) {
            $this->db->execute(
                'INSERT INTO lib_checkouts (PersonId, BookId, CheckedOutOn)' . ' VALUES (?, ?, ?)',
                [$personId, 0, $this->yearsAgo($loanOpenSinceYearsAgo)]
            );
        }

        return $personId;
    }

    private function change(int $personId, string $field, ?string $old, ?string $new, int $yearsAgo): void
    {
        $this->db->execute(
            'INSERT INTO sch_changes (ChangedEntity, ChangedField, ChangedIDValue, OldValue, NewValue, UpdatedOn)'
            . " VALUES ('person', ?, ?, ?, ?, ?)",
            [$field, $personId, $old, $new, $this->yearsAgo($yearsAgo)]
        );
    }

    /**
     * @param list<int> $personIds
     * @return list<array{0: int, 1: string, 2: ?string, 3: ?string}>
     */
    private function changes(array $personIds): array
    {
        $rows = [];
        foreach (
            $this->db->select(
                "SELECT ChangedIDValue, ChangedField, OldValue, NewValue FROM sch_changes
                 WHERE ChangedEntity = 'person' AND ChangedIDValue IN (?, ?) AND ChangedField <> 'newEntry'
                 ORDER BY ChangedIDValue, ChangedField, UpdatedOn",
                $personIds
            ) as $row
        ) {
            $rows[] = [
                (int) $row['ChangedIDValue'],
                (string) $row['ChangedField'],
                null === $row['OldValue'] ? null : (string) $row['OldValue'],
                null === $row['NewValue'] ? null : (string) $row['NewValue'],
            ];
        }

        return $rows;
    }

    private function yearsAgo(int $years, string $format = 'Y-m-d H:i:s'): string
    {
        return $this->now->modify(sprintf('%+d years', -$years))->format($format);
    }
}

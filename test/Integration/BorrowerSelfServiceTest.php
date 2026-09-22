<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ContainerFactory;
use Books\Model\BorrowerTokenTable;
use Books\Model\LibraryTable;
use DateTimeImmutable;
use DateTimeZone;
use SionModel\Db\Connection;
use App\Services\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

use function bin2hex;
use function is_readable;
use function random_bytes;
use function str_repeat;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Borrower self-service: the renewal rules, and the scope of the emailed token.
 *
 * Both halves guard something that used to be missing rather than wrong.
 *
 * `LibraryTable::renewBook()` persisted nothing for six years — it computed a due
 * date and returned it — and was unreachable, so nothing noticed. It also cached
 * `Carbon::today()` in a `static` and called the mutating `addDays()` on it, which
 * would have granted the second book renewed in one request twice the period and the
 * third three times. `testRenewingSeveralBooksGivesThemAllTheSameDueDate` is that bug
 * written down; it fails against the old code by construction.
 *
 * The token half is the one to keep honest. A borrower link stands in for a session,
 * so "one token sees exactly one person at one library" is not a nicety — it is the
 * entire authorization of /library/my-books.
 *
 * Writes to the capsule and cleans up after itself: it creates its own checkout row
 * against a scratch book, and never touches a row it did not make.
 */
class BorrowerSelfServiceTest extends TestCase
{
    private Container $container;
    private Connection $adapter;
    private LibraryTable $library;
    private BorrowerTokenTable $tokens;
    /** @var list<int> checkout ids this test created */
    private array $createdCheckouts = [];
    private int $personId = 0;
    private int $libraryId = 0;
    private int $bookId = 0;

    protected function setUp(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';

        $container = ContainerFactory::build($appConfig);
        $this->container = $container;

        try {
            /** @var Connection $adapter */
            $adapter = $container->get(Connection::class);
            $adapter->select('SELECT 1');
        } catch (Throwable $e) {
            self::markTestSkipped(
                'no reachable database: ' . $e->getMessage()
                . ' — this test needs the capsule up (docker compose up -d)'
            );
        }
        $this->adapter = $adapter;

        //db8.0 must have run; without it there is no token table and no renewal limit.
        $has = $adapter->select(
            "SELECT COUNT(*) AS n FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lib_borrower_tokens'",
            []
        )->current();
        if (1 !== (int) $has['n']) {
            self::markTestSkipped('database/db8.0.sql has not been applied here');
        }

        try {
            $this->library = $container->get(LibraryTable::class);
            $this->tokens  = $container->get(BorrowerTokenTable::class);
        } catch (Throwable $e) {
            self::markTestSkipped('Books tables are not constructible here: ' . $e->getMessage());
        }

        //A real book in a real library, so the library's options (period, limit) are
        //the ones production would use — but our own checkout row against it.
        $row = $adapter->select(
            'SELECT b.book_id, b.library_id FROM lib_books b
             JOIN lib_libraries l ON l.LibraryId = b.library_id LIMIT 1',
            []
        )->current();
        if (! isset($row['book_id'])) {
            self::markTestSkipped('no library books in this database');
        }
        $this->bookId    = (int) $row['book_id'];
        $this->libraryId = (int) $row['library_id'];

        $person = $adapter->select('SELECT PersonId FROM sch_persons LIMIT 1', [])->current();
        $this->personId = (int) $person['PersonId'];
    }

    protected function tearDown(): void
    {
        foreach ($this->createdCheckouts as $id) {
            $this->adapter->execute('DELETE FROM lib_checkouts WHERE CheckoutId = ?', [$id]);
        }
        if (0 !== $this->personId) {
            $this->adapter->execute('DELETE FROM lib_borrower_tokens WHERE PersonId = ?', [$this->personId]);
        }
    }

    private function newCheckout(string $dueOn = '2020-01-01 23:59:59', int $timesRenewed = 0): int
    {
        $this->adapter->execute(
            'INSERT INTO lib_checkouts (PersonId, BookId, CheckedOutOn, DueOn, TimesRenewed)
             VALUES (?, ?, UTC_TIMESTAMP(), ?, ?)',
            [$this->personId, $this->bookId, $dueOn, $timesRenewed]
        );
        $id = (int) $this->adapter->select('SELECT LAST_INSERT_ID() AS id', [])->current()['id'];
        $this->createdCheckouts[] = $id;

        return $id;
    }

    private function dueOnOf(int $checkoutId): string
    {
        return (string) $this->adapter
            ->select('SELECT DueOn FROM lib_checkouts WHERE CheckoutId = ?', [$checkoutId])
            ->current()['DueOn'];
    }

    // ---------------------------------------------------------------- renewal --

    public function testRenewingPersistsTheNewDueDateAndCountsIt(): void
    {
        $id = $this->newCheckout();
        $before = $this->dueOnOf($id);

        $result = $this->library->renewBook($id);

        self::assertSame(LibraryTable::RENEW_OK, $result['status']);
        self::assertSame(1, $result['timesRenewed']);
        self::assertNotSame($before, $this->dueOnOf($id), 'the new due date must reach the database');

        $row = $this->adapter
            ->select('SELECT TimesRenewed, LastRenewedOn FROM lib_checkouts WHERE CheckoutId = ?', [$id])
            ->current();
        self::assertSame(1, (int) $row['TimesRenewed']);
        self::assertNotNull($row['LastRenewedOn'], 'LastRenewedOn must be stamped');
    }

    public function testRenewingSeveralBooksGivesThemAllTheSameDueDate(): void
    {
        //The regression that mattered: the old implementation held Carbon::today() in a
        //static and called the MUTATING addDays() on it, so consecutive renewals in one
        //process accumulated — measured +14, +28, +42. Three separate checkouts renewed
        //in one process must land on one date.
        $ids = [$this->newCheckout(), $this->newCheckout(), $this->newCheckout()];
        $dates = [];
        foreach ($ids as $id) {
            $result = $this->library->renewBook($id);
            self::assertSame(LibraryTable::RENEW_OK, $result['status']);
            $dates[] = $this->dueOnOf($id);
        }

        self::assertCount(1, array_unique($dates), 'renewals must not accumulate: ' . implode(' | ', $dates));
    }

    public function testAnOverdueBookIsRenewableFromToday(): void
    {
        //The policy the notices depend on. Both Colegio Mayor books are months overdue,
        //so a rule that refused overdue renewals would make the email's button useless.
        $id = $this->newCheckout('2020-01-01 23:59:59');
        $result = $this->library->renewBook($id);

        self::assertSame(LibraryTable::RENEW_OK, $result['status']);
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        self::assertGreaterThan($today, $result['dueOn'], 'an overdue book renews into the future, not from its old date');
    }

    public function testTheRenewalLimitIsEnforced(): void
    {
        $maximum = (int) $this->library->getObject('library', $this->libraryId)['options']->maximumBookRenewals;
        self::assertGreaterThan(0, $maximum, 'this library must allow renewals for the test to mean anything');

        $id = $this->newCheckout('2020-01-01 23:59:59', $maximum);
        $result = $this->library->renewBook($id);

        self::assertSame(LibraryTable::RENEW_LIMIT_REACHED, $result['status']);
        self::assertSame($maximum, (int) $this->adapter
            ->select('SELECT TimesRenewed FROM lib_checkouts WHERE CheckoutId = ?', [$id])
            ->current()['TimesRenewed'], 'a refused renewal must not increment the counter');
    }

    public function testAReturnedBookIsNotRenewable(): void
    {
        $id = $this->newCheckout();
        $this->adapter->execute('UPDATE lib_checkouts SET CheckedInOn = UTC_TIMESTAMP() WHERE CheckoutId = ?', [$id]);

        self::assertSame(LibraryTable::RENEW_ALREADY_RETURNED, $this->library->renewBook($id)['status']);
    }

    public function testAnUnknownCheckoutIsRefusedRatherThanFatal(): void
    {
        self::assertSame(LibraryTable::RENEW_NO_SUCH_CHECKOUT, $this->library->renewBook(0)['status']);
    }

    // ------------------------------------------------------------------ token --

    public function testATokenResolvesToExactlyThePersonAndLibraryItWasIssuedFor(): void
    {
        $token = $this->tokens->issue($this->personId, $this->libraryId);

        self::assertSame(
            ['personId' => $this->personId, 'libraryId' => $this->libraryId],
            $this->tokens->redeem($token)
        );
    }

    public function testATokenIsNotSingleUse(): void
    {
        //Deliberate, and the one place this differs from a magic-link sign-in: the
        //borrower is expected to look, then renew, perhaps days apart.
        $token = $this->tokens->issue($this->personId, $this->libraryId);

        self::assertNotNull($this->tokens->redeem($token));
        self::assertNotNull($this->tokens->redeem($token), 'a second visit must still work');
    }

    public function testTheStoredRowNeverContainsThePlaintextToken(): void
    {
        $token = $this->tokens->issue($this->personId, $this->libraryId);

        $found = $this->adapter
            ->select('SELECT COUNT(*) AS n FROM lib_borrower_tokens WHERE TokenHash = ?', [$token])
            ->current();
        self::assertSame(0, (int) $found['n'], 'the token must be stored hashed, never in the clear');
    }

    public function testAnExpiredTokenIsRefused(): void
    {
        $token = $this->tokens->issue($this->personId, $this->libraryId);
        $this->adapter->execute(
            'UPDATE lib_borrower_tokens SET ExpiresOn = ? WHERE PersonId = ?',
            ['2020-01-01 00:00:00', $this->personId]
        );

        self::assertNull($this->tokens->redeem($token));
    }

    public function testARevokedTokenIsRefused(): void
    {
        $token = $this->tokens->issue($this->personId, $this->libraryId);
        $this->tokens->revokeForPerson($this->personId);

        self::assertNull($this->tokens->redeem($token));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedTokens(): iterable
    {
        yield 'empty'         => [''];
        yield 'too short'     => ['abc'];
        yield 'not hex'       => [str_repeat('z', 64)];
        yield 'sql-ish'       => ["' OR '1'='1"];
        yield 'right length, wrong value' => [str_repeat('a', 64)];
    }

    #[DataProvider('malformedTokens')]
    public function testAMalformedTokenIsRefused(string $token): void
    {
        self::assertNull($this->tokens->redeem($token));
    }
}

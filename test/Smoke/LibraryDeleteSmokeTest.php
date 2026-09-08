<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PDO;

use function array_unique;
use function count;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_pad;

/**
 * `/libraries/{id}/delete` — the one page on this site that can destroy a whole catalogue.
 *
 * ## Every test here builds its own library
 *
 * None of them touches a real one, and that is not fussiness. The capsule's database is a
 * current production export, `database/` holds ninety incremental migrations and no base
 * schema, and the memory note "the capsule cannot be rebuilt from database/dumps/" is
 * there because a `down -v` already cost 113 integration failures once. A test that
 * deleted PUC would take 16,383 real book rows with it and no amount of care afterwards
 * would put them back.
 *
 * So `createLibrary()` inserts a throwaway row with a name nothing else uses, hangs a
 * couple of books, a collection, a checkout and a borrower token off it, and
 * `purgeFixtures()` removes whatever is left in `tearDown()` — including on failure, which
 * is the case that matters, because a test that dies mid-POST leaves a half-deleted
 * library behind.
 *
 * ## What is actually being pinned
 *
 * The cascade, and the four ways in which nothing should happen:
 *
 *  - anonymous, and a signed-in account without `lib_administrator`, are refused;
 *  - a POST naming the wrong library name changes nothing and answers 200;
 *  - a POST with a bad CSRF token changes nothing and answers 400;
 *  - a POST naming `cancel` changes nothing, whatever else it carries.
 *
 * The last one has no button behind it — the template's Cancel is a link and the form
 * declares no cancel element — so it can only be produced by hand. It is tested precisely
 * because it can only be produced by hand: `SionModel\Form\DeleteEntityForm` shipped a
 * Cancel that deleted the record on both front controllers until 2026-08-14.
 */
class LibraryDeleteSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** Distinct from every other class's, or one tearDown deletes another's accounts. */
    private const EMAIL_PREFIX = 'library-delete-smoke-';

    /** Nothing in the production export begins with this. */
    private const LIBRARY_PREFIX = 'ZZ Smoke Test Library';

    /** config/autoload/local.php's development key, as SionModelSmokeTest uses it. */
    private const DEV_API_KEY = 'local-dev-api-key';

    /** @var list<int> libraries created by this test, deleted or not */
    private array $created = [];

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    protected function tearDown(): void
    {
        $this->purgeFixtures();

        parent::tearDown();
    }

    public function testAnonymousVisitorsAreRefused(): void
    {
        $libraryId = $this->createLibrary();

        $response = $this->get("/en/libraries/$libraryId/delete");

        self::assertContains(
            $response['status'],
            [302, 403],
            'the delete page must never render for an anonymous visitor'
        );
        self::assertSame(1, $this->libraryExists($libraryId), 'a GET must not delete anything');
    }

    /**
     * The route guard, doing something for the first time on this surface.
     *
     * Every other `libraries/library/*` guard names `lib_user`, which is `is_default = 1`
     * and therefore means "signed in". This one names `lib_administrator`, so an ordinary
     * account is refused by the guard rather than by the page.
     */
    public function testASignedInAccountWithoutLibAdministratorIsRefused(): void
    {
        $libraryId = $this->createLibrary();

        $jar = $this->newCookieJar();
        //no roles beyond the defaults every account inherits
        $this->signIn($jar);

        $response = $this->get("/en/libraries/$libraryId/delete", false, $jar);

        self::assertContains($response['status'], [302, 403], 'lib_user must not reach the delete page');
        self::assertSame(1, $this->libraryExists($libraryId));
    }

    /** The confirmation page states the inventory it is about to destroy. */
    public function testThePageReportsWhatWillBeDestroyed(): void
    {
        $libraryId = $this->createLibrary(books: 3, collections: 1, checkouts: 2, tokens: 1);
        $jar       = $this->administrator();

        $response = $this->get("/en/libraries/$libraryId/delete", false, $jar);

        self::assertSame(200, $response['status']);
        $body = $response['body'];

        self::assertStringContainsString($this->libraryName($libraryId), $body, 'the name must be on the page');
        self::assertStringContainsString('cannot be undone', $body);
        //The counts, each in its own row. Asserted as numbers rather than by parsing the
        //table, because the point is that the figures are right, not where they sit.
        foreach (['3', '1', '2'] as $expected) {
            self::assertMatchesRegularExpression(
                '/>\s*' . $expected . '\s*</',
                $body,
                "the inventory should report $expected somewhere"
            );
        }
        self::assertSame(1, $this->libraryExists($libraryId), 'rendering the page must delete nothing');
    }

    /** An outstanding checkout is called out separately, because it means a book is out. */
    public function testAnOutstandingCheckoutIsReportedSeparately(): void
    {
        $libraryId = $this->createLibrary(books: 1, checkouts: 1, checkedIn: false);
        $jar       = $this->administrator();

        $body = $this->get("/en/libraries/$libraryId/delete", false, $jar)['body'];

        self::assertStringContainsString('still outstanding', $body);
    }

    public function testTheWrongNameDeletesNothing(): void
    {
        $libraryId = $this->createLibrary(books: 2);
        $jar       = $this->administrator();

        $page  = $this->get("/en/libraries/$libraryId/delete", false, $jar);
        $token = $this->deleteFormToken($page['body']);

        $response = $this->request('POST', "/en/libraries/$libraryId/delete", [], false, $jar, [
            'security'     => $token,
            'library_name' => 'not the name of this library',
        ]);

        self::assertSame(200, $response['status'], 'a mistyped name is human error, not a bad request');
        self::assertStringContainsString('not this library', $response['body']);
        self::assertSame(1, $this->libraryExists($libraryId));
        self::assertSame(2, $this->bookCount($libraryId));
    }

    public function testABadCsrfTokenDeletesNothingAndAnswers400(): void
    {
        $libraryId = $this->createLibrary(books: 2);
        $jar       = $this->administrator();

        $response = $this->request('POST', "/en/libraries/$libraryId/delete", [], false, $jar, [
            'security'     => 'not-a-token',
            'library_name' => $this->libraryName($libraryId),
        ]);

        self::assertSame(400, $response['status']);
        self::assertSame(1, $this->libraryExists($libraryId), 'a refused token must not delete');
        self::assertSame(2, $this->bookCount($libraryId));
    }

    /**
     * A POST naming `cancel` is a no-op even when everything else about it is valid.
     *
     * No browser can produce this. That is the point: the check exists for the
     * hand-crafted request, and it must fail safe.
     */
    public function testAPostNamingCancelDeletesNothingEvenWhenOtherwiseValid(): void
    {
        $libraryId = $this->createLibrary(books: 2);
        $jar       = $this->administrator();

        $page  = $this->get("/en/libraries/$libraryId/delete", false, $jar);
        $token = $this->deleteFormToken($page['body']);

        $response = $this->request('POST', "/en/libraries/$libraryId/delete", [], false, $jar, [
            'security'     => $token,
            'library_name' => $this->libraryName($libraryId),
            'cancel'       => 'Cancel',
        ]);

        self::assertSame(302, $response['status']);
        self::assertSame(1, $this->libraryExists($libraryId), 'naming cancel must never delete');
        self::assertSame(2, $this->bookCount($libraryId));
    }

    /**
     * The whole point: the library goes, and so does everything hanging off it.
     *
     * Asserted table by table rather than through the page, because "the page said it
     * worked" is exactly the claim that a bare `deleteEntity()` would also have made while
     * leaving every book behind.
     */
    public function testTheCorrectNameDeletesTheLibraryAndEverythingInIt(): void
    {
        $libraryId = $this->createLibrary(books: 3, collections: 2, checkouts: 2, tokens: 1);
        $name      = $this->libraryName($libraryId);
        $jar       = $this->administrator();

        self::assertSame(3, $this->bookCount($libraryId), 'fixture sanity');

        $page  = $this->get("/en/libraries/$libraryId/delete", false, $jar);
        $token = $this->deleteFormToken($page['body']);

        $response = $this->request('POST', "/en/libraries/$libraryId/delete", [], false, $jar, [
            'security'     => $token,
            'library_name' => $name,
        ]);

        self::assertSame(302, $response['status'], 'a successful delete redirects');
        self::assertTrue(
            str_contains($response['headers']['location'] ?? '', '/libraries'),
            'and it redirects to the libraries index'
        );

        self::assertSame(0, $this->libraryExists($libraryId), 'the library row is gone');
        self::assertSame(0, $this->bookCount($libraryId), 'its books are gone, not orphaned');
        self::assertSame(0, $this->rowCount('lib_collections', 'LibraryId', $libraryId));
        self::assertSame(0, $this->rowCount('lib_borrower_tokens', 'LibraryId', $libraryId));
        self::assertSame(
            0,
            (int) $this->one(
                'SELECT COUNT(*) FROM lib_checkouts co JOIN lib_books b ON b.book_id = co.BookId '
                . 'WHERE b.library_id = ?',
                [$libraryId]
            ),
            'its checkouts are gone'
        );

        //The change log records the deletion as an aggregate — one entryDeleted row plus
        //one counted row per dependent kind. See App\Books\LibraryDelete for why it is not
        //one row per book.
        $logged = $this->all(
            'SELECT ChangedField, NewValue, OldValue FROM sch_changes '
            . "WHERE ChangedEntity = 'library' AND ChangedIDValue = ?",
            [$libraryId]
        );
        $fields = [];
        foreach ($logged as $row) {
            $fields[(string) $row['ChangedField']] = $row;
        }
        self::assertArrayHasKey('entryDeleted', $fields);
        self::assertSame($name, $fields['entryDeleted']['OldValue'], 'the log keeps the name of what it deleted');
        self::assertArrayHasKey('booksDeleted', $fields);
        self::assertSame('3', (string) $fields['booksDeleted']['NewValue']);
        self::assertArrayHasKey('collectionsDeleted', $fields);
        self::assertArrayHasKey('checkoutsDeleted', $fields);
        self::assertArrayHasKey('borrowerTokensDeleted', $fields);
        //imports had none, and a zero is not a change
        self::assertArrayNotHasKey('importsDeleted', $fields);
    }

    /** A signed-in session holding lib_administrator. */
    private function administrator(): string
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        return $jar;
    }

    /**
     * A throwaway library, plus whatever should hang off it.
     *
     * The id is taken from `MAX(LibraryId) + 1` rather than an auto-increment, because
     * `lib_libraries.LibraryId` has no `AUTO_INCREMENT` on it — the six real rows were
     * written with explicit ids.
     */
    private function createLibrary(
        int $books = 0,
        int $collections = 0,
        int $checkouts = 0,
        int $tokens = 0,
        bool $checkedIn = true
    ): int {
        $libraryId = 1 + (int) $this->one('SELECT COALESCE(MAX(LibraryId), 0) FROM lib_libraries');
        $name      = sprintf('%s %d', self::LIBRARY_PREFIX, $libraryId);

        $this->exec(
            'INSERT INTO lib_libraries (LibraryId, LibraryName, ViewRole) VALUES (?, ?, ?)',
            [$libraryId, $name, 'lib_user']
        );
        $this->created[] = $libraryId;

        $bookIds = [];
        for ($i = 0; $i < $books; $i++) {
            $bookId = 1 + (int) $this->one('SELECT COALESCE(MAX(book_id), 0) FROM lib_books');
            $this->exec(
                'INSERT INTO lib_books (book_id, library_id, original_id, title) VALUES (?, ?, ?, ?)',
                [$bookId, $libraryId, 'smoke-' . $bookId, 'Smoke test book ' . $bookId]
            );
            $bookIds[] = $bookId;
        }

        for ($i = 0; $i < $collections; $i++) {
            $collectionId = 1 + (int) $this->one('SELECT COALESCE(MAX(CollectionId), 0) FROM lib_collections');
            $this->exec(
                'INSERT INTO lib_collections (CollectionId, LibraryId, CollectionName, Abbreviation) '
                . 'VALUES (?, ?, ?, ?)',
                [$collectionId, $libraryId, 'Smoke collection ' . $collectionId, 'SM' . $i]
            );
        }

        for ($i = 0; $i < $checkouts && $i < count($bookIds); $i++) {
            $checkoutId = 1 + (int) $this->one('SELECT COALESCE(MAX(CheckoutId), 0) FROM lib_checkouts');
            $this->exec(
                'INSERT INTO lib_checkouts (CheckoutId, PersonId, BookId, CheckedOutOn, CheckedInOn) '
                . 'VALUES (?, ?, ?, NOW(), ' . ($checkedIn ? 'NOW()' : 'NULL') . ')',
                //PersonId 0: sch_persons has no FK from this table, and the test never
                //renders a borrower name. Inventing a person would be a second fixture to
                //clean up.
                [$checkoutId, 0, $bookIds[$i]]
            );
        }

        for ($i = 0; $i < $tokens; $i++) {
            $this->exec(
                'INSERT INTO lib_borrower_tokens (TokenHash, PersonId, LibraryId, CreatedOn, ExpiresOn) '
                . "VALUES (?, ?, ?, NOW(), NOW() + INTERVAL 30 DAY)",
                //A 64-character digest-shaped value; nothing reads it back.
                [str_pad('smoke' . $libraryId . $i, 64, '0'), 0, $libraryId]
            );
        }

        //**A library inserted by SQL is invisible to the ACL until the cache is flushed**,
        //and this cost the first run of this test six failures reported as
        //"403 You are not authorized to access library_8". It is not a defect in the page:
        //`LibraryTable::getRules()` derives one `library_<id>` resource per row from
        //`getObjects('library')`, which is cached, and the assembled ACL is itself cached
        //in APCu under `bjyauthorize:acl`. Creating a library *through the application*
        //invalidates both, because the write goes through
        //`removeDependentCacheItems('library')`; a raw INSERT does not, so the new
        //library's resource does not exist and default-deny answers 403.
        //
        //Worth knowing beyond this test: the same is true of a library added by a DBA or a
        //migration. It will not be administrable until something flushes the cache.
        $this->flushPersistentCache();

        return $libraryId;
    }

    /**
     * Flush the web server's APCu segment over HTTP.
     *
     * Over HTTP because it has to be: a CLI process gets its own APCu segment, so nothing
     * this test could run in-process would touch the cache Apache is reading. The endpoint
     * demands an API key, which is why the dev key is named above.
     */
    private function flushPersistentCache(): void
    {
        $response = $this->request('GET', '/en/sm/clear-persistent-cache', ['X-Api-Key: ' . self::DEV_API_KEY]);
        self::assertSame(
            200,
            $response['status'],
            'the fixture library is unreachable until the cache is flushed, so this is a precondition '
            . 'rather than an assertion about the endpoint'
        );
    }

    /**
     * Remove every library this class created, and everything under it.
     *
     * Matches on the id list first and the name prefix second, so a library left behind by
     * a crashed earlier run is cleaned up too. The name prefix is what makes that safe:
     * nothing in the production export begins with it.
     */
    private function purgeFixtures(): void
    {
        $ids = $this->all(
            'SELECT LibraryId FROM lib_libraries WHERE LibraryName LIKE ?',
            [self::LIBRARY_PREFIX . '%']
        );
        foreach ($ids as $row) {
            $this->created[] = (int) $row['LibraryId'];
        }

        foreach (array_unique($this->created) as $libraryId) {
            $this->exec(
                'DELETE co FROM lib_checkouts co JOIN lib_books b ON b.book_id = co.BookId WHERE b.library_id = ?',
                [$libraryId]
            );
            $this->exec('DELETE FROM lib_books WHERE library_id = ?', [$libraryId]);
            $this->exec('DELETE FROM lib_collections WHERE LibraryId = ?', [$libraryId]);
            $this->exec('DELETE FROM lib_borrower_tokens WHERE LibraryId = ?', [$libraryId]);
            $this->exec('DELETE FROM lib_imports WHERE LibraryId = ?', [$libraryId]);
            $this->exec('DELETE FROM lib_libraries WHERE LibraryId = ?', [$libraryId]);
            $this->exec(
                "DELETE FROM sch_changes WHERE ChangedEntity = 'library' AND ChangedIDValue = ?",
                [$libraryId]
            );
        }
        $this->created = [];
    }

    /**
     * The delete form's CSRF token.
     *
     * Not `MagicLinkSignIn::extractCsrfToken()`, whose regex is identical but whose failure
     * message says "the sign-in form should carry a CSRF token" — which is what it reported
     * on the first run of this test, for a delete page that had answered 403 and contained
     * no form at all. A wrong message on a real failure is worse than no message.
     */
    private function deleteFormToken(string $body): string
    {
        $found = preg_match('/name="security"[^>]*value="([^"]+)"/', $body, $matches);
        self::assertSame(1, $found, 'the delete confirmation should carry a CSRF token');

        return $matches[1];
    }

    private function libraryName(int $libraryId): string
    {
        return (string) $this->one('SELECT LibraryName FROM lib_libraries WHERE LibraryId = ?', [$libraryId]);
    }

    private function libraryExists(int $libraryId): int
    {
        return $this->rowCount('lib_libraries', 'LibraryId', $libraryId);
    }

    private function bookCount(int $libraryId): int
    {
        return $this->rowCount('lib_books', 'library_id', $libraryId);
    }

    private function rowCount(string $table, string $column, int $value): int
    {
        //The table and column names are literals from this class, never request data.
        return (int) $this->one("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?", [$value]);
    }

    /** @param list<mixed> $params */
    private function one(string $sql, array $params = []): mixed
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function all(string $sql, array $params = []): array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /** @param list<mixed> $params */
    private function exec(string $sql, array $params = []): void
    {
        $this->pdo()->prepare($sql)->execute($params);
    }
}

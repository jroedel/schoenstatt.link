<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Books\LibraryScopedForms;
use App\Laminas\ContainerFactory;
use App\Laminas\ServiceBridge;
use App\Services\Container;
use Books\Model\LibraryTable;
use Books\Validator\UniqueBarcodeInLibrary;
use PHPUnit\Framework\TestCase;
use SionModel\Db\Connection;
use SionModel\Form\FormInterface;
use Throwable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * A barcode already used in this library is refused by the form, not by the database.
 *
 * `lib_books` has `UNIQUE (library_id, original_id)`, and until 2026-09-23 the index was the
 * only thing enforcing it. A librarian editing a book to a barcode another copy already had
 * got `SQLSTATE[23000] … Duplicate entry '1-52355' for key 'library_id'` out of
 * `SionTable::updateHelper()` — **479 times between 2026-08-17 and 2026-09-22**, on a page
 * that had already accepted the submission (#286).
 *
 * ## Why this is an integration test
 *
 * Because the rule's whole content is a question to the database, and the two ways it can be
 * wrong both look right from outside. Scoped to the wrong library it passes when it should
 * refuse; missing its self-exclusion it refuses every save that left the barcode alone,
 * which is most of them. Neither is visible in the specification, and a unit test with a
 * stubbed connection would be asserting the stub.
 *
 * **Nothing is written.** The form is validated, never submitted, so the only statements
 * this issues are the rule's own SELECTs. The three barcodes it uses are read out of the
 * capsule's data rather than hardcoded, since the dump is replaceable and book ids are not
 * stable.
 *
 * Needs a running capsule's database; skips without one, like its siblings.
 */
final class BookBarcodeUniquenessTest extends TestCase
{
    use RequiresDatabase;

    private static ?Container $container = null;

    /**
     * Two active books in one library, and a barcode nobody holds.
     *
     * @var array{library: int, first: int, firstBarcode: int, second: int, secondBarcode: int, free: int}|null
     */
    private static ?array $fixture = null;

    protected function setUp(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }

        $this->requireDatabase(self::bridge());

        try {
            self::fixture();
        } catch (Throwable $e) {
            self::markTestSkipped('no usable book fixture in this dataset: ' . $e->getMessage());
        }
    }

    /** The bug, stated as a test: the other book's barcode must be refused. */
    public function testABarcodeAnotherBookInTheLibraryHoldsIsRefused(): void
    {
        $fixture = self::fixture();
        $form    = self::editForm($fixture['first']);

        $form->setData(self::submission($form, $fixture['first'], $fixture['secondBarcode']));

        self::assertFalse(
            $form->isValid(),
            sprintf(
                'barcode %d belongs to book %d, so editing book %d to it must be refused by the form — '
                . 'otherwise it reaches UNIQUE (library_id, original_id) and the page 500s',
                $fixture['secondBarcode'],
                $fixture['second'],
                $fixture['first']
            )
        );
        self::assertArrayHasKey('withinLibraryId', $form->getMessages());
        self::assertArrayHasKey(
            UniqueBarcodeInLibrary::ERROR_TAKEN,
            $form->getMessages()['withinLibraryId'],
            'the refusal must be the uniqueness rule, not some other validator failing by accident'
        );
    }

    /**
     * The half that makes the other half safe.
     *
     * A uniqueness rule with no self-exclusion finds the row being edited and refuses every
     * save that did not change the barcode — which is nearly every save. It would read as
     * "the form is broken" rather than as "the rule is too strict".
     */
    public function testABookKeepingItsOwnBarcodeIsAccepted(): void
    {
        $fixture = self::fixture();
        $form    = self::editForm($fixture['first']);

        $form->setData(self::submission($form, $fixture['first'], $fixture['firstBarcode']));

        self::assertTrue(
            $form->isValid(),
            'book ' . $fixture['first'] . ' keeping its own barcode must be accepted: '
            . json_encode($form->getMessages())
        );
    }

    /** And a barcode nobody holds is accepted, or the rule would refuse everything. */
    public function testAnUnusedBarcodeIsAccepted(): void
    {
        $fixture = self::fixture();
        $form    = self::editForm($fixture['first']);

        $form->setData(self::submission($form, $fixture['first'], $fixture['free']));

        self::assertTrue(
            $form->isValid(),
            'an unused barcode must be accepted: ' . json_encode($form->getMessages())
        );
    }

    /**
     * The create path has no row to exclude, so every existing barcode collides.
     *
     * Worth its own case because the two paths build the form through different entry points
     * — `formForLibrary()` against a route's library id, `form()` against a loaded row — and
     * only the second knows a book id.
     */
    public function testOnACreateAnExistingBarcodeIsRefused(): void
    {
        $fixture = self::fixture();
        $form    = self::forms()->formForLibrary('book', $fixture['library']);

        $form->setData(self::submission($form, $fixture['first'], $fixture['firstBarcode']));

        self::assertFalse(
            $form->isValid(),
            'creating a book with a barcode the library already uses must be refused'
        );
    }

    /**
     * The message names the book that already has it.
     *
     * The reason the bug was expensive is that the librarian could not tell what was wrong;
     * "already taken" alone would leave them hunting. This is the part that has to survive a
     * refactor, so it is asserted rather than left to the message template.
     */
    public function testTheMessageNamesTheConflictingBook(): void
    {
        $fixture = self::fixture();
        $form    = self::editForm($fixture['first']);

        $form->setData(self::submission($form, $fixture['first'], $fixture['secondBarcode']));
        $form->isValid();

        $message = $form->getMessages()['withinLibraryId'][UniqueBarcodeInLibrary::ERROR_TAKEN] ?? '';

        self::assertStringContainsString((string) $fixture['secondBarcode'], $message);
        self::assertStringContainsString('#' . $fixture['second'], $message);
    }

    /**
     * A submission that is otherwise valid, so the only thing under test is the barcode.
     *
     * Built from the row itself rather than from literals: the book form has required fields
     * whose value options are scoped to the library, and inventing values for them would
     * make this test fail for reasons that have nothing to do with barcodes.
     *
     * @return array<string, mixed>
     */
    private static function submission(FormInterface $form, int $bookId, int $barcode): array
    {
        $book = self::book($bookId);

        return [
            //Reading the token off the form is what a rendered page hands a browser. Without
            //it every case below fails on `security`, which says nothing about barcodes.
            'security'        => $form->get('security')->getValue(),
            'withinLibraryId' => (string) $barcode,
            'title'           => (string) $book['title'],
            'authors'         => is_array($book['authors'] ?? null) ? $book['authors'] : [],
            'collectionId'    => (string) ($book['collectionId'] ?? ''),
            'callNumber'      => (string) ($book['callNumber'] ?? ''),
            'category'        => (string) ($book['category'] ?? ''),
            'isActive'        => '1',
            'submit'          => 'Save',
        ];
    }

    /** @return array<string, mixed> */
    private static function book(int $bookId): array
    {
        /** @var LibraryTable $table */
        $table = self::bridge()->get(LibraryTable::class);
        $table->setLibraryId(self::fixture()['library']);

        /** @var array<string, mixed>|null $book */
        $book = $table->getObject('book', $bookId);
        self::assertIsArray($book, "book $bookId is not readable");

        return $book;
    }

    private static function editForm(int $bookId): FormInterface
    {
        return self::forms()->form('book', self::book($bookId));
    }

    /**
     * Two active books in one library plus an unused barcode, discovered from the data.
     *
     * @return array{library: int, first: int, firstBarcode: int, second: int, secondBarcode: int, free: int}
     */
    private static function fixture(): array
    {
        if (null !== self::$fixture) {
            return self::$fixture;
        }

        /** @var Connection $connection */
        $connection = self::bridge()->get(Connection::class);

        $rows = $connection->select(
            'SELECT `library_id`, `book_id`, `original_id` FROM `lib_books` '
            . 'WHERE `original_id` IS NOT NULL AND `library_id` = ('
            . '  SELECT `library_id` FROM `lib_books` WHERE `original_id` IS NOT NULL '
            . '  GROUP BY `library_id` ORDER BY COUNT(*) DESC LIMIT 1'
            . ') ORDER BY `book_id` LIMIT 2'
        )->toArray();

        if (2 !== count($rows)) {
            self::markTestSkipped('no library in this dataset has two books with a barcode');
        }

        $library = (int) $rows[0]['library_id'];
        //CAST, because `original_id` is varchar(20): a plain MAX() is lexicographic and
        //answers '9999' for a library holding '10000', so "one past the highest" lands on a
        //barcode that is already in use. That mistake made this test fail against its own
        //fixture rather than against the code.
        $max     = $connection->select(
            'SELECT MAX(CAST(`original_id` AS UNSIGNED)) AS `m` FROM `lib_books` WHERE `library_id` = ?',
            [$library]
        )->current();

        return self::$fixture = [
            'library'       => $library,
            'first'         => (int) $rows[0]['book_id'],
            'firstBarcode'  => (int) $rows[0]['original_id'],
            'second'        => (int) $rows[1]['book_id'],
            'secondBarcode' => (int) $rows[1]['original_id'],
            //One past the highest in use, so it is free by construction rather than by luck.
            'free'          => (int) ($max['m'] ?? 0) + 1,
        ];
    }

    private static function forms(): LibraryScopedForms
    {
        return new LibraryScopedForms(self::bridge());
    }

    private static function bridge(): ServiceBridge
    {
        return ServiceBridge::around(self::container());
    }

    private static function container(): Container
    {
        if (null === self::$container) {
            /** @var array<string, mixed> $appConfig */
            $appConfig = require __DIR__ . '/../../config/application.config.php';
            $appConfig['module_listener_options']['config_cache_enabled']     = false;
            $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

            self::$container = ContainerFactory::build($appConfig);
        }

        return self::$container;
    }
}

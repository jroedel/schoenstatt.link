<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Books\Import\ImportColumns;
use App\Laminas\ContainerFactory;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function implode;
use function in_array;
use function is_array;
use function sort;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Every field the import vocabulary claims to write is a field the book entity can store.
 *
 * ## This is the test that would have caught the missing year
 *
 * The old column map named `copyrightYear`. The book entity's `update_columns` calls that
 * field `publishedYear` — commit `4933155` renamed it on 2018-11-02 — and both
 * `SionTable::createHelper()` and `updateHelper()` iterate their input and **skip any key
 * the map has no column for**, silently. So from that day every import read the year, cast
 * it to an int, and threw it away. Three imports ran afterwards, and nothing anywhere
 * failed, logged or looked different.
 *
 * That is the whole failure mode this file exists for: a spelling that is wrong in one of
 * two places, where the wrong one costs a column of data and nothing else. It cannot be
 * caught by rendering, by planning, or by running an import — the plan is right, the write
 * is right, and the field is simply absent from the statement.
 *
 * `collection` is the one deliberate exception. It is not a book field at all: the import
 * writes a collection *name*, resolves it against the library's collections and writes
 * `collectionId`, creating the collection if it has to. Excluded by name here, and
 * asserted to still not be a column, so that adding one would fail this test rather than
 * quietly changing what the import does with the column.
 *
 * ## Integration, not unit
 *
 * It reads the application's merged configuration to get `update_columns`, which needs
 * modules loaded. It needs no database and no request, so it runs on a bare CI runner.
 */
class ImportColumnsWriteRealFieldsTest extends TestCase
{
    /** The one vocabulary entry that is not a book field, and why. */
    private const NOT_A_BOOK_FIELD = ['collection'];

    /** @var array<string, string>|null */
    private static ?array $updateColumns = null;

    /** @return array<string, string> the book entity's field => column map */
    private function updateColumns(): array
    {
        if (null !== self::$updateColumns) {
            return self::$updateColumns;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        //Both caches off, for the reason AclGuardRouteDriftTest gives: a bare runner
        //cannot write data/config/, and a cache file owned by the wrong user beside a
        //real deployment is worse than a slow test.

        $services = ContainerFactory::build($appConfig);

        /** @var array<string, mixed> $config */
        $config  = $services->get('config');
        $entity  = $config['sion_model']['entities']['book'] ?? null;
        self::assertIsArray($entity, 'the book entity specification is missing');
        $columns = $entity['update_columns'] ?? null;
        self::assertIsArray($columns, 'the book entity has no update_columns');

        /** @var array<string, string> $columns */
        return self::$updateColumns = $columns;
    }

    public function testEveryImportColumnNamesAStorableBookField(): void
    {
        $storable = array_keys($this->updateColumns());
        $missing  = [];
        foreach (ImportColumns::fields() as $field) {
            if (in_array($field, self::NOT_A_BOOK_FIELD, true)) {
                continue;
            }
            if (! in_array($field, $storable, true)) {
                $missing[] = $field;
            }
        }

        sort($missing);
        self::assertSame(
            [],
            $missing,
            "These import columns name fields the book entity cannot store, so SionTable "
            . "will drop them without a word:\n  " . implode("\n  ", $missing)
            . "\nCheck update_columns in module/Books/config/module.config.php for the real name."
        );
    }

    /**
     * `collection` must stay something the entity cannot store directly.
     *
     * If a `collection` column were ever added to `update_columns`, the importer's
     * name-to-id resolution would be bypassed for any row that reached `createEntity()`
     * with the key still set — and `LibraryImporter::planRows()` unsets it deliberately.
     * Better to fail here than to discover it as collections silently not being created.
     */
    public function testTheCollectionNameIsNotItselfABookColumn(): void
    {
        self::assertArrayNotHasKey(
            'collection',
            $this->updateColumns(),
            'the import resolves a collection name to collectionId; a direct column would bypass that'
        );
    }

    /** The two the importer refuses a file without are both real fields. */
    public function testTheRequiredFieldsAreStorable(): void
    {
        foreach (ImportColumns::REQUIRED_FIELDS as $field) {
            self::assertArrayHasKey($field, $this->updateColumns(), $field . ' is required but not storable');
        }
    }
}

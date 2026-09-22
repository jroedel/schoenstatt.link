<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Db\SqlBuilderCases;
use SionModel\Db\Sql\Delete;
use SionModel\Db\Sql\Predicate\InvalidPredicate;
use SionModel\Db\Sql\Select;
use SionModel\Db\Sql\Update;
use SionModel\Db\Sql\Where;

/**
 * The SQL builder writes what `test/Db/sql-builder-surface.php` records.
 *
 * The builder touches no database — it turns method calls into a string and a list of
 * values — so this belongs in the vendor-free unit suite, where it runs on a CI runner that
 * has no MariaDB. That matters more here than usual: `tools/sql-surface.sh --check`, the
 * other half of the contract, needs a running capsule and a full HTTP pass, so without this
 * the builder would be checked nowhere CI can reach.
 *
 * The suite has no autoloader, which is why the classes are registered below rather than
 * imported and forgotten.
 */
class SqlBuilderSurfaceTest extends TestCase
{
    /** @var array<string, array{sql: string, values: list<mixed>}> */
    private static array $recorded;

    public static function setUpBeforeClass(): void
    {
        self::register();

        /** @var array<string, array{sql: string, values: list<mixed>}> $recorded */
        $recorded       = require __DIR__ . '/../Db/sql-builder-surface.php';
        self::$recorded = $recorded;
    }

    /** @return iterable<string, array{string}> */
    public static function caseNames(): iterable
    {
        self::register();

        foreach (array_keys(SqlBuilderCases::all()) as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('caseNames')]
    public function testTheCaseRendersWhatWasRecorded(string $name): void
    {
        $this->assertArrayHasKey(
            $name,
            self::$recorded,
            'A case with no recorded answer: regenerate the recording and read the diff.'
        );

        [$sql, $values] = (SqlBuilderCases::all()[$name])()->render();

        $this->assertSame(self::$recorded[$name]['sql'], $sql);
        $this->assertSame(self::$recorded[$name]['values'], $values);
    }

    public function testTheRecordingHasNoCaseTheCorpusDropped(): void
    {
        $this->assertSame([], array_diff(array_keys(self::$recorded), array_keys(SqlBuilderCases::all())));
    }

    /**
     * A write with no `WHERE` is refused where it is assembled, not sent.
     *
     * laminas-db rendered both of these, so the statement reached the server and did exactly
     * what it said. Neither has a recorded answer, because neither produces one.
     */
    public function testAnUnboundedWriteIsRefused(): void
    {
        $this->expectException(InvalidPredicate::class);
        (new Update('sch_associations'))->set(['IsActive' => 0])->render();
    }

    public function testAnUnboundedDeleteIsRefused(): void
    {
        $this->expectException(InvalidPredicate::class);
        (new Delete('sch_associations'))->render();
    }

    /** An `IN` over nothing is a syntax error at the server; it is refused here instead. */
    public function testAnEmptyInIsRefused(): void
    {
        $this->expectException(InvalidPredicate::class);
        (new Select('t'))->where(['a' => []])->render();
    }

    /** A condition under a column key would silently lose the key, so it is refused. */
    public function testAConditionUnderAColumnKeyIsRefused(): void
    {
        $this->expectException(InvalidPredicate::class);
        (new Where())->addPredicates(['a' => new \SionModel\Db\Sql\Predicate\IsNull('b')]);
    }

    /** Registers the two namespaces this test needs, since the unit suite autoloads nothing. */
    private static function register(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        spl_autoload_register(static function (string $class): void {
            $roots = [
                'SionModel\\Db\\Sql\\'    => __DIR__ . '/../../module/SionModel/src/Db/Sql/',
                'SchoenstattTest\\Db\\'   => __DIR__ . '/../Db/',
            ];

            foreach ($roots as $prefix => $root) {
                if (! str_starts_with($class, $prefix)) {
                    continue;
                }
                $file = $root . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require $file;
                }
                return;
            }
        });
    }
}

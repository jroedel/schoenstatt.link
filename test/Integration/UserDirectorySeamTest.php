<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use JUser\Model\UserTable;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;
use Schoenstatt\Model\SchoenstattTable;
use SionModel\Db\Model\PredicatesTable;
use SionModel\Service\UserDirectoryInterface;
use Throwable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The seam that stopped SionModel naming a JUser type.
 *
 * `SionTable` carried `use JUser\Model\UserTable` until 2026-08-22 — a type from a package
 * it does not require, and a cycle, since that class extends `SionTable`. It named it for
 * two display columns: "who changed this" in the change log and "who wrote this" in the
 * comments list.
 *
 * **Both call sites dereferenced the getter unguarded while its own docblock documented a
 * null return.** So a host that registered no user table fatalled on its own change log,
 * and nothing here could catch it, because this application always registers one. That is
 * the first two tests: they take the directory away deliberately, which is the only way to
 * reach the path a second application would have hit on its first page load.
 *
 * The third is the property that let JUser 3.0.0 stay untouched: `UserTable` does **not**
 * implement the interface and is adapted anyway. If someone later "tidies" that by adding
 * `implements`, this test says why it was not needed rather than silently passing.
 *
 * Run in the capsule: php composer.phar integration
 */
final class UserDirectorySeamTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    /**
     * Tables come from the container, so they are shared with every other test in the run.
     * Three tests here take a directory away on purpose; without restoring it, whichever
     * test ran next would see a table that silently names nobody. Recorded as
     * [table, original directory] and put back in tearDown so a failed assertion still
     * restores.
     *
     * @var list<array{0: \SionModel\Db\Model\SionTable, 1: UserDirectoryInterface|null}>
     */
    private array $restore = [];

    protected function tearDown(): void
    {
        foreach ($this->restore as [$table, $directory]) {
            $table->setUserDirectory($directory);
        }
        $this->restore = [];
        parent::tearDown();
    }

    /**
     * Takes the directory away for the duration of one test, remembering what to put back.
     */
    private function withoutUserDirectory(\SionModel\Db\Model\SionTable $table): void
    {
        $this->restore[] = [$table, $table->getUserDirectory()];
        $table->setUserDirectory(null);
    }

    /**
     * The laminas services, built the way bin/console builds them — no bootstrap(), so no
     * MVC listeners, no route stack, no dispatch. Config caching off so a test run never
     * writes data/config/.
     */
    private function bridge(): ServiceBridge
    {
        if (null !== self::$bridge) {
            return self::$bridge;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        return self::$bridge = new ServiceBridge($appConfig);
    }

    /**
     * Every table here builds a database adapter, which a bare CI runner has no
     * configuration for. Checks local.php before asking the container, because asking
     * without it raises a warning and failOnWarning makes that a failure no later catch
     * can undo.
     */
    private function requireDatabase(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        try {
            $adapter = $this->bridge()->get(Adapter::class);
            $adapter->getDriver()->getConnection()->connect();
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }
    }

    /**
     * The change log is what `/sm/view-changes` and every entity show page render. With no
     * directory it used to fatal on `null->getUsers()`; now the name column is simply
     * blank, which is already what it shows for a user id nobody can resolve.
     */
    public function testTheChangeLogRendersWithNoUserDirectory(): void
    {
        $this->requireDatabase();
        $table = $this->bridge()->get(SchoenstattTable::class);
        $this->withoutUserDirectory($table);

        self::assertNull($table->getUserDirectory(), 'the directory must stay unset once cleared');

        $changes = $table->getChanges(5);

        self::assertIsArray($changes);
        foreach ($changes as $change) {
            //An id nobody can name yields ['userId' => n]; the template renders
            //`updatedBy.username ?? ''` behind an `is iterable` test, so both shapes
            //are blank rather than broken.
            self::assertArrayNotHasKey(
                'username',
                is_array($change['updatedBy'] ?? null) ? $change['updatedBy'] : [],
                'with no directory there is no name to render'
            );
        }
    }

    /**
     * The comments list, the second of the two columns. PredicatesTable had the identical
     * unguarded dereference in processCommentRow().
     */
    public function testTheCommentsListRendersWithNoUserDirectory(): void
    {
        $this->requireDatabase();
        $table = $this->bridge()->get(PredicatesTable::class);
        $this->withoutUserDirectory($table);

        $comments = $table->getComments();

        self::assertIsArray($comments);
        foreach ($comments as $comment) {
            self::assertNull($comment['createdByUsername'], 'with no directory there is no name to render');
            self::assertNull($comment['reviewedByUsername'], 'with no directory there is no name to render');
        }
    }

    /**
     * Why breaking the cycle needed no change in JUser at all. `UserTable` answers
     * `getUsers()` and `getUsernames()` without declaring the interface, and SionTable
     * wraps it. Declaring `implements` would have forced `: array` return types onto two
     * methods that have none — a covariance fatal, not a warning — days after 3.0.0 was
     * tagged.
     */
    public function testTheUserTableIsAdaptedWithoutImplementingTheInterface(): void
    {
        $this->requireDatabase();
        $bridge = $this->bridge();
        if (! $bridge->has(UserTable::class)) {
            self::markTestSkipped('JUser is not enabled in this configuration');
        }

        self::assertNotInstanceOf(
            UserDirectoryInterface::class,
            $bridge->get(UserTable::class),
            'JUser is deliberately unaware of this interface; if that changes, the adapter can go'
        );

        $directory = $this->bridge()->get(SchoenstattTable::class)->getUserDirectory();

        self::assertInstanceOf(UserDirectoryInterface::class, $directory);
        self::assertIsArray($directory->getUsers());
        self::assertIsArray($directory->getUsernames());
    }

    /**
     * The self-reference guard, which used to be spelled `! $this instanceof UserTable` and
     * was the entire coupling. It is now an identity check, so it protects any table a host
     * happens to name as its own directory — not one hardcoded class.
     */
    public function testATableIsNeverItsOwnUserDirectory(): void
    {
        $this->requireDatabase();
        $bridge = $this->bridge();
        if (! $bridge->has(UserTable::class)) {
            self::markTestSkipped('JUser is not enabled in this configuration');
        }

        self::assertNull(
            $bridge->get(UserTable::class)->getUserDirectory(),
            'the user table has no use for a handle on itself'
        );
    }

    /**
     * The deprecated shims stay, because they are public API and a consuming application
     * may still call them. They answer the interface now, not a JUser type.
     */
    public function testTheDeprecatedShimsDelegateToTheDirectory(): void
    {
        $this->requireDatabase();
        $table = $this->bridge()->get(SchoenstattTable::class);

        self::assertSame($table->getUserDirectory(), $table->getUserTable());

        $this->restore[] = [$table, $table->getUserDirectory()];
        $table->setUserTable(null);
        self::assertNull($table->getUserDirectory(), 'setUserTable(null) must clear the directory');
    }
}

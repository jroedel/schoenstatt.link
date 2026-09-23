<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function implode;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * `tools/api-key-edit.php` — the part of the maintenance-key rotation that edits a file
 * on a production server which is not in git.
 *
 * Every case here is a way the edit could go wrong quietly. The one that motivated the
 * script's whole design is {@see testItRefusesWhenTheTwoListsShareAKey} and its
 * neighbour {@see testItNeverTouchesTheSchoenstattList}: `local.php` holds two different
 * `api_keys` lists and they guard different things, so an editor that finds "the array"
 * by pattern rotates the wrong secret and says it succeeded.
 */
final class ApiKeyEditTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/api-key-edit-' . getmypid() . '-' . uniqid();
        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0o700, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            unlink($f);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    /**
     * A local.php shaped like the real one: two api_keys lists, comments between them,
     * and unrelated settings either side.
     */
    private function fixture(string $sion, string $schoenstatt, string $quote = "'"): string
    {
        $q    = static fn(string $v): string => $quote . $v . $quote;
        $path = $this->dir . '/local.php';

        file_put_contents($path, <<<PHP
        <?php

        declare(strict_types=1);

        return [
            'db' => [
                'dsn' => 'mysql:dbname=ourlink_db1;host=localhost',
            ],
            //The maintenance key. Guards /sm/cache-status and /sm/clear-persistent-cache.
            'sion_model' => [
                'api_keys' => [{$q($sion)}],
            ],
            'books' => [
                'files_api_key' => 'unrelated-and-must-not-move',
            ],
            'schoenstatt' => [
                //A DIFFERENT secret: App\\Controller\\LibraryNoticesController reads this.
                'api_keys' => [
                    {$q($schoenstatt)},
                ],
            ],
        ];
        PHP);

        return $path;
    }

    /** @return array{0: int, 1: string} status and combined output */
    private function edit(string ...$args): array
    {
        $cmd = 'php ' . escapeshellarg(dirname(__DIR__, 2) . '/tools/api-key-edit.php');
        foreach ($args as $a) {
            $cmd .= ' ' . escapeshellarg($a);
        }
        exec($cmd . ' 2>&1', $out, $status);

        return [$status, implode("\n", $out)];
    }

    public function testItReadsTheMaintenanceKeyAndNotTheOtherOne(): void
    {
        $file = $this->fixture('maintenance-key-aaa', 'notices-key-bbb');

        [$status, $out] = $this->edit('read', $file);

        self::assertSame(0, $status, $out);
        self::assertSame('maintenance-key-aaa', trim($out));
    }

    public function testAddWidensTheListSoBothKeysWorkAtOnce(): void
    {
        $file = $this->fixture('old-key-aaa', 'notices-key-bbb');

        [$status, $out] = $this->edit('add', $file, 'new-key-ccc');
        self::assertSame(0, $status, $out);

        [, $read] = $this->edit('read', $file);
        self::assertSame("old-key-aaa\nnew-key-ccc", trim($read), 'both keys must be valid during a rotation');
    }

    public function testKeepOnlyNarrowsToTheNewKey(): void
    {
        $file = $this->fixture('old-key-aaa', 'notices-key-bbb');

        $this->edit('add', $file, 'new-key-ccc');
        [$status, $out] = $this->edit('keep-only', $file, 'new-key-ccc');
        self::assertSame(0, $status, $out);

        [, $read] = $this->edit('read', $file);
        self::assertSame('new-key-ccc', trim($read));
    }

    /**
     * The whole point. `schoenstatt.api_keys` and `books.files_api_key` are different
     * secrets and a rotation that moved either would be a silent outage somewhere else.
     */
    public function testItNeverTouchesTheSchoenstattList(): void
    {
        $file = $this->fixture('old-key-aaa', 'notices-key-bbb');

        $this->edit('add', $file, 'new-key-ccc');
        $this->edit('keep-only', $file, 'new-key-ccc');

        $after = (string) file_get_contents($file);
        self::assertStringContainsString("'notices-key-bbb'", $after, 'the notices key must survive verbatim');
        self::assertStringContainsString("'unrelated-and-must-not-move'", $after);
    }

    public function testItRefusesWhenTheTwoListsShareAKey(): void
    {
        $file = $this->fixture('same-key-for-both', 'same-key-for-both');

        [$status, $out] = $this->edit('add', $file, 'new-key-ccc');

        self::assertSame(1, $status, 'an ambiguous key must stop the edit');
        self::assertStringContainsString('both', $out);
        self::assertStringNotContainsString('new-key-ccc', file_get_contents($file) ?: '');
    }

    public function testItRefusesToRemoveTheKeyItWasNotToldToKeep(): void
    {
        $file = $this->fixture('old-key-aaa', 'notices-key-bbb');

        [$status, $out] = $this->edit('keep-only', $file, 'a-key-that-was-never-added');

        self::assertSame(1, $status);
        self::assertStringContainsString('add', $out, 'it should point at the safe order');
    }

    public function testItRefusesAnEmptyMaintenanceList(): void
    {
        $path = $this->dir . '/local.php';
        file_put_contents($path, "<?php\n\nreturn ['sion_model' => ['api_keys' => []]];\n");

        [$status, $out] = $this->edit('add', $path, 'new-key-ccc');

        self::assertSame(1, $status);
        self::assertStringContainsString('nothing to rotate', $out);
    }

    public function testItHandlesDoubleQuotedKeys(): void
    {
        $file = $this->fixture('old-key-aaa', 'notices-key-bbb', '"');

        [$status, $out] = $this->edit('add', $file, 'new-key-ccc');
        self::assertSame(0, $status, $out);

        [, $read] = $this->edit('read', $file);
        self::assertSame("old-key-aaa\nnew-key-ccc", trim($read));
        self::assertStringContainsString('"new-key-ccc"', file_get_contents($file) ?: '');
    }

    public function testItLeavesABackupThatRestoresTheOriginal(): void
    {
        $file   = $this->fixture('old-key-aaa', 'notices-key-bbb');
        $before = (string) file_get_contents($file);

        [$status, $out] = $this->edit('add', $file, 'new-key-ccc');
        self::assertSame(0, $status, $out);

        $backup = trim($out);
        self::assertFileExists($backup, 'the backup path is what the script prints on success');
        self::assertSame($before, file_get_contents($backup));
    }

    /**
     * A rotation runs `add` and `keep-only` within the same second. Before this was
     * fixed the two backups collided and the second overwrote the first, so what
     * survived under the original's name was the post-add state — the one recovery does
     * not need.
     */
    public function testTwoEditsInTheSameSecondKeepBothBackups(): void
    {
        $file   = $this->fixture('old-key-aaa', 'notices-key-bbb');
        $before = (string) file_get_contents($file);

        [, $first]  = $this->edit('add', $file, 'new-key-ccc');
        [, $second] = $this->edit('keep-only', $file, 'new-key-ccc');

        self::assertNotSame(trim($first), trim($second), 'the two backups must not collide');
        self::assertSame($before, file_get_contents(trim($first)), 'the first backup is the original');
        self::assertStringContainsString('old-key-aaa', (string) file_get_contents(trim($second)));
    }

    /**
     * A key that appears twice cannot be edited by replacing its literal, and guessing
     * which occurrence was meant is exactly the class of mistake this script exists to
     * avoid.
     */
    public function testItRefusesAKeyThatAppearsTwice(): void
    {
        $path = $this->dir . '/local.php';
        file_put_contents($path, <<<'PHP'
        <?php

        //The same literal again in a comment: 'doubled-key-aaa'
        return [
            'sion_model' => ['api_keys' => ['doubled-key-aaa']],
        ];
        PHP);

        [$status, $out] = $this->edit('add', $path, 'new-key-ccc');

        self::assertSame(1, $status);
        self::assertStringContainsString('expected exactly 1', $out);
    }
}

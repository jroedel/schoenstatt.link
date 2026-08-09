<?php

namespace SchoenstattTest\Integration;

use JTranslate\Model\TranslationsTable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Pins the two properties JTranslate's catalog writer did not have: it fails
 * loudly, and it never leaves a partially written file where a concurrent
 * request could include() it.
 *
 * The bug being locked down was silent data loss, not a crash. Every return
 * value in writePhpTranslationArrays() was discarded — mkdir(), then
 * file_put_contents(), then chmod() — so on an unwritable target the phrase was
 * committed to the database, the admin action flashed "Translations successfully
 * updated", and the site went on serving the old text with nothing logged.
 *
 * That state was reachable in this capsule when these tests were written:
 * `module/Schoenstatt/language/` and `language/` were owned by root while Apache
 * runs as www-data, so the site's largest catalogs were exactly the ones that
 * could not be rewritten. Both were given to the web server's group, setgid, on
 * 2026-08-09, so the capsule no longer reproduces it — which is a reason to keep
 * these tests rather than to relax them. The failure mode is a property of a
 * deployment's ownership, not of this repository, and the next machine to check
 * out this code has whatever ownership its checkout gives it.
 *
 * The atomicity half matters for a different reason. These files are include()d
 * by concurrent requests, so an in-place write lets another process compile a
 * half-written file — a parse error in the middle of an unrelated page. That
 * failure looks like a permissions problem and is not one, which is precisely
 * why it is worth a test rather than a comment.
 *
 * Both methods are protected and take their target path as an argument, so this
 * drives them directly on a temporary directory rather than through
 * writePhpTranslationArrays(), which would insist on the real project layout and
 * a database. The instance is built without its constructor for the same reason
 * TranslationArrayExportTest does it: the constructor queries the phrases table,
 * and nothing about writing a file needs a connection.
 *
 * Runs in the capsule, needs vendor/ for autoloading, needs no database:
 * php composer.phar integration
 */
class TranslationCatalogWriteTest extends TestCase
{
    private string $tmp;

    private TranslationsTable $table;

    /** @var callable */
    private $write;

    /** @var callable */
    private $ensure;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/jtranslate-write-' . bin2hex(random_bytes(6));

        $reflection  = new ReflectionClass(TranslationsTable::class);
        $this->table = $reflection->newInstanceWithoutConstructor();

        $write  = $reflection->getMethod('writeCatalogAtomically');
        $ensure = $reflection->getMethod('ensureDirectory');

        $this->write  = fn(string $path, string $code): string
            => $write->invoke($this->table, $path, $code);
        $this->ensure = fn(string $path): mixed
            => $ensure->invoke($this->table, $path);
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->tmp)) {
            return;
        }
        //restore any mode this test tightened, or the recursive delete fails
        @chmod($this->tmp, 0775);
        foreach ((glob($this->tmp . '/*') ?: []) as $file) {
            @chmod($file, 0664);
            @unlink($file);
        }
        @rmdir($this->tmp);
    }

    public function testItCreatesTheDirectoryRecursively(): void
    {
        $nested = $this->tmp . '/language/Schoenstatt';

        ($this->ensure)($nested);

        self::assertDirectoryExists($nested);
        self::assertDirectoryIsWritable($nested);

        //cleaning up a nested tree is more trouble than this test is worth, so
        //flatten it back before tearDown()
        @rmdir($nested);
        @rmdir($this->tmp . '/language');
    }

    public function testEnsuringAnExistingDirectoryIsHarmless(): void
    {
        ($this->ensure)($this->tmp);
        ($this->ensure)($this->tmp);

        self::assertDirectoryExists($this->tmp);
    }

    public function testItWritesTheExactBytesItWasGiven(): void
    {
        ($this->ensure)($this->tmp);
        $target = $this->tmp . '/en_US.lang.php';
        $code   = "<?php\n\nreturn [\n    'Cancel' => 'Cancelar',\n];\n";

        $returned = ($this->write)($target, $code);

        self::assertSame($target, $returned, 'the writer should report the path it committed');
        self::assertSame($code, file_get_contents($target));
    }

    /**
     * The whole point of the temp-file-plus-rename dance. If a `.tmp` sibling
     * survives the call, either the rename did not happen or the cleanup path is
     * broken — and a stray `*.tmp` in a language directory is a file the
     * translator will eventually find and wonder about.
     */
    public function testItLeavesNoTemporaryFileBehind(): void
    {
        ($this->ensure)($this->tmp);
        ($this->write)($this->tmp . '/en_US.lang.php', "<?php\n\nreturn [];\n");

        self::assertSame(
            ['en_US.lang.php'],
            array_map('basename', glob($this->tmp . '/*') ?: []),
            'the catalog should be the only file left in the directory'
        );
    }

    /**
     * A catalog is data. The old code chmod'd it 0775, which came from reusing the
     * directory's mode, and left every generated translation file executable.
     */
    public function testTheCatalogIsNotExecutable(): void
    {
        ($this->ensure)($this->tmp);
        $target = $this->tmp . '/en_US.lang.php';
        ($this->write)($target, "<?php\n\nreturn [];\n");

        $mode = fileperms($target) & 0777;

        self::assertSame(
            0,
            $mode & 0111,
            sprintf('expected no exec bits on a data file, got 0%o', $mode)
        );
        self::assertNotSame(
            0,
            $mode & 0040,
            sprintf('the group must still be able to read the catalog, got 0%o', $mode)
        );
    }

    /**
     * Replacing a catalog whose *file* is unwritable must still work, as long as
     * the directory is: rename() takes its permission from the directory. This is
     * the ordinary production case — a deploy leaves the file owned by the SFTP
     * account and the web server has to replace it — and it is the case the old
     * in-place file_put_contents() could not handle.
     */
    public function testItReplacesAFileThatIsItselfUnwritable(): void
    {
        ($this->ensure)($this->tmp);
        $target = $this->tmp . '/en_US.lang.php';
        file_put_contents($target, "<?php\n\nreturn ['stale' => 'stale'];\n");
        chmod($target, 0444);

        $code = "<?php\n\nreturn ['fresh' => 'fresh'];\n";
        ($this->write)($target, $code);

        self::assertSame($code, file_get_contents($target));
    }

    /**
     * The regression that matters most: an unwritable target has to raise, because
     * the caller's next move depends on knowing. The admin action reports it to the
     * translator; the request-path caller logs it and renders the page. Neither can
     * do anything with a silent false.
     *
     * This variant provokes the failure with a parent path that is a regular file
     * rather than with permissions, because permissions do not stop uid 0 and the
     * capsule runs `docker compose exec php` as root by default — the sibling test
     * below therefore skips in exactly the run that matters most. "Not a directory"
     * fails for everybody, so this one always guards the regression.
     */
    public function testAnUnwritableTargetRaisesInsteadOfFailingSilently(): void
    {
        ($this->ensure)($this->tmp);
        $notADirectory = $this->tmp . '/en_US.lang.php';
        file_put_contents($notADirectory, "<?php\n\nreturn [];\n");
        $target = $notADirectory . '/nested/en_US.lang.php';

        try {
            ($this->write)($target, "<?php\n\nreturn [];\n");
            self::fail(
                'writeCatalogAtomically() returned normally for a path it cannot write. '
                . 'That is the silent-failure bug: the database write has already '
                . 'committed by the time this runs, so a caller that is told nothing '
                . 'reports success and the site keeps serving the old translations.'
            );
        } catch (RuntimeException $e) {
            self::assertStringContainsString(
                $target,
                $e->getMessage(),
                'the exception must name the path that could not be written, or whoever '
                . 'reads the log cannot act on it'
            );
        }
    }

    /**
     * The same guarantee via directory permissions, which is how it actually
     * presents in production. Skipped for uid 0, hence the sibling above.
     */
    public function testAnUnwritableDirectoryRaisesInsteadOfFailingSilently(): void
    {
        if (0 === posix_getuid()) {
            self::markTestSkipped(
                'running as uid 0, which writes regardless of mode; '
                . 'testAnUnwritableTargetRaisesInsteadOfFailingSilently covers this unconditionally'
            );
        }

        ($this->ensure)($this->tmp);
        chmod($this->tmp, 0555);
        $target = $this->tmp . '/en_US.lang.php';

        try {
            ($this->write)($target, "<?php\n\nreturn [];\n");
            self::fail(
                'writeCatalogAtomically() returned normally for an unwritable directory. '
                . 'That is the silent-failure bug: the database write has already '
                . 'committed by the time this runs, so a caller that is told nothing '
                . 'reports success and the site keeps serving the old translations.'
            );
        } catch (RuntimeException $e) {
            self::assertStringContainsString(
                $this->tmp,
                $e->getMessage(),
                'the exception must name the path that could not be written, or whoever '
                . 'reads the log cannot act on it'
            );
        } finally {
            chmod($this->tmp, 0775);
        }

        self::assertSame([], glob($this->tmp . '/*') ?: [], 'a failed write should leave nothing behind');
    }
}

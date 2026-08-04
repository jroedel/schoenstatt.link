<?php

namespace SchoenstattTest\Integration;

use PHPUnit\Framework\TestCase;
use SionModel\Console\Command\ClearConfigCacheCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Covers the command that replaced the deploy's hand-written
 * `rm -f data/config/module-*-cache.*.php`.
 *
 * The behaviour worth pinning is the exit status, because this runs as a deploy
 * hook: a config cache that silently survives leaves production serving the
 * previous release's merged configuration, and a hook that reports success
 * while doing nothing is how that goes unnoticed.
 *
 * Needs vendor/ (symfony/console), so it lives outside the vendor-free unit
 * suite: php composer.phar integration
 */
class ClearConfigCacheCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sch-config-cache-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $path) {
            is_dir($path) ? rmdir($path) : unlink($path);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    /**
     * SymfonyStyle hard-wraps its blocks to the terminal width, so a message
     * asserted verbatim can fail purely on where the line broke.
     */
    private function display(CommandTester $tester): string
    {
        return trim(preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
    }

    public function testRemovesEveryConfiguredCacheFile(): void
    {
        $config = $this->dir . '/module-config-cache.sch_config.php';
        $classMap = $this->dir . '/module-classmap-cache.sch_module_map.php';
        file_put_contents($config, '<?php return [];');
        file_put_contents($classMap, '<?php return [];');

        $tester = new CommandTester(new ClearConfigCacheCommand([$config, $classMap]));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertFileDoesNotExist($config);
        $this->assertFileDoesNotExist($classMap);
        $this->assertStringContainsString('2 file(s) removed', $this->display($tester));
    }

    /**
     * Caching is off in development, so on most machines there is nothing to
     * delete. That is a success, not a failure — otherwise the deploy hook
     * would have to special-case it.
     */
    public function testSucceedsWhenTheCacheWasNeverWritten(): void
    {
        $tester = new CommandTester(
            new ClearConfigCacheCommand([$this->dir . '/module-config-cache.sch_config.php'])
        );

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('absent', $this->display($tester));
    }

    /**
     * An empty file list means no cache_dir was configured. Reporting success
     * is right, but it has to say so: the alternative reading — "I cleared
     * everything" — is the dangerous one.
     */
    public function testWarnsWhenNoCacheIsConfigured(): void
    {
        $tester = new CommandTester(new ClearConfigCacheCommand([]));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('No config cache is configured', $this->display($tester));
    }

    /**
     * The failure that actually happens in production is an ownership one: the
     * web SAPI writes these files as its own user, so the deploy account can
     * be unable to replace them. A directory on the cache path stands in for
     * "present but not removable" — the branch and the exit status are what
     * matter, and this way the test needs no root and triggers no warning.
     */
    public function testFailsWhenAPathCannotBeRemoved(): void
    {
        $blocked = $this->dir . '/module-config-cache.sch_config.php';
        mkdir($blocked);

        $tester = new CommandTester(new ClearConfigCacheCommand([$blocked]));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('Check ownership', $this->display($tester));
    }
}

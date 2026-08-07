<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Twig\ForgivingCache;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Twig\Cache\CacheInterface;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

use function chmod;
use function function_exists;
use function is_dir;
use function mkdir;
use function posix_geteuid;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * An unwritable Twig compile cache must cost a page its cache, not its body.
 *
 * This is the regression test for the 2026-08-07 capsule failure: every ported HTML
 * route answered **200 with an empty body**, because two per-template subdirectories
 * under data/cache/twig were root-owned, Twig's FilesystemCache::write() threw
 * mid-render, and SionModel\Error\FatalErrorHandler recorded the throwable and
 * emitted nothing.
 *
 * Why App\Twig\TwigFactory's writability probe did not catch it, and cannot: Twig
 * hashes each template into a two-hex-character subdirectory and creates that
 * subdirectory itself. The probe checks the parent. A render performed as a
 * different user — `docker compose exec -T app php …` runs as root, which is how
 * CLAUDE.md says to run things in the capsule — leaves one subdirectory permanently
 * unwritable while the parent keeps answering is_writable() = true.
 *
 * So the property under test is not "the cache works". It is that a cache which
 * *fails* still renders, which is what TwigFactory's docblock has always claimed and
 * what App\Twig\ForgivingCache is what makes true. Twig supports this directly:
 * Environment::loadTemplate() falls back to `eval('?>'.$content)` and names "$this->
 * cache is implemented as a no-op" as a case that fallback exists for.
 *
 * No HTTP and no laminas container: an ArrayLoader and a deliberately broken cache
 * are the whole fixture, so this stays valid while vendor/ is mid-migration.
 *
 * **Every test needs its own template source**, which is not tidiness. Twig derives
 * the compiled class name from the source and skips the cache entirely once that
 * class is loaded (`class_exists($cls, false)`). Two tests sharing a template share a
 * class, so the second never reaches the cache and passes for the wrong reason —
 * measured: it is what made the first draft of this file report a green degradation
 * test and a red control.
 */
class TwigCacheDegradationTest extends TestCase
{
    private const TEMPLATE = 'degrade.html.twig';

    /**
     * The failure exactly as it happened, reproduced through the real
     * FilesystemCache rather than a stub: a directory that exists and cannot be
     * written to.
     *
     * Skipped as root, who is never denied a write and so cannot observe this at all
     * — and root is precisely who the suite runs as inside the capsule, which is why
     * the stub-driven test below carries the same claim.
     */
    public function testARealUnwritableDirectoryDoesNotCostThePageItsBody(): void
    {
        if (! function_exists('posix_geteuid') || 0 === posix_geteuid()) {
            self::markTestSkipped('root (or no posix ext) is never denied a write, so there is nothing to observe');
        }

        $dir = $this->unwritableDir();
        try {
            $twig = $this->environment(new ForgivingCache(new FilesystemCache($dir)), 'real-unwritable');

            self::assertSame('rendered real-unwritable', $twig->render(self::TEMPLATE));
        } finally {
            chmod($dir, 0700);
            rmdir($dir);
        }
    }

    /**
     * The same claim without the filesystem, so it holds on a machine where the test
     * above is skipped — and so the failure mode is stated as what it is: any
     * throwing write() at all, not specifically a permission error.
     */
    public function testAThrowingCacheWriteDoesNotCostThePageItsBody(): void
    {
        $twig = $this->environment(new ForgivingCache(new ThrowingTwigCache()), 'wrapped');

        self::assertSame('rendered wrapped', $twig->render(self::TEMPLATE));
    }

    /**
     * The bad outcome, asserted rather than described: the control for the test
     * above. Without the wrapper the same render raises, and an uncaught throwable is
     * what reached the visitor as an empty 200 — the fatal handler records it and
     * emits no body.
     */
    public function testWithoutTheWrapperTheSameRenderThrows(): void
    {
        $twig = $this->environment(new ThrowingTwigCache(), 'unwrapped');

        $this->expectException(RuntimeException::class);
        $twig->render(self::TEMPLATE);
    }

    /**
     * A writable cache still caches. Without this the wrapper could have turned the
     * compile cache off for every page and no other assertion here would notice — a
     * silent performance regression on every ported route.
     *
     * Asserted against the directory rather than through
     * Environment::getTemplateClass(), which is marked @internal.
     */
    public function testAWritableCacheStillWritesThroughTheWrapper(): void
    {
        $dir = $this->emptyDir();
        try {
            $twig = $this->environment(new ForgivingCache(new FilesystemCache($dir)), 'writable');
            self::assertSame([], $this->entries($dir), 'the fixture directory should start empty');

            $twig->render(self::TEMPLATE);

            self::assertNotSame([], $this->entries($dir), 'nothing was written to the compile cache');
        } finally {
            $this->deleteTree($dir);
        }
    }

    /** @param non-empty-string $marker makes the template source, and so the compiled class, unique per test */
    private function environment(CacheInterface $cache, string $marker): Environment
    {
        //auto_reload and strict_variables as App\Twig\TwigFactory sets them
        return new Environment(new ArrayLoader([self::TEMPLATE => 'rendered ' . $marker]), [
            'strict_variables' => true,
            'cache'            => $cache,
            'auto_reload'      => true,
        ]);
    }

    private function emptyDir(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'twigcache');
        unlink($path);
        mkdir($path, 0700);

        return $path;
    }

    private function unwritableDir(): string
    {
        $path = $this->emptyDir();
        chmod($path, 0500);

        return $path;
    }

    /** @return list<string> */
    private function entries(string $dir): array
    {
        $found = [];
        foreach ((array) @scandir($dir) as $entry) {
            if ('.' !== $entry && '..' !== $entry) {
                $found[] = (string) $entry;
            }
        }

        return $found;
    }

    private function deleteTree(string $dir): void
    {
        foreach ($this->entries($dir) as $entry) {
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->deleteTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}

/**
 * Deliberately not a PHPUnit mock: this has to survive being called from inside a
 * Twig render, and the interface is four methods. Precedent for a support class in a
 * test file: ProbeSionModelController in CacheStatusParityTest.
 */
final class ThrowingTwigCache implements CacheInterface
{
    public function generateKey(string $name, string $className): string
    {
        return $className;
    }

    public function write(string $key, string $content): void
    {
        throw new RuntimeException('Unable to write in the cache directory (pretend/62).');
    }

    public function load(string $key): void
    {
    }

    public function getTimestamp(string $key): int
    {
        return 0;
    }
}

<?php

declare(strict_types=1);

namespace App\Twig;

use Throwable;
use Twig\Cache\CacheInterface;

use function error_log;
use function sprintf;

/**
 * A Twig cache that cannot take a page down.
 *
 * App\Twig\TwigFactory already promises this: "a failure degrades to in-memory
 * compilation — a slower page, not a 500 on the first HTML route ported". Its
 * writability probe was not enough to keep that promise, and on 2026-08-07 the
 * capsule proved it — every ported HTML route answered **200 with an empty body**:
 *
 *     Twig\Error\RuntimeError: An exception has been thrown during the rendering
 *     of a template ("Unable to write in the cache directory
 *     (data/cache/twig/62).") in "schoenstatt/_shrine-index.html.twig" at line 46
 *
 * The gap is that Twig does not write into the directory the factory probes. It
 * hashes each template into a two-hex-character subdirectory and creates that
 * subdirectory itself, with the umask of whichever process compiled the template
 * first. So a single render performed as root — which is precisely how CLAUDE.md
 * says to run things in the capsule, `docker compose exec -T app php …` — leaves a
 * root-owned subdirectory that the Apache worker can never write to again, while
 * `is_writable('data/cache/twig')` keeps answering true. Production has its own
 * version of the same hazard: phploy deploys over SFTP as one account and PHP-FPM
 * runs as another.
 *
 * A failed cache write is not a reason to fail a request, and Twig agrees —
 * Environment::loadTemplate() ends with `eval('?>'.$content)` and a comment naming
 * "$this->cache is implemented as a no-op" as a case it exists to cover. So
 * swallowing the write is not a hack around Twig; it is the documented way to be
 * one. What the visitor loses is the compile cache for that one template, which is
 * a slower page.
 *
 * It is not silent, because a permanently degraded cache is a misconfiguration
 * somebody should fix: the first failure per process goes to error_log, i.e. the
 * Apache error log. Once per process rather than once per render, so a degraded
 * cache cannot itself become the load problem.
 *
 * Only write() is guarded, because only write() throws. FilesystemCache::load()
 * checks is_file() first and @include's, and getTimestamp() returns 0 for a missing
 * key — both already degrade on their own.
 */
final class ForgivingCache implements CacheInterface
{
    private bool $reported = false;

    public function __construct(private readonly CacheInterface $inner)
    {
    }

    public function generateKey(string $name, string $className): string
    {
        return $this->inner->generateKey($name, $className);
    }

    public function write(string $key, string $content): void
    {
        try {
            $this->inner->write($key, $content);
        } catch (Throwable $e) {
            if (! $this->reported) {
                $this->reported = true;
                error_log(sprintf(
                    'Twig compile cache is not writable, falling back to in-memory compilation: %s',
                    $e->getMessage()
                ));
            }
        }
    }

    public function load(string $key): void
    {
        $this->inner->load($key);
    }

    public function getTimestamp(string $key): int
    {
        return $this->inner->getTimestamp($key);
    }
}

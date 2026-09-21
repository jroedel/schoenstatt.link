<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use App\Text\Slug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `App\Text\Slug` against what `cocur/slugify` answered.
 *
 * The package was removed on 2026-09-21 and its answers were recorded first —
 * `test/Text/slug-surface.php` holds 599 of them: every rule in the derived map, alone and
 * between letters; every distinct character the production database contains, in context;
 * and the shapes. A parity test would have died with the package. This outlives it.
 *
 * **A changed line in the recording is a slug moving, and a slug is a URL.** Association,
 * publication, text and composition pages are addressed by them, and two tables generate a
 * missing slug on a read path and write it back, so a drift here is a moved page and a dead
 * link rather than a cosmetic difference. There is nothing to regenerate the recording
 * from; read the diff instead.
 *
 * The wider claim — that the replacement agrees with the package on all 14,211 distinct
 * values this application actually slugs — was measured once, on 2026-09-21 against the
 * production export, and belongs in the pull request rather than in the repository.
 *
 * A unit test: `App\Text\Slug` needs no database and no container, only `ext-intl`, which
 * is a hard requirement of this application. See `php composer.phar unit`.
 */
final class SlugSurfaceTest extends TestCase
{
    /**
     * A floor, so a recording that silently emptied reads as a failure rather than as a
     * test that agrees about nothing.
     */
    private const CASE_FLOOR = 500;

    /** @return iterable<string, array{string, string}> */
    public static function recordedSlugs(): iterable
    {
        /** @var list<array{string, string}> $recording */
        $recording = require __DIR__ . '/../Text/slug-surface.php';

        foreach ($recording as [$input, $slug]) {
            yield sprintf('%s => %s', bin2hex($input), $slug) => [$input, $slug];
        }
    }

    public function testTheRecordingIsNotEmpty(): void
    {
        /** @var list<array{string, string}> $recording */
        $recording = require __DIR__ . '/../Text/slug-surface.php';

        self::assertGreaterThanOrEqual(self::CASE_FLOOR, count($recording));
    }

    #[DataProvider('recordedSlugs')]
    public function testEverySlugIsWhatTheRecordingHolds(string $input, string $expected): void
    {
        self::assertSame($expected, Slug::of($input));
    }

    /**
     * The two properties every call site depends on, stated rather than left to the corpus:
     * a slug is URL-safe, and slugging one is idempotent.
     */
    #[DataProvider('recordedSlugs')]
    public function testASlugIsUrlSafeAndStable(string $input, string $expected): void
    {
        self::assertMatchesRegularExpression('/\A[a-z0-9]*(?:-[a-z0-9]+)*\z/', $expected);
        self::assertSame($expected, Slug::of($expected), 'slugging a slug changed it');
    }
}

<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;

use function array_map;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function json_decode;
use function json_encode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * `tools/php-advisories.php` — the daily check of PHP's own advisories against the PHP
 * production runs (`.github/workflows/security-advisories.yml`).
 *
 * The shapes below are copied from real php-src advisories, whose version data is written
 * by hand: an open-ended range per branch (`< 8.5.12`), a closed one (`>=8.2.0, <=8.2.2`),
 * a list of hyphen spans, a bare version. The case that motivated the design is
 * {@see testOurBranchsFixOutvotesAnotherBranchsOpenRange}: read naively, `< 8.5.12`
 * says 8.4.30 is vulnerable, and a check that cries wolf every morning gets ignored.
 */
final class PhpAdvisoriesTest extends TestCase
{
    private string $file = '';

    protected function tearDown(): void
    {
        if ($this->file !== '') {
            @unlink($this->file);
        }
    }

    /**
     * @param array<string, string> $versions range => patched
     * @return array<string, mixed>
     */
    private static function advisory(string $id, array $versions, string $published = '2026-10-21T13:34:25Z'): array
    {
        $entries = [];
        foreach ($versions as $range => $patched) {
            $entries[] = [
                'package'                  => ['ecosystem' => 'php', 'name' => 'php-src'],
                'vulnerable_version_range' => (string) $range,
                'patched_versions'         => $patched,
            ];
        }

        return [
            'ghsa_id'         => $id,
            'cve_id'          => null,
            'summary'         => 'summary of ' . $id,
            'severity'        => 'high',
            'published_at'    => $published,
            'updated_at'      => $published,
            'vulnerabilities' => $entries,
        ];
    }

    /**
     * @param list<array<string, mixed>> $advisories
     * @return array{int, list<array<string, mixed>>, string}
     */
    private function check(array $advisories, string ...$args): array
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'php-advisories-');
        file_put_contents($this->file, implode("\n", array_map(
            static fn (array $a): string => (string) json_encode($a),
            $advisories,
        )) . "\n");

        $cmd = 'php ' . escapeshellarg(dirname(__DIR__, 2) . '/tools/php-advisories.php')
            . ' ' . escapeshellarg($this->file);
        foreach ($args as $a) {
            $cmd .= ' ' . escapeshellarg($a);
        }
        $errFile = $this->file . '.err';
        exec($cmd . ' 2>' . escapeshellarg($errFile), $out, $status);
        $err = (string) @file_get_contents($errFile);
        @unlink($errFile);

        return [$status, array_map(static fn (string $l): array => (array) json_decode($l, true), $out), $err];
    }

    /**
     * php2shell, as published: one open-ended range per supported branch.
     *
     * @return array<string, mixed>
     */
    private static function php2shell(): array
    {
        return self::advisory('GHSA-w8hw-w9fx-j79w', [
            '< 8.2.35' => '8.2.35',
            '< 8.3.36' => '8.3.36',
            '< 8.4.27' => '8.4.27',
            '< 8.5.12' => '8.5.12',
        ]);
    }

    public function testBelowOurBranchsFixIsAffected(): void
    {
        [$status, $out] = $this->check([self::php2shell()], '--php=8.5.9');

        self::assertSame(1, $status);
        self::assertCount(1, $out);
        self::assertSame('affected', $out[0]['verdict']);
        self::assertSame('8.5.12', $out[0]['fixed_in']);
    }

    public function testAtOurBranchsFixNothingIsPrinted(): void
    {
        [$status, $out] = $this->check([self::php2shell()], '--php=8.5.12');

        self::assertSame(0, $status);
        self::assertSame([], $out);
    }

    public function testOurBranchsFixOutvotesAnotherBranchsOpenRange(): void
    {
        [$status, $out] = $this->check([self::php2shell()], '--php=8.4.30', '--all');

        self::assertSame(0, $status);
        self::assertSame('not-affected', $out[0]['verdict']);
    }

    public function testAMatchingRangeWithNoFixOnOurBranchIsAffectedWithNoFix(): void
    {
        [$status, $out] = $this->check([self::php2shell()], '--php=8.1.10');

        self::assertSame(1, $status);
        self::assertSame('affected', $out[0]['verdict']);
        self::assertNull($out[0]['fixed_in']);
    }

    public function testHyphenSpansAreAlternatives(): void
    {
        $advisory = self::advisory('GHSA-w8qr-v226-r27w', [
            '7.4.15 - 7.4.33, 8.0.2 - 8.0.30' => '',
        ]);

        self::assertSame('affected', $this->check([$advisory], '--php=8.0.10')[1][0]['verdict']);
        self::assertSame('not-affected', $this->check([$advisory], '--php=8.0.1', '--all')[1][0]['verdict']);
    }

    public function testAClosedRangeIsAConjunction(): void
    {
        $advisory = self::advisory('GHSA-54hq-v5wp-fqgv', ['>=8.1.0, <=8.1.15' => '']);

        self::assertSame('affected', $this->check([$advisory], '--php=8.1.15')[1][0]['verdict']);
        self::assertSame('not-affected', $this->check([$advisory], '--php=8.1.16', '--all')[1][0]['verdict']);
    }

    public function testARangeThatDoesNotParseIsUnknownAndPrinted(): void
    {
        $advisory = self::advisory('GHSA-xxxx-xxxx-xxxx', ['all versions before the fix' => '']);

        [$status, $out] = $this->check([$advisory], '--php=8.5.12');

        self::assertSame(1, $status);
        self::assertSame('unknown', $out[0]['verdict']);
    }

    public function testSinceDropsOlderAdvisories(): void
    {
        $old = self::advisory('GHSA-old0-old0-old0', ['< 8.5.99' => '8.5.99'], '2026-01-01T00:00:00Z');

        [$status, $out] = $this->check([$old, self::php2shell()], '--php=8.5.9', '--since=2026-10-15');

        self::assertSame(1, $status);
        self::assertSame(['GHSA-w8hw-w9fx-j79w'], array_map(static fn (array $d): string => $d['ghsa'], $out));
    }

    public function testTheVersionDefaultsToThePlatformPin(): void
    {
        $composer = (array) json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

        [, $out] = $this->check([self::php2shell()], '--all');

        self::assertSame($composer['config']['platform']['php'], $out[0]['php']);
    }

    public function testInputThatIsNotAnAdvisoryIsRefused(): void
    {
        [$status, , $err] = $this->check([['not' => 'an advisory']]);

        self::assertSame(2, $status);
        self::assertStringContainsString('not an advisory', $err);
    }
}

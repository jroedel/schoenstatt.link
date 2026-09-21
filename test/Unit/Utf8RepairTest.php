<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SionModel\Text\Utf8Repair;

require_once __DIR__ . '/../../module/SionModel/src/Text/Utf8Repair.php';

/**
 * `SionModel\Text\Utf8Repair` against what `ForceUTF8\Encoding::toUTF8()` answered.
 *
 * `neitanod/forceutf8` was removed on 2026-09-21 and its answers were recorded first —
 * `fixtures/utf8-repair.php` holds 587 of them in hex, because half the inputs are byte
 * sequences no source file can carry. There is nothing left to regenerate them from, which
 * is the point: a parity test would have died with the package, and this outlives it.
 *
 * What the recording covers, and why each part is there:
 *
 * - **Every single byte**, alone and embedded. This is the whole input alphabet, and it is
 *   where the CP1252-versus-Latin-1 decision for `\x80`–`\x9f` shows up.
 * - **Malformed sequences** — truncated, overlong, surrogate, five-byte. Two of these are
 *   reproduced *bugs*: `\xc0\xaf` and `\xed\xa0\x80` look well-formed to the rule and are
 *   copied through, so `iconv()` later rejects them. Keeping the bug is deliberate.
 * - **Real words as mojibake and mixed with a stray byte.** The mixed case is the one a
 *   whole-string `mb_convert_encoding()` gets wrong, which is why the byte loop exists.
 *
 * A unit test, so it needs no database, no container and no running app — and unlike most
 * of this suite it needs no `ext-intl` either, so it runs on a bare PHP as well as in the
 * capsule. See `php composer.phar unit`.
 */
final class Utf8RepairTest extends TestCase
{
    /**
     * A floor, so a recording that silently became empty reads as a failure rather than as
     * a test that agrees about nothing.
     */
    private const CASE_FLOOR = 500;

    /** @return iterable<string, array{string, string}> */
    public static function recordedAnswers(): iterable
    {
        /** @var list<array{string, string}> $recording */
        $recording = require __DIR__ . '/fixtures/utf8-repair.php';

        foreach ($recording as [$input, $expected]) {
            yield $input => [$input, $expected];
        }
    }

    public function testTheRecordingIsNotEmpty(): void
    {
        /** @var list<array{string, string}> $recording */
        $recording = require __DIR__ . '/fixtures/utf8-repair.php';

        self::assertGreaterThanOrEqual(self::CASE_FLOOR, count($recording));
    }

    #[DataProvider('recordedAnswers')]
    public function testTheRepairAnswersWhatTheRecordingHolds(string $input, string $expected): void
    {
        $bytes = hex2bin($input);
        self::assertIsString($bytes, 'the recording holds a key that is not hex');

        self::assertSame(
            $expected,
            bin2hex(Utf8Repair::toUtf8($bytes)),
            sprintf('the repair of %s moved', $input)
        );
    }

    /**
     * The property the whole class exists for: whatever goes in, what comes out is valid
     * UTF-8, so the `iconv()` in `ToAscii::filter()` cannot return false.
     *
     * The two reproduced bugs are the exceptions and they are named here rather than
     * excluded silently — an overlong sequence and a surrogate are copied through, because
     * the rule this transcribes reads both as well-formed.
     */
    public function testEveryRepairedByteIsValidUtf8(): void
    {
        $knownExceptions = ["\xc0\xaf", "\xed\xa0\x80"];

        for ($byte = 0; $byte < 256; $byte++) {
            $repaired = Utf8Repair::toUtf8(chr($byte));
            self::assertTrue(
                mb_check_encoding($repaired, 'UTF-8'),
                sprintf('byte 0x%02x repaired to invalid UTF-8', $byte)
            );
        }

        foreach ($knownExceptions as $sequence) {
            self::assertFalse(
                mb_check_encoding(Utf8Repair::toUtf8($sequence), 'UTF-8'),
                'a known exception started passing; the rule changed'
            );
        }
    }
}

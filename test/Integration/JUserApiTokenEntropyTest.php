<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use JUser\Service\ApiTokenService;
use PHPUnit\Framework\TestCase;

use function array_count_values;
use function array_keys;
use function array_unique;
use function count;
use function max;
use function min;
use function str_split;
use function strlen;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The `jti` generator, pinned — length, alphabet and the fact that it is random.
 *
 * Written **before** `ApiTokenService::generateJti()` stopped calling
 * `Laminas\Math\Rand::getString()` and passing against that version, because this is
 * the one kind of change where "it still works" proves nothing: a token generator
 * that has quietly lost half its alphabet, or its entropy source, produces output
 * that is indistinguishable from correct output at a glance and stays that way until
 * somebody attacks it. The swap to `random_int()` is only defensible with this in
 * place, and it is left in place afterwards for the same reason.
 *
 * ## What is actually at stake
 *
 * The `jti` is not a bearer credential — it travels inside a signed JWT, and a
 * guessed one is worth nothing without the signature. What it protects is the
 * *registry*: `api_tokens.jti` is UNIQUE, and `JUser\Model\ApiTokenTable` is what
 * makes a token revocable. So a weakened generator shows up first as collisions, at
 * issue time, on an insert — which is to say, as an administrator being told they
 * cannot issue a token for no visible reason.
 *
 * ## What this deliberately does not test
 *
 * Modulo bias. `random_int()` is unbiased by construction, so the implementation this
 * guards cannot have it; catching the 1.21x skew that `random_bytes()[i] % 62` would
 * introduce needs on the order of a million draws to separate from noise, and a test
 * that slow buys a property no plausible rewrite of three lines would violate. The
 * uniformity band below is sized to catch a *narrowed* alphabet, which is the failure
 * that has a real chance of happening.
 *
 * Needs vendor/, so it runs in the capsule: php composer.phar integration
 */
class JUserApiTokenEntropyTest extends TestCase
{
    /** Enough that every one of 62 characters is overwhelmingly likely to appear. */
    private const SAMPLE = 600;

    /**
     * The alphabet, written out here as its own literal rather than read off the
     * class. Comparing a constant to itself would pass no matter what it said; this
     * is the second, independent statement of what a `jti` is made of, and the two
     * disagreeing is the whole signal.
     */
    private const EXPECTED_ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public function testTheAlphabetIsTheSixtyTwoBase62Characters(): void
    {
        self::assertSame(self::EXPECTED_ALPHABET, ApiTokenService::JTI_ALPHABET);
        self::assertSame(62, strlen(ApiTokenService::JTI_ALPHABET));
        self::assertSame(
            62,
            count(array_unique(str_split(ApiTokenService::JTI_ALPHABET))),
            'a repeated character would silently narrow the alphabet'
        );
    }

    /**
     * 43 characters, which is what `api_tokens.jti` is sized for. A longer one is
     * truncated by MySQL, and a truncated jti is a jti the revocation lookup will
     * never match — the token stays valid and unrevocable.
     */
    public function testEveryTokenIsExactlyTheDeclaredLength(): void
    {
        self::assertSame(43, ApiTokenService::JTI_LENGTH);

        foreach ($this->sample() as $jti) {
            self::assertSame(ApiTokenService::JTI_LENGTH, strlen($jti));
        }
    }

    public function testNoCharacterOutsideTheAlphabetIsEverDrawn(): void
    {
        foreach ($this->counts() as $char => $_) {
            self::assertStringContainsString(
                (string) $char,
                self::EXPECTED_ALPHABET,
                'drew a character that is not in the alphabet'
            );
        }
    }

    /**
     * ...and the other direction, which is the one that catches an off-by-one: a
     * generator drawing from `range(0, 60)` never produces the last character and
     * nothing about its output looks wrong.
     */
    public function testEveryCharacterOfTheAlphabetIsReachable(): void
    {
        $observed = $this->counts();

        foreach (str_split(self::EXPECTED_ALPHABET) as $char) {
            self::assertArrayHasKey(
                $char,
                $observed,
                "'$char' was never drawn in " . (self::SAMPLE * 43) . ' characters'
            );
        }
    }

    /**
     * Roughly flat. The band is wide on purpose — see the class docblock. With
     * ~25,800 characters over 62 buckets the expected count is ~416 and the standard
     * deviation ~20, so 0.7–1.4 is about ±8σ: it cannot fire by chance, and it does
     * fire the moment a chunk of the alphabet becomes unreachable.
     */
    public function testTheDistributionIsRoughlyFlat(): void
    {
        $counts   = $this->counts();
        $expected = (self::SAMPLE * ApiTokenService::JTI_LENGTH) / 62;

        self::assertGreaterThan(0.7 * $expected, min($counts));
        self::assertLessThan(1.4 * $expected, max($counts));
    }

    /**
     * Distinct, which is the property the UNIQUE column depends on. A generator
     * returning a constant — a plausible outcome of a bad refactor — passes every
     * assertion above and fails only this one.
     */
    public function testTokensAreNotRepeated(): void
    {
        $sample = $this->sample();

        self::assertCount(self::SAMPLE, array_unique($sample));
    }

    /** @return list<string> */
    private function sample(): array
    {
        static $sample;

        if (null === $sample) {
            $sample = [];
            for ($i = 0; $i < self::SAMPLE; $i++) {
                $sample[] = ApiTokenService::generateJti();
            }
        }

        return $sample;
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        static $counts;

        if (null === $counts) {
            $chars = [];
            foreach ($this->sample() as $jti) {
                foreach (str_split($jti) as $char) {
                    $chars[] = $char;
                }
            }
            $counts = array_count_values($chars);
        }

        return $counts;
    }
}

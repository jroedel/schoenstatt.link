<?php

namespace SchoenstattTest\Unit;

use JTranslate\Model\PhraseIdentity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../module/JTranslate/src/Model/PhraseIdentity.php';

/**
 * Pins what makes two phrases the same phrase.
 *
 * `PhraseIdentity` is four lines of `hash()` calls, which is exactly why it is worth
 * a test: it looks like something a later reader would "tidy" — lower-case it, trim
 * it, normalize the Unicode, swap it back to md5 because md5 is shorter. Every one of
 * those changes is silently destructive, and none of them would fail any other test
 * in this repository until translations started disappearing in production.
 *
 * The relation it has to implement is `Laminas\I18n\Translator`'s, which compares
 * catalog keys byte for byte. `trans_phrases.phrase_hash` carries that relation into
 * the schema as `UNIQUE (project, text_domain, phrase_hash)`. So a hash function that
 * maps two distinct translator keys onto one value does not merely lose a row — it
 * makes the second phrase permanently unable to exist, and therefore permanently
 * untranslatable, with no error anywhere.
 *
 * The class touches no framework code and no database, so this lives in the
 * vendor-free unit suite and requires the file directly.
 */
class PhraseIdentityTest extends TestCase
{
    public function testRawIsThirtyTwoBytesAndHexIsItsEncoding(): void
    {
        $raw = PhraseIdentity::raw('Save');

        self::assertSame(
            PhraseIdentity::LENGTH,
            strlen($raw),
            'the phrase_hash column is BINARY(32); a digest of any other width is silently truncated or '
            . 'right-padded by MySQL, and every stored hash stops matching'
        );
        self::assertSame(PhraseIdentity::hex('Save'), PhraseIdentity::hexOf($raw));
        self::assertSame(64, strlen(PhraseIdentity::hex('Save')));
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', PhraseIdentity::hex('Save'));
    }

    /**
     * Every pair here is two phrases a case- or accent-insensitive index would call
     * one, and the translator calls two.
     *
     * These are not hypothetical. All four came out of schoenstatt.link's own phrase
     * table, and there are fourteen such pairs in it: `Inglés`/`Inglês` are the
     * Spanish and Portuguese names of the same language, sitting in the same text
     * domain, and a `UNIQUE (project, text_domain, phrase(255))` index under
     * `utf8mb4_unicode_520_ci` would have accepted whichever was inserted first and
     * refused the other forever.
     */
    #[DataProvider('distinctPhrasePairs')]
    public function testPhrasesTheCollationWouldMergeStayDistinct(string $a, string $b, string $why): void
    {
        self::assertNotSame(PhraseIdentity::hex($a), PhraseIdentity::hex($b), $why);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function distinctPhrasePairs(): array
    {
        return [
            'case' => [
                'Book checkout',
                'Book Checkout',
                'case-folding would merge two real phrases from the Books domain',
            ],
            'accent' => [
                'Inglés',
                'Inglês',
                'accent-folding would merge the Spanish and Portuguese names of English',
            ],
            'trailing space' => [
                'Save',
                'Save ',
                'a PAD SPACE collation ignores the trailing space; the translator does not, so these are '
                . 'two catalog keys',
            ],
            'leading space' => [
                'Save',
                ' Save',
                'trimming is normalization, and normalization is what makes a stored row unrecognisable to '
                . 'the code that looks it up',
            ],
        ];
    }

    /**
     * Unicode-equivalent but byte-distinct forms must hash differently.
     *
     * `é` composed (U+00E9) and decomposed (U+0065 U+0301) render identically and are
     * canonically equivalent, so NFC-normalizing before hashing looks like an
     * improvement. It is not: the translator does not normalize, so the two are
     * separate catalog keys, and a hash that merged them would let the row for one
     * answer lookups for the other — which silently serves the wrong translation
     * rather than none.
     */
    public function testUnicodeIsNotNormalized(): void
    {
        $composed   = "Espa\u{00F1}ol";
        $decomposed = "Espan\u{0303}ol";

        self::assertNotSame($composed, $decomposed, 'the fixtures are meant to differ in bytes');
        self::assertNotSame(PhraseIdentity::hex($composed), PhraseIdentity::hex($decomposed));
    }

    /**
     * The algorithm is part of the stored data, not an implementation detail.
     *
     * Changing it invalidates every `phrase_hash` in both installations at once, and
     * the failure is not an error — the render path simply stops recognising every
     * phrase it has and starts inserting duplicates of all of them, which is the
     * defect M003 exists to fix, reintroduced wholesale. Changing this constant means
     * writing a migration that rehashes the table in the same deploy.
     */
    public function testTheAlgorithmIsPinned(): void
    {
        self::assertSame('sha256', PhraseIdentity::ALGORITHM);
        self::assertSame(
            hash('sha256', 'Save'),
            PhraseIdentity::hex('Save'),
            'the hash must be a bare sha256 of the phrase bytes, with nothing prepended, appended or '
            . 'normalized — the SQL backfill in M003 computes SHA2(phrase, 256) and the two have to agree'
        );
    }

    public function testTheEmptyPhraseHashesRatherThanFailing(): void
    {
        self::assertSame(PhraseIdentity::LENGTH, strlen(PhraseIdentity::raw('')));
    }

    /**
     * Long phrases are hashed, not truncated.
     *
     * The whole defect this class exists to close was a 2,000-character limit that
     * silently cut phrases short, after which nothing could recognise them. A digest
     * of a 50,000-character phrase must be as usable as any other.
     */
    public function testALongPhraseHashesToTheSameWidth(): void
    {
        $long = str_repeat('a really quite long phrase. ', 2000);

        self::assertGreaterThan(50000, strlen($long));
        self::assertSame(PhraseIdentity::LENGTH, strlen(PhraseIdentity::raw($long)));
        self::assertNotSame(
            PhraseIdentity::hex($long),
            PhraseIdentity::hex(substr($long, 0, 2000)),
            'a phrase and its first 2,000 characters must not share an identity — that equivalence is '
            . 'precisely what a prefix index would assert and what produced 5,088 duplicate rows'
        );
    }
}

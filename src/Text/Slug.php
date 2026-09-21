<?php

declare(strict_types=1);

namespace App\Text;

use Transliterator;

use function mb_strtolower;
use function preg_replace;
use function strtr;
use function trim;

/**
 * What `cocur/slugify` did for this application, in ICU and 223 rules of our own.
 *
 * Slugs are **URLs** — `SchoenstattTable::insertMissingAssociationSlugs()` writes them, and
 * `PublicationsTable` and `EventTextTable` generate a missing one on a *read* path and save
 * it back — so a slugifier that answers differently does not merely change a string, it
 * moves a page and breaks every link to it. That is why this is a transcription.
 *
 * ## Why not Symfony's AsciiSlugger
 *
 * laminas-exit.md §8 proposed it and measurement refused it. `new Slugify()` activates 19
 * rulesets, and `default.json` alone maps `ä→ae`, `ö→oe`, `ü→ue`, `ß→ss`, where ICU
 * transliteration gives `ä→a`. On a site called *Schönstatt* that rewrites slugs wholesale:
 * `schoenstatt-bewegung` would become `schonstatt-bewegung`. `AsciiSlugger('de')` narrows
 * the gap without closing it, and it would cost two new declared requirements —
 * `symfony/string`, and `symfony/translation-contracts`, without which `AsciiSlugger`
 * throws at class-load time. Trading a zero-dependency package for two is the wrong
 * direction. ICU is already here: `ext-intl` is required and `lib-icu` is pinned.
 *
 * ## What the rule map is, and how it was arrived at
 *
 * It is not hand-written. It is every character where cocur's active rulesets and ICU's
 * `Any-Latin; Latin-ASCII` disagree **and cocur has an opinion of its own** — derived
 * mechanically from the package's own `Resources/rules/*.json` while it was still
 * installed. Where cocur merely drops a character and ICU produces a letter, ICU wins and
 * no rule is recorded; that is the one deliberate behaviour change, it affects about 230
 * characters none of which occur in this database, and it is strictly an improvement
 * (`Ŋ` slugs as `n` now instead of vanishing).
 *
 * Longest keys first, because several rules are multi-character (`ει`, `ေါင်`) and `strtr()`
 * would otherwise let a one-character rule pre-empt them.
 *
 * ## What was measured before the package was removed
 *
 * Every distinct value this application slugs — 14,211 strings across association names and
 * their translations, publication and text titles, composition names and dictionary keys —
 * plus all 149 distinct characters in them, plus Latin-1 Supplement, Latin Extended-A/B,
 * Greek and Cyrillic in full: **zero differences**. `test/Text/slug-surface.php` is that
 * corpus, frozen.
 */
final class Slug
{
    /**
     * Characters where ICU and `cocur/slugify` disagreed, and cocur had an answer.
     *
     * @var array<string, string>
     */
    private const RULES = [
        'န်ုပ်' => 'nub', 'သြော' => 'aw', 'ိုက်' => 'aik', 'ိုင်' => 'aing', 'ိုဒ်' => 'ok',
        'ေါင်' => 'aung', 'ောက်' => 'auk', 'ောင်' => 'aung', 'ာန်' => 'an', 'ိတ်' => 'eik',
        'ိပ်' => 'eik', 'ိမ်' => 'ein', 'ုတ်' => 'ok', 'ုဒ်' => 'ait', 'ုန်' => 'on',
        'ုပ်' => 'ok', 'ုမ်' => 'on', 'ေတ်' => 'it', 'ွတ်' => 'ut', 'ွန်' => 'un', 'ွပ်' => 'ut',
        'ွမ်' => 'un', 'ΎΙ' => 'I', 'Ύι' => 'I', 'ΕΊ' => 'I', 'ΕΙ' => 'I', 'Εί' => 'I',
        'Ει' => 'I', 'ΟΊ' => 'I', 'ΟΙ' => 'I', 'Οί' => 'I', 'Οι' => 'I', 'ΥΊ' => 'I', 'ΥΙ' => 'I',
        'Υί' => 'I', 'Υι' => 'I', 'εί' => 'i', 'ει' => 'i', 'οί' => 'i', 'οι' => 'i', 'υί' => 'i',
        'υι' => 'i', 'ύι' => 'i', 'क़' => 'Qi', 'ख़' => 'Khi', 'ग़' => 'Ghi', 'ड़' => 'ugDha',
        'ढ़' => 'ugDhha', 'फ़' => 'Fi', 'य़' => 'Yi', 'က်' => 'et', 'စျ' => 'za', 'ပ်' => 'at',
        'မ်' => 'an', 'ယ်' => 'e', 'ိံ' => 'ein', 'ုံ' => 'on', 'ျွ' => 'ywa', 'ြွ' => 'yw',
        '@' => 'at', 'ª' => 'a', '°' => '0', '²' => '2', '³' => '3', '¹' => '1', 'º' => 'o',
        'Ä' => 'AE', 'Å' => 'AA', 'Ð' => 'Dj', 'Ö' => 'OE', 'Ø' => 'OE', 'Ü' => 'UE', 'ä' => 'ae',
        'å' => 'aa', 'ð' => 'dj', 'ö' => 'oe', 'ø' => 'oe', 'ü' => 'ue', 'Ə' => 'E', 'ə' => 'e',
        'Ή' => 'I', 'Ύ' => 'I', 'Β' => 'V', 'Η' => 'I', 'Υ' => 'I', 'Φ' => 'F', 'Ϋ' => 'I',
        'ή' => 'i', 'ΰ' => 'i', 'β' => 'v', 'η' => 'i', 'υ' => 'i', 'φ' => 'f', 'ϋ' => 'i',
        'ύ' => 'i', 'ϐ' => 'v', 'ϒ' => 'I', 'Є' => 'Ye', 'Ї' => 'Ji', 'Ж' => 'Zh', 'Й' => 'Y',
        'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch', 'Ю' => 'Yu', 'Я' => 'Ya', 'ж' => 'zh',
        'й' => 'y', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ю' => 'yu', 'я' => 'ya',
        'є' => 'ye', 'ї' => 'ji', 'Ը' => 'Y', 'Թ' => 'Th', 'Ժ' => 'Zh', 'Խ' => 'Kh', 'Ծ' => 'Ts',
        'Ձ' => 'Dz', 'Ղ' => 'Gh', 'Ճ' => 'Tch', 'Շ' => 'Sh', 'Ո' => 'Vo', 'Չ' => 'Ch', 'Ւ' => 'u',
        'Փ' => 'Ph', 'Ք' => 'Q', 'ը' => 'y', 'թ' => 'th', 'ժ' => 'zh', 'խ' => 'kh', 'ծ' => 'ts',
        'ձ' => 'dz', 'ղ' => 'gh', 'ճ' => 'tch', 'շ' => 'sh', 'ո' => 'vo', 'չ' => 'ch', 'ւ' => 'u',
        'փ' => 'ph', 'ք' => 'q', 'ج' => 'g', 'ذ' => 'th', 'ظ' => 'th', 'ع' => 'aa', 'ق' => 'k',
        'و' => 'o', 'आ' => 'aa', 'ई' => 'ii', 'ऊ' => 'uu', 'ऋ' => 'Ri', 'ऌ' => 'Li', 'ऍ' => 'ei',
        'ऎ' => 'ae', 'ऑ' => 'oi', 'ऒ' => 'oii', 'औ' => 'ou', 'छ' => 'Chha', 'ञ' => 'Nia',
        'ण' => 'Nae', 'द' => 'Tha', 'ध' => 'Thha', 'ऩ' => 'Ni', 'फ' => 'Fa', 'ब' => 'B',
        'ऱ' => 'Ri', 'ल' => 'L', 'ळ' => 'Li', 'ऴ' => 'Lii', 'श' => 'Sha', 'ष' => 'Shha',
        'ॐ' => 'oms', 'ॠ' => 'Ri', 'ॡ' => 'Lii', 'ခ' => 'kh', 'ဃ' => 'ga', 'စ' => 's',
        'ဆ' => 'sa', 'ဇ' => 'z', 'ဉ' => 'u', 'ဌ' => 'ta', 'ဎ' => 'da', 'ဏ' => 'na', 'ထ' => 'ta',
        'ဓ' => 'da', 'ဖ' => 'pa', 'ဘ' => 'ba', 'ရ' => 'ya', 'သ' => 'th', 'ဠ' => 'la', 'ဩ' => 'aw',
        'ဪ' => 'aw', 'ါ' => 'a', 'ာ' => 'a', 'ီ' => 'i', 'ူ' => 'u', 'ေ' => 'e', 'ဲ' => 'e',
        '်' => 'at', 'ြ' => 'y', 'ှ' => 'h', '၌' => 'hnaik', '၍' => 'ywae', '၏' => '-e',
        'ფ' => 'f', '⁴' => '4', '⁵' => '5', '⁶' => '6', '⁷' => '7', '⁸' => '8', '⁹' => '9',
        '₀' => '0', '₁' => '1', '₂' => '2', '₃' => '3', '₄' => '4', '₅' => '5', '₆' => '6',
        '₇' => '7', '₈' => '8', '₉' => '9',
    ];

    private static ?Transliterator $transliterator = null;

    /**
     * The slug for a title, a name or a key — lowercase ASCII words joined by hyphens.
     *
     * No length limit here: `SchoenstattTable::getSlug()` truncates to 50 characters and
     * `DictionaryTable` does not, and that difference predates this class.
     */
    public static function of(string $text): string
    {
        $text = strtr($text, self::RULES);

        $transliterated = self::transliterator()->transliterate($text);
        if (false !== $transliterated) {
            $text = $transliterated;
        }

        $text = mb_strtolower($text);
        $text = preg_replace('/[^A-Za-z0-9]+/', '-', $text) ?? '';

        return trim($text, '-');
    }

    private static function transliterator(): Transliterator
    {
        //Constructing one parses ICU rule data, so it is built once per process rather
        //than per slug. It holds no state between calls.
        return self::$transliterator ??= Transliterator::create('Any-Latin; Latin-ASCII')
            ?? throw new \RuntimeException('ICU has no Any-Latin; Latin-ASCII transliterator');
    }
}

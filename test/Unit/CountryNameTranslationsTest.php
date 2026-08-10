<?php

namespace SchoenstattTest\Unit;

use JTranslate\Model\CountriesInfo;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../module/JTranslate/src/Model/CountriesInfo.php';

/**
 * Country names answer for the locales an installation configured, not a fixed five.
 *
 * `getCountryNameTranslations()` used to hardcode `en_US`, `de_DE`, `pt_BR`, `es_ES` and
 * `fr_FR`. That was wrong in both directions at once on this installation: `it_IT` is
 * configured and got no country names at all, while `fr_FR` is configured nowhere and was
 * computed on every call. The locale list now comes from config; what stays static is the
 * ISO 639-1 → 639-3 table, because that is a fact about the vendored data rather than
 * about any installation.
 *
 * The class reads a JSON file and does no I/O of its own, so this lives in the
 * vendor-free unit suite and requires the file directly.
 */
class CountryNameTranslationsTest extends TestCase
{
    /** @var array<\stdClass> */
    private static array $countries;

    public static function setUpBeforeClass(): void
    {
        $json = file_get_contents(__DIR__ . '/../../module/JTranslate/data/countries.json');
        self::assertIsString($json, 'the vendored countries.json is missing');
        self::$countries = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
    }

    private function info(array $locales): CountriesInfo
    {
        return new CountriesInfo(self::$countries, $locales);
    }

    /**
     * Every requested locale gets a key, and it is the right language.
     */
    public function testItAnswersForTheConfiguredLocales(): void
    {
        $names = $this->info(['en_US', 'de_DE', 'it_IT'])->getCountryNameTranslations();

        self::assertSame(
            ['en_US' => 'Germany', 'de_DE' => 'Deutschland', 'it_IT' => 'Germania'],
            $names['Germany'],
            'the locales answered are not the locales asked for'
        );
    }

    /**
     * Italian was the locale the hardcoded list silently omitted, so it is the one worth
     * naming: it is configured on this installation and got nothing.
     */
    public function testALocaleTheOldHardcodedListOmittedIsAnswered(): void
    {
        $names = $this->info(['it_IT'])->getCountryNameTranslations();

        self::assertSame('Brasile', $names['Brazil']['it_IT']);
        self::assertSame('Giappone', $names['Japan']['it_IT']);
    }

    /**
     * A locale nobody configured is absent rather than computed.
     */
    public function testAnUnconfiguredLocaleIsNotAnswered(): void
    {
        $names = $this->info(['en_US', 'de_DE'])->getCountryNameTranslations();

        self::assertArrayNotHasKey(
            'fr_FR',
            $names['Germany'],
            'French was still computed, so the list is not really coming from config'
        );
    }

    /**
     * A configured locale the data cannot translate still gets a key.
     *
     * Falling back to the English name is what every missing translation already did. The
     * key must exist regardless, because a caller reading `$names[$country][$locale]`
     * should never have to test for it — the old shape guaranteed all five keys and the
     * new shape has to guarantee all configured ones.
     */
    public function testALanguageTheDataLacksFallsBackToEnglishRatherThanVanishing(): void
    {
        //Swahili is a real locale and is not among the thirteen languages countries.json
        //carries, so it exercises the fallback rather than a typo.
        $names = $this->info(['en_US', 'sw_KE'])->getCountryNameTranslations();

        self::assertArrayHasKey('sw_KE', $names['Germany']);
        self::assertSame('Germany', $names['Germany']['sw_KE']);
    }

    /**
     * Scotland must not be named after the United Kingdom in any language.
     *
     * It is built by cloning `GB` and overriding a handful of fields, so every
     * translation not explicitly overridden was the United Kingdom's name. While the
     * locale list was hardcoded to the four overridden languages that was invisible; it
     * surfaced the moment `it_IT` started being answered and Scotland came back as
     * "Regno Unito". The clone now clears the inherited names first.
     */
    public function testScotlandIsNotNamedAfterTheUnitedKingdom(): void
    {
        $locales = ['en_US', 'de_DE', 'es_ES', 'pt_BR', 'it_IT', 'nl_NL', 'ru_RU'];
        $names   = $this->info($locales)->getCountryNameTranslations();

        self::assertSame('Scozia', $names['Scotland']['it_IT']);
        self::assertSame('Schottland', $names['Scotland']['de_DE']);

        $uk = $names['United Kingdom'];
        foreach ($locales as $locale) {
            self::assertNotSame(
                $uk[$locale],
                $names['Scotland'][$locale],
                sprintf(
                    'Scotland is named "%s" in %s, which is the United Kingdom\'s name. It inherited it from '
                    . 'the GB clone. An unknown language must fall back to "Scotland", never to another country.',
                    $names['Scotland'][$locale],
                    $locale
                )
            );
        }
    }

    /**
     * The official name gets its own entry only when it differs from the common one.
     *
     * Both are keyed by the English string, so emitting an entry for an identical
     * official name would overwrite the common one with the same content — harmless, but
     * it means the count is a real assertion rather than a tautology.
     */
    public function testOfficialNamesAppearOnlyWhenTheyDiffer(): void
    {
        $names = $this->info(['en_US'])->getCountryNameTranslations();

        self::assertArrayHasKey('Germany', $names);
        self::assertArrayHasKey('Federal Republic of Germany', $names);
        self::assertSame('Federal Republic of Germany', $names['Federal Republic of Germany']['en_US']);
    }

    /**
     * An empty locale list is a caller mistake, not a request for nothing.
     */
    public function testAnEmptyLocaleListFallsBackToTheKeyLocale(): void
    {
        $names = $this->info([])->getCountryNameTranslations();

        self::assertSame(['en_US' => 'Germany'], $names['Germany']);
    }
}

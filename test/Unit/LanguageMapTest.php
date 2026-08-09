<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use JTranslate\I18n\LanguageMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../module/JTranslate/src/I18n/LanguageMap.php';

/**
 * The conversion `/api/v3/phrases` performs at every edge.
 *
 * The API speaks language codes and `trans_translations.locale` stores locales, so
 * this class sits on the boundary in both directions. Two of its properties are worth
 * pinning because getting either wrong is silent:
 *
 * 1. **Ambiguity throws.** With `pt_BR` and `pt_PT` both configured, `pt` no longer
 *    identifies a translation — and the failure that matters is the *write*, where a
 *    caller's Portuguese would be stored against whichever locale won. A test that
 *    only checked the happy path would pass forever while that hazard sat one config
 *    line away.
 * 2. **Order is preserved.** The phrase schema publishes `writable.languages`, so a
 *    set that reshuffles is a diff for every consumer that stores it.
 *
 * A unit test, so it needs no database, no container and no running app — see
 * `php composer.phar unit`.
 */
final class LanguageMapTest extends TestCase
{
    /** This site's five, in configuration order. */
    private const LOCALES = ['es_ES', 'de_DE', 'pt_BR', 'it_IT', 'en_US'];

    public function testItAnswersLanguagesInConfigurationOrder(): void
    {
        self::assertSame(
            ['es', 'de', 'pt', 'it', 'en'],
            (new LanguageMap(self::LOCALES))->languages(),
            'the schema publishes this list; reordering it is a diff for every consumer'
        );
    }

    public function testItMapsBothWays(): void
    {
        $map = new LanguageMap(self::LOCALES);

        self::assertSame('de_DE', $map->localeFor('de'));
        self::assertSame('de', $map->languageFor('de_DE'));
        self::assertSame('pt_BR', $map->localeFor('pt'));
        self::assertSame('en', $map->languageFor('en_US'));
    }

    /**
     * A stored locale nobody configured is not an error — `trans_translations` can
     * hold rows for a locale since dropped from the configuration, readable through
     * the GUI and writable through neither surface.
     */
    public function testAnUnconfiguredLocaleHasNoLanguage(): void
    {
        self::assertNull((new LanguageMap(self::LOCALES))->languageFor('fr_FR'));
    }

    public function testAnUnknownLanguageHasNoLocale(): void
    {
        $map = new LanguageMap(self::LOCALES);

        self::assertNull($map->localeFor('fr'));
        self::assertFalse($map->hasLanguage('fr'));
        self::assertTrue($map->hasLanguage('de'));
    }

    /**
     * The locale form is *not* silently accepted where a language is expected. It is
     * the mistake an agent is most likely to make, and resolving it here would make the
     * key a translation is stored under depend on which spelling the caller sent.
     */
    public function testALocaleIsNotALanguage(): void
    {
        $map = new LanguageMap(self::LOCALES);

        self::assertFalse($map->hasLanguage('de_DE'));
        self::assertNull($map->localeFor('de_DE'));
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function localeForms(): iterable
    {
        yield 'underscore' => ['de_DE', 'de'];
        yield 'hyphen'     => ['de-DE', 'de'];
        yield 'uppercase'  => ['DE_de', 'de'];
        yield 'bare'       => ['de', 'de'];
        yield 'three part' => ['zh_Hans_CN', 'zh'];
    }

    #[DataProvider('localeForms')]
    public function testLanguageOfAcceptsEverySpellingOfTheSameLocale(string $locale, string $expected): void
    {
        self::assertSame($expected, LanguageMap::languageOf($locale));
    }

    /**
     * The test this file exists for. Two locales sharing a primary subtag make the
     * mapping lossy in the direction that corrupts data.
     */
    public function testTwoLocalesOfOneLanguageAreRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/pt_BR.*pt_PT.*"pt"/');

        new LanguageMap(['pt_BR', 'pt_PT']);
    }

    /** The refusal has to name both offenders, or nobody can act on it. */
    public function testTheRefusalNamesWhatToDoAboutIt(): void
    {
        try {
            new LanguageMap(['en_US', 'en_GB']);
            self::fail('an ambiguous configuration was accepted');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('en_US', $e->getMessage());
            self::assertStringContainsString('en_GB', $e->getMessage());
        }
    }

    public function testAnEmptyConfigurationIsAllowedAndEmpty(): void
    {
        //Not a hazard: a site with nothing configured writes nothing, and the API
        //answers an empty writable set rather than throwing at the schema endpoint.
        self::assertSame([], (new LanguageMap([]))->languages());
    }
}

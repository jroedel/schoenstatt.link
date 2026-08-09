<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use App\Locale\Locales;
use JTranslate\I18n\LanguageMap;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * App\Locale\Locales duplicates `slm_locale`'s alias table; this is the guard that
 * keeps the copy honest.
 *
 * The duplication is deliberate — config/symfony/routes.php needs the alias pattern
 * to declare a route's `_locale` constraint, and reading the merged config there
 * would mean loading every laminas module before the first route exists, so
 * /_health would start paying for a module load. What makes that affordable is this
 * test: add a language to `slm_locale` and forget the class and a Symfony-served
 * route silently answers the new prefix in English, or does not answer it at all,
 * with nothing else to notice.
 *
 * ## And now a third copy of the same idea
 *
 * `/api/v3/phrases` addresses translations by language code — `de`, not `de_DE` —
 * derived by JTranslate\I18n\LanguageMap from `jtranslate.locales_to_translate`.
 * That derivation is independent of this alias table, and it should be: an API
 * language code is an ISO 639-1 subtag, not a URL segment somebody chose.
 *
 * But a visitor reading `/de/shrines` and an agent patching `{"de": …}` had better be
 * talking about the same language, and nothing structural forces that. An alias of
 * `br` for `pt_BR` would be perfectly legal here and would leave the API saying `pt`
 * for the page served at `/br/`. So the two are compared, and a divergence is a
 * failing test rather than a support question.
 */
class SymfonyLocaleAliasTest extends TestCase
{
    /**
     * The API's language codes and the site's URL aliases name the same languages.
     *
     * Compared as sets: the API publishes its list in configuration order and the
     * alias table is ordered for the language chooser, and neither order is the
     * other's business.
     */
    public function testTheApiLanguageCodesMatchTheUrlAliases(): void
    {
        //Derived from the merged config rather than from the container, like every
        //other assertion in this file: no database, so it runs on CI.
        $apiLanguages = (new LanguageMap($this->configuredLocales()))->languages();
        $urlAliases   = array_keys(Locales::ALIASES);

        sort($apiLanguages);
        sort($urlAliases);

        $this->assertSame(
            $urlAliases,
            $apiLanguages,
            'the language codes /api/v3/phrases accepts have drifted from the site\'s URL aliases, so '
            . 'an agent and a visitor no longer name the same language the same way'
        );
    }

    public function testTheAliasTableMatchesTheSlmLocaleConfiguration(): void
    {
        $slmLocale = $this->slmLocaleConfig();

        //assertEquals, not assertSame: the two orders differ on purpose. slm_locale
        //lists its aliases in no particular order because it only ever looks them
        //up, while Locales::ALIASES is ordered to match the language chooser's
        //dropdown in the layout — which the laminas layout hard-coded separately.
        $this->assertEquals(
            $slmLocale['aliases'],
            Locales::ALIASES,
            'App\Locale\Locales::ALIASES has drifted from slm_locale.aliases'
        );
        $this->assertSame(
            $slmLocale['default'],
            Locales::DEFAULT_LOCALE,
            'the locale an unprefixed path falls back to must be slm_locale.default'
        );
        $supported = array_values($slmLocale['supported']);
        $aliased   = array_values(Locales::ALIASES);
        sort($supported);
        sort($aliased);
        $this->assertSame(
            $supported,
            $aliased,
            'every supported locale needs a URL alias, and every alias a supported locale'
        );
        $this->assertSame(
            ['en_US', 'es_ES', 'de_DE', 'pt_BR', 'it_IT'],
            array_values(Locales::ALIASES),
            'the order is the language chooser dropdown order the laminas layout used'
        );
    }

    /**
     * The route constraint the aliases become. Constrained, not open: `/xx/shrines`
     * has to keep falling through to the `legacy` catch-all rather than being
     * swallowed by a two-segment pattern and answered in the default language.
     */
    public function testThePatternIsAnAlternationOfExactlyTheAliases(): void
    {
        $this->assertSame(implode('|', array_keys(Locales::ALIASES)), Locales::pattern());

        foreach (array_keys(Locales::ALIASES) as $alias) {
            $this->assertSame(1, preg_match('#^(' . Locales::pattern() . ')$#', $alias));
        }
        foreach (['xx', 'en_US', 'EN', 'e', 'ends'] as $notAnAlias) {
            $this->assertSame(
                0,
                preg_match('#^(' . Locales::pattern() . ')$#', $notAnAlias),
                "'$notAnAlias' must not match the locale constraint"
            );
        }
    }

    /** Round-tripping matters because the language chooser rewrites a path by alias. */
    public function testAliasAndLocaleRoundTrip(): void
    {
        foreach (Locales::ALIASES as $alias => $locale) {
            $this->assertSame($locale, Locales::localeFor($alias));
            $this->assertSame($alias, Locales::aliasFor($locale));
            $this->assertTrue(Locales::isAlias($alias));
        }
        $this->assertSame(Locales::DEFAULT_LOCALE, Locales::localeFor(null));
        $this->assertSame(Locales::DEFAULT_LOCALE, Locales::localeFor('xx'));
        $this->assertFalse(Locales::isAlias('xx'));
    }

    /**
     * The locales `/api/v3/phrases` derives its language codes from: what JTranslate is
     * configured to translate into, plus the key locale.
     *
     * `TranslationsTable::getLocales(true)` is the runtime authority and needs a
     * database; test/Integration/PhraseValidationParityTest asserts the two agree, so
     * reading the config here is safe and keeps this file container-free.
     *
     * @return list<string>
     */
    private function configuredLocales(): array
    {
        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        /** @var array<string, mixed> $jtranslate */
        $jtranslate = (new ServiceBridge($appConfig))->config()['jtranslate'];

        /** @var list<string> $locales */
        $locales   = $jtranslate['locales_to_translate'];
        $keyLocale = $jtranslate['key_locale'] ?? null;
        if (is_string($keyLocale) && ! in_array($keyLocale, $locales, true)) {
            $locales[] = $keyLocale;
        }

        return $locales;
    }

    /**
     * @return array{default: string, supported: list<string>, aliases: array<string, string>}
     */
    private function slmLocaleConfig(): array
    {
        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        /** @var array{default: string, supported: list<string>, aliases: array<string, string>} $slmLocale */
        $slmLocale = (new ServiceBridge($appConfig))->config()['slm_locale'];

        return $slmLocale;
    }
}

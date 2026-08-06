<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use App\Locale\Locales;
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
 */
class SymfonyLocaleAliasTest extends TestCase
{
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

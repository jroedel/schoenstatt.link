<?php

declare(strict_types=1);

namespace App\Locale;

use function array_keys;
use function array_search;
use function implode;
use function is_string;

/**
 * The five locales this site speaks, and the one-segment URL alias each answers
 * under.
 *
 * Under laminas this mapping lives in `config/autoload/juser.global.php`
 * (`slm_locale.aliases`) and SlmLocale\Strategy\UriPathStrategy applies it: it
 * strips `/en` off the path, calls \Locale::setDefault('en_US') and rewrites the
 * router's base URL so every assembled link keeps the prefix. None of that runs
 * for a Symfony-served route — SlmLocale is an MVC listener — so the map has to
 * exist on this side too.
 *
 * It is duplicated rather than read from the merged config on purpose:
 * config/symfony/routes.php needs the alias pattern to build its route
 * constraints, and reaching the merged config there would mean loading every
 * laminas module before the first route is even declared — i.e. /_health would
 * start paying for module loading. The duplication is guarded instead:
 * test/Integration/SymfonyLocaleAliasParityTest asserts this class and
 * `slm_locale` still agree, so a locale added to one and not the other fails a
 * test rather than silently serving the wrong language.
 */
final class Locales
{
    /**
     * URL alias => ICU locale, in the order the language chooser lists them.
     *
     * @var array<string, string>
     */
    public const ALIASES = [
        'en' => 'en_US',
        'es' => 'es_ES',
        'de' => 'de_DE',
        'pt' => 'pt_BR',
        'it' => 'it_IT',
    ];

    /**
     * What a request without a locale prefix gets, matching `slm_locale.default`.
     * SlmLocale would redirect such a request to the prefixed form; a ported route
     * answers it directly, so it has to pick a locale itself.
     */
    public const DEFAULT_LOCALE = 'en_US';

    /**
     * The alternation for a route's `_locale` requirement. Constrained rather
     * than open so a path whose first segment is something else keeps falling
     * through to the `legacy` catch-all.
     */
    public static function pattern(): string
    {
        return implode('|', array_keys(self::ALIASES));
    }

    /** Unknown aliases resolve to the default rather than throwing: the route constraint already rejected them. */
    public static function localeFor(?string $alias): string
    {
        //null is spelt out rather than left to the null-coalesce, because PHP 8.5
        //deprecates using null as an array offset
        return null === $alias ? self::DEFAULT_LOCALE : self::ALIASES[$alias] ?? self::DEFAULT_LOCALE;
    }

    public static function aliasFor(string $locale): string
    {
        $alias = array_search($locale, self::ALIASES, true);

        return is_string($alias) ? $alias : 'en';
    }

    public static function isAlias(string $segment): bool
    {
        return isset(self::ALIASES[$segment]);
    }
}

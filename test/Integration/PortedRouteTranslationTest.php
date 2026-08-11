<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use App\Laminas\TranslatorConfigurator;
use App\Twig\LaminasExtension;
use Laminas\Db\Adapter\Adapter;
use Laminas\Mvc\I18n\Translator as MvcTranslator;
use Locale;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Throwable;

use function implode;
use function is_dir;
use function is_readable;
use function is_string;
use function str_ends_with;
use function str_starts_with;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Ported pages translate, and translate from the right text domain.
 *
 * Guards the worst defect this migration has produced, found on production 2026-08-08
 * through the cookie canary and invisible for three days before that: **every**
 * translated string on **every** ported page was falling back to its English source, in
 * all four non-English languages. `/es/shrines` said "Schoenstatt shrine" where laminas
 * says "Santuario de Schoenstatt"; the `/es/` navbar said "Sign in · Register" instead of
 * "Acceder · Registrar".
 *
 * Two independent causes, both fixed, both guarded here:
 *
 * 1. **The translator had no sources.** Translations are exported to
 *    `language/<Domain>/<locale>.lang.php` and registered with the translator by
 *    JTranslate\Module::onBootstrap(), which a Symfony-served route never runs.
 *    App\Laminas\TranslatorConfigurator is the delegator that does it instead.
 * 2. **The text domain was wrong.** laminas assigns a domain per rendering context — the
 *    `translate` helper gets the controller's module namespace — and the phrases really
 *    are scattered accordingly. Measured in es_ES: `Shrines` exists *only* in `default`,
 *    while `Wayside shrines` and `Fr.` exist *only* in `Schoenstatt`. So each ported
 *    route declares its domain and LaminasExtension::translate() tries it before
 *    `default`.
 *
 * ## What this file can and cannot check
 *
 * The `.lang.php` exports are **gitignored** — TranslationsTable::writePhpTranslationArrays()
 * writes them when an admin edits a phrase — so a fresh checkout and CI have none, and an
 * end-to-end "does es_ES say Santuario" assertion would fail there for the wrong reason.
 * The tests below therefore split:
 *
 * - the wiring (a domain on every HTML route, the delegator's effect on the translator)
 *   is asserted unconditionally;
 * - the actual lookup is asserted only when an export exists, and skips loudly otherwise.
 *
 * The exhaustive check is not automatable here at all, and is documented in
 * docs/strangler.md instead: render all five locales through *both* front controllers and
 * diff. That is what found this, and what confirmed the fix — 65 of 65 responses
 * identical across 5 locales × 13 routes.
 */
class PortedRouteTranslationTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    public static function setUpBeforeClass(): void
    {
        Locale::setDefault('es_ES');
    }

    /**
     * Every ported route that renders HTML declares the module text domain its strings
     * live in. Without one, `translate()` sees only `default` and a phrase that lives in
     * a module domain renders in English — which is exactly the bug, in the half that a
     * working translator does not fix.
     *
     * Derived from the route collection rather than a list, so a route added next month
     * is covered. The JSON and maintenance routes are exempt: they render no template.
     */
    public function testEveryPortedHtmlRouteDeclaresATextDomain(): void
    {
        $missing = [];
        foreach ($this->routes() as $name => $route) {
            if ('legacy' === $name || ! $this->rendersHtml($name)) {
                continue;
            }
            $domain = $route->getDefault(LaminasExtension::TEXT_DOMAIN_ATTRIBUTE);
            if (! is_string($domain) || '' === $domain) {
                $missing[] = $name;
            }
        }

        self::assertSame(
            [],
            $missing,
            'these ported HTML routes declare no text domain, so any phrase of theirs that lives in a '
            . 'module domain will render in English: ' . implode(', ', $missing)
        );
    }

    /** The declared domains must be real ones, not typos — a typo silently translates nothing. */
    public function testEveryDeclaredTextDomainIsOneTheApplicationUses(): void
    {
        $known = ['Application', 'Books', 'JTranslate', 'JUser', 'Schoenstatt', 'SionModel', 'default'];

        foreach ($this->routes() as $name => $route) {
            $domain = $route->getDefault(LaminasExtension::TEXT_DOMAIN_ATTRIBUTE);
            if (! is_string($domain) || '' === $domain) {
                continue;
            }
            self::assertContains(
                $domain,
                $known,
                "route \"$name\" declares text domain \"$domain\", which is not a module of this application"
            );
        }
    }

    /**
     * The delegator did what onBootstrap does. Asserted through effects that need no
     * export file: the fallback locale (which is what makes an untranslated phrase come
     * out in English rather than empty) and the event manager the missing-translation
     * reporter attaches to.
     */
    public function testTheTranslatorIsConfiguredTheWayOnBootstrapConfiguresIt(): void
    {
        $this->requireDatabase();

        $translator = $this->bridge()->get('MvcTranslator');
        self::assertInstanceOf(MvcTranslator::class, $translator);

        self::assertSame(
            TranslatorConfigurator::FALLBACK_LOCALE,
            $translator->getFallbackLocale(),
            'no fallback locale: the delegator did not run. It must be keyed on the canonical '
            . 'Laminas\Mvc\I18n\Translator, not on the MvcTranslator alias — an alias is resolved '
            . 'before delegators are looked up, so one registered under it never fires.'
        );
        self::assertTrue(
            $translator->isEventManagerEnabled(),
            'the event manager is off, so JTranslate\'s missing-translation reporter cannot attach '
            . 'and the phrase table will stop learning what ported pages need'
        );
    }

    /**
     * The lookup itself, on a phrase measured to live only in a module domain. This is
     * the assertion that fails if either half regresses — no sources, or the wrong
     * domain.
     *
     * Skipped without an export, because those files are gitignored and generated.
     */
    public function testAPhraseThatLivesOnlyInAModuleDomainIsTranslated(): void
    {
        $this->requireDatabase();
        if (! is_dir(__DIR__ . '/../../module/Schoenstatt/language')) {
            self::markTestSkipped(
                'no module/Schoenstatt/language export (gitignored, written by '
                . 'TranslationsTable::writePhpTranslationArrays) — nothing to look up'
            );
        }

        $phrase = $this->aModuleOnlyPhrase();

        $translator = $this->bridge()->get('MvcTranslator');
        self::assertInstanceOf(MvcTranslator::class, $translator);

        $inModuleDomain = $translator->translate($phrase, 'Schoenstatt');
        self::assertNotSame(
            $phrase,
            $inModuleDomain,
            'the Schoenstatt domain resolved nothing for "' . $phrase . '": the translator has no '
            . 'file patterns, i.e. the delegator is not registering them'
        );

        //and the domain really is the only place it lives, which is what makes the
        //page-domain-then-default order necessary rather than cosmetic
        self::assertSame(
            $phrase,
            $translator->translate($phrase, 'default'),
            '"' . $phrase . '" resolves in `default` too, though the phrase table says it lives '
            . 'only in `Schoenstatt` — the export on disk is stale'
        );
    }

    /**
     * A phrase that lives in the Schoenstatt domain and nowhere else, chosen at run
     * time rather than pinned.
     *
     * It used to be the constant above, and that rotted the moment phrase discovery
     * started working on Symfony-served routes: `App\Twig\LaminasExtension::translate()`
     * looks a miss up in the page's domain *and* in `default`, so both lookups are
     * recorded, and JTranslate copies the existing translations onto the new row. The
     * phrase then resolves in `default` and no longer demonstrates anything. Picking
     * one from the table keeps the assertion meaningful without needing a human to
     * notice and repin it.
     */
    private function aModuleOnlyPhrase(): string
    {
        /** @var Adapter $adapter */
        $adapter = $this->bridge()->get(Adapter::class);
        $rows    = $adapter->query(
            'SELECT p.phrase FROM trans_phrases p'
            . ' JOIN trans_translations t ON t.translation_phrase_id = p.translation_phrase_id'
            . ' WHERE p.text_domain = ? AND p.retired_on IS NULL AND t.locale = ?'
            . " AND t.translation <> '' AND t.translation <> p.phrase"
            . ' AND NOT EXISTS ('
            . '   SELECT 1 FROM trans_phrases d'
            . "   WHERE d.text_domain = 'default' AND d.phrase = p.phrase"
            . ' ) ORDER BY p.translation_phrase_id LIMIT 1',
            ['Schoenstatt', 'es_ES']
        );

        foreach ($rows as $row) {
            $phrase = $row['phrase'] ?? null;
            if (is_string($phrase) && '' !== $phrase) {
                return $phrase;
            }
        }

        self::markTestSkipped(
            'no phrase left that lives only in the Schoenstatt domain and is translated into '
            . 'es_ES, so there is nothing to demonstrate the page-domain-then-default order with'
        );
    }

    /** True for a route whose controller renders a Twig template. */
    private function rendersHtml(string $name): bool
    {
        //the whole of v3, by prefix. An API for automated agents emits field names and
        //validation messages, not localized prose, and giving it a text domain would
        //start translating the messages an agent parses.
        //
        //Matched on the prefix rather than enumerated, because the enumeration was
        //wrong the first time it was tested: the phrase endpoints were added and this
        //test failed on eight route names that were never going to render a template.
        //A list that has to be extended by hand every time v3 grows is a list that
        //fails for the wrong reason, and the temptation then is to make the *route*
        //declare a domain it does not want.
        if (str_starts_with($name, 'api-v3/')) {
            return false;
        }

        //the two maintenance endpoints and /_health answer JSON; the GeoJSON pair does
        //too. Enumerated because these are individual routes among HTML siblings, not
        //a namespace.
        $jsonOnly = ['health', 'sm-cache-status', 'sm-clear-persistent-cache',
                     'api-v1/shrines-json', 'api-v2/shrines-json'];
        foreach ($jsonOnly as $json) {
            if ($name === $json || $name === $json . '.locale') {
                return false;
            }
        }

        return true;
    }

    /** @return iterable<string, Route> */
    private function routes(): iterable
    {
        /** @var RouteCollection $routes */
        $routes = require __DIR__ . '/../../config/symfony/routes.php';

        foreach ($routes as $name => $route) {
            //both twins carry the same defaults; checking the unprefixed one is enough,
            //and RouteAccess::ATTRIBUTE is what proves they are the same instance
            if (str_ends_with((string) $name, '.locale')) {
                continue;
            }
            yield (string) $name => $route;
        }
    }

    private function requireDatabase(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        try {
            /** @var Adapter $adapter */
            $adapter = $this->bridge()->get(Adapter::class);
            $adapter->getDriver()->getConnection()->connect();
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }
    }

    private function bridge(): ServiceBridge
    {
        if (null !== self::$bridge) {
            return self::$bridge;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        return self::$bridge = new ServiceBridge($appConfig);
    }
}

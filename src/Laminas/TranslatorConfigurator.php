<?php

declare(strict_types=1);

namespace App\Laminas;

use JTranslate\I18n\Translator\TranslatorEventListener;
use JTranslate\Model\TranslationsTable;
use Laminas\I18n\Translator\Translator;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Mvc\I18n\Translator as MvcI18nTranslator;
use Laminas\ModuleManager\ModuleManager;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use Laminas\Validator\AbstractValidator;
use Locale;
use Psr\Container\ContainerInterface;

use function array_key_exists;
use function file_exists;
use function getcwd;
use function glob;
use function is_array;
use function str_replace;

/**
 * Gives a Symfony-served route the translator that JTranslate\Module::onBootstrap()
 * gives every other request.
 *
 * ## The bug this exists for
 *
 * Measured on production 2026-08-08, through the cookie canary, comparing the two
 * front controllers on the same server and the same data:
 *
 *     /es/shrines  kind label   laminas "Santuario de Schoenstatt"  symfony "Schoenstatt shrine"
 *     /de/shrines  kind label   laminas "Schönstatt-Heiligtum"     symfony "Schoenstatt shrine"
 *     /es/ navbar               laminas "Acceder · Registrar"      symfony "Sign in · Register"
 *
 * **Every** translated string on a ported page was falling back to its English
 * source, in all four non-English languages. Not a shrine bug — site-wide.
 *
 * The cause is that translations are not loaded from the database at request time.
 * They are exported to `language/<TextDomain>/<locale>.lang.php` (and
 * `module/<Module>/language/…`) by TranslationsTable::writePhpTranslationArrays(),
 * and it is `onBootstrap` that registers those files with the translator. A
 * Symfony-served route never boots laminas-mvc, so the translator had no sources at
 * all and `translate()` returned the phrase it was handed.
 *
 * It is worse than missing labels, because the translator is also used to build
 * *data*: Schoenstatt\Model\SchoenstattTable composes `nameByLocale` by calling
 * `translate()`, so three of the 43 wayside shrines came out named in Spanish or
 * French on the English page, and the slugs and JSON-LD alternateName derive from
 * that same array.
 *
 * ## Why a delegator and not a kernel listener
 *
 * App\Http\SessionListener reproduces JUser's `onBootstrap` as a kernel.request
 * listener, and the obvious move was to do the same here. A delegator is better for
 * one reason: **it is lazy**. Building this needs TranslationsTable (a database
 * query for the locale list) and the module manager, and a listener would spend that
 * on every ported request including `/_health` and the two maintenance endpoints —
 * exactly the cost docs/strangler.md says those must not pay. A delegator runs when
 * something first asks for the translator, which is precisely when it is needed.
 *
 * It also cannot be forgotten. Templates reach the translator through
 * App\Twig\LaminasExtension and models get it injected by their own factories;
 * both resolve `MvcTranslator` from this container, so both are covered by one
 * hook. A listener would have had to be remembered by whoever added the next
 * consumer.
 *
 * Proven safe to attach to `MvcTranslator` alone because there is only one
 * translator: `MvcTranslator`, `jtranslate_translator` and
 * `SchoenstattTable::$translator` were measured to be the *same instance*, wrapping
 * the one `Laminas\I18n\Translator\Translator` that `TranslatorInterface` resolves
 * to.
 *
 * ## Faithfulness
 *
 * Every step below is `onBootstrap`'s, in its order, including the parts that look
 * skippable:
 *
 * - `enableEventManager()` before attaching the listener, or the attach is a no-op.
 * - `setFallbackLocale('en_US')`, which is what makes an untranslated phrase come
 *   out in English rather than empty.
 * - `TranslatorEventListener`, which does **not** load translations — it records
 *   *missing* ones to the database, which is how the phrase table gets populated.
 *   Dropping it would quietly stop that, and the next translator would never learn
 *   which phrases a ported page needs.
 * - `setUserModules()` on the table, which decides whether a domain's export goes to
 *   `module/<M>/language` or to `language/<M>`.
 *
 * `getcwd()` is the original's, and safe: both entry points chdir() to the project
 * root (public/index.php:12, bin/console:35).
 */
final class TranslatorConfigurator implements DelegatorFactoryInterface
{
    /** What onBootstrap falls back to. Untranslated phrases then read as English, not as nothing. */
    public const FALLBACK_LOCALE = 'en_US';

    private const FILE_PATTERN = '%s.lang.php';

    /**
     * @param string $name
     * @param callable(): mixed $callback
     * @param array<string, mixed>|null $options
     */
    public function __invoke(
        ContainerInterface $container,
        $name,
        callable $callback,
        ?array $options = null
    ): mixed {
        /** @var mixed $translator */
        $translator = $callback();
        if (! $translator instanceof TranslatorInterface) {
            return $translator;
        }

        $this->configure($container, $translator);

        return $translator;
    }

    private function configure(ContainerInterface $container, TranslatorInterface $translator): void
    {
        //onBootstrap calls these on the Mvc wrapper, which forwards them to the inner
        //translator through __call(). Reaching the inner one explicitly via
        //getTranslator() is the same object and the same effect, and it is typed — the
        //__call route is invisible to static analysis, so PHPStan level 8 rejects every
        //one of these six calls on the wrapper.
        if (! $translator instanceof MvcI18nTranslator) {
            return;
        }
        $inner = $translator->getTranslator();
        if (! $inner instanceof Translator) {
            return;
        }

        $inner->enableEventManager();
        $inner->setLocale(Locale::getDefault());
        $inner->setFallbackLocale(self::FALLBACK_LOCALE);

        /** @var TranslationsTable $table */
        $table = $container->get(TranslationsTable::class);
        //getLocales(TRUE): the argument includes the key locale, and without it an
        //English page view can never discover a phrase. JTranslate\Module passes the
        //same thing — a discrepancy would mean discovery worked under one front
        //controller and not the other. See TranslatorEventListener's class docblock.
        (new TranslatorEventListener($table, $table->getLocales(true)))->attach($inner->getEventManager());

        //The listener above only *queues* a miss; TranslationsTable::flush() writes
        //it, and laminas calls that from MvcEvent::EVENT_FINISH, which a
        //Symfony-served route never reaches. Arming here rather than having the
        //kernel ask for the table is what keeps /_health and the maintenance
        //endpoints from building one. See App\Laminas\PhraseFlush.
        if ($container->has(PhraseFlush::class)) {
            /** @var PhraseFlush $phrases */
            $phrases = $container->get(PhraseFlush::class);
            $phrases->arm($table);
        }

        //Validator messages are translated as templates, before laminas fills in
        //%value%/%hostname%/%min%, so a stranger's mistyped input never reaches the
        //translator and never becomes a phrase. JTranslate\Module does the same on
        //the laminas side; a discrepancy would mean the two front controllers filed
        //different phrases for the same failed form. The renderers that used to
        //translate the finished message are off in step with this — see
        //SionModel\Form\BootstrapFormRenderer::errors().
        //
        //The static setter is what laminas-validator itself offers; it is set on the
        //Mvc wrapper because that, not the inner translator, is what implements
        //Laminas\Validator\Translator\TranslatorInterface.
        AbstractValidator::setDefaultTranslator($translator, 'default');

        $modules = $this->moduleLanguageDirectories($container);
        $table->setUserModules($modules);
        foreach ($modules as $module => $directory) {
            if (file_exists($directory)) {
                $inner->addTranslationFilePattern('phpArray', $directory, self::FILE_PATTERN, $module);
            }
        }

        //the domains that are not modules land under the project's own language/,
        //one directory per text domain. It is gitignored and generated, so its
        //absence is normal on a fresh checkout and must not be an error.
        $root = getcwd() . '/language';
        if (! file_exists($root)) {
            return;
        }
        foreach ($this->subdirectories('language/*') as $domain) {
            $inner->addTranslationFilePattern(
                'phpArray',
                $root . '/' . $domain,
                self::FILE_PATTERN,
                $domain
            );
        }
    }

    /**
     * `module/<M>/language` for every *loaded* module, keyed by module name.
     *
     * The loaded-modules filter is the original's and matters: `module/` also holds
     * directories for modules `config/modules.config.php` does not enable, and
     * registering a pattern for one would let a disabled module's stale export
     * translate a live page.
     *
     * @return array<string, string>
     */
    private function moduleLanguageDirectories(ContainerInterface $container): array
    {
        /** @var ModuleManager $manager */
        $manager = $container->get(ModuleManager::class);
        $loaded  = $manager->getLoadedModules();

        $modules = [];
        foreach ($this->subdirectories('module/*') as $module) {
            if (array_key_exists($module, $loaded)) {
                $modules[$module] = getcwd() . '/module/' . $module . '/language';
            }
        }

        return $modules;
    }

    /**
     * @return list<string>
     */
    private function subdirectories(string $pattern): array
    {
        $found = glob($pattern, GLOB_ONLYDIR);
        if (! is_array($found)) {
            return [];
        }

        $prefix = str_replace('*', '', $pattern);
        $names  = [];
        foreach ($found as $dir) {
            $names[] = str_replace($prefix, '', $dir);
        }

        return $names;
    }
}

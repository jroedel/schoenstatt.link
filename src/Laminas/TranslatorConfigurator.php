<?php

declare(strict_types=1);

namespace App\Laminas;

use JTranslate\I18n\Translator\Translator;
use JTranslate\I18n\Translator\TranslatorEventListener;
use JTranslate\Model\TranslationsTable;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use SionModel\Validator\AbstractValidator;
use Locale;
use Psr\Container\ContainerInterface;

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
 * exactly the cost docs/laminas-exit.md says those must not pay. A delegator runs when
 * something first asks for the translator, which is precisely when it is needed.
 *
 * It also cannot be forgotten. Templates reach the translator through
 * App\Twig\LaminasExtension and models get it injected by their own factories;
 * both resolve `MvcTranslator` from this container, so both are covered by one
 * hook. A listener would have had to be remembered by whoever added the next
 * consumer.
 *
 * Proven safe to attach to one id alone because there is only one translator:
 * `MvcTranslator`, `jtranslate_translator`, `Laminas\Translator\TranslatorInterface` and
 * `SchoenstattTable::$translator` were measured to be the *same instance*. They are all
 * aliases of `JTranslate\I18n\Translator\Translator`, which is the id this delegator is
 * registered on — aliases resolve before delegators are looked up, so registering it on
 * any of the other names would silently never run.
 *
 * ## Faithfulness
 *
 * Every step below is `onBootstrap`'s, in its order, including the parts that look
 * skippable:
 *
 * - the missing-translation listener, which since 2026-09 is a plain callable on the
 *   translator rather than an event-manager attachment. There is no `enableEventManager()`
 *   to forget any more; that call was load-bearing here for years because without it the
 *   attach was a silent no-op.
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
        if (! $translator instanceof Translator) {
            return $translator;
        }

        $this->configure($container, $translator);

        return $translator;
    }

    private function configure(ContainerInterface $container, Translator $inner): void
    {
        $inner->setLocale(Locale::getDefault());
        $inner->setFallbackLocale(self::FALLBACK_LOCALE);

        //JTranslate's missing-translation reporter, attached on the first miss rather than
        //now. The table it needs is built through JUser's user table, whose factory builds
        //JUser's mailer, whose factory asks for this very translator — so resolving it
        //while the translator is still under construction recurses until memory runs
        //out (measured). A miss can only happen once the translator exists, which is
        //exactly late enough.
        //
        //getLocales(TRUE): the argument includes the key locale, and without it an
        //English page view can never discover a phrase. JTranslate\Module passes the
        //same thing. See TranslatorEventListener's class docblock.
        $bootstrap = static function (
            string $message,
            string $locale,
            string $textDomain
        ) use (
            $inner,
            $container
        ): ?string {
            $inner->clearMissingTranslationListeners();
            /** @var TranslationsTable $table */
            $table    = $container->get(TranslationsTable::class);
            $listener = new TranslatorEventListener($table, $table->getLocales(true));
            $inner->onMissingTranslation($listener);
            //The listener only *queues* a miss; TranslationsTable::flush() writes it, from
            //the kernel's terminate listener. Armed here, on the first miss, so that a
            //request that misses nothing never builds the table. See App\Laminas\PhraseFlush.
            if ($container->has(PhraseFlush::class)) {
                /** @var PhraseFlush $phrases */
                $phrases = $container->get(PhraseFlush::class);
                $phrases->arm($table);
            }
            //this miss too: the listener that replaced this one is not consulted for the
            //very lookup that installed it
            $listener->missingTranslation($message, $locale, $textDomain);

            return null;
        };
        $inner->onMissingTranslation($bootstrap);

        //Validator messages are translated as templates, before laminas fills in
        //%value%/%hostname%/%min%, so a stranger's mistyped input never reaches the
        //translator and never becomes a phrase. JTranslate\Module does the same on
        //the laminas side; a discrepancy would mean the two front controllers filed
        //different phrases for the same failed form. The renderers that used to
        //translate the finished message are off in step with this — see
        //SionModel\Form\BootstrapFormRenderer::errors().
        //
        //The static setter is the one seam a validator built inside a form specification
        //has: no container, no request, nowhere to inject. `SionModel\Validator\AbstractValidator`
        //keeps laminas' shape for exactly that reason.
        //
        //Missing this line does not fail: it renders. Every validation message comes out in
        //its source English while the rest of the page is translated, which is what the
        //rendered-markup baseline caught when the rule library landed — laminas' static was
        //still being set and ours was not, so 35 forms said "The form submitted did not
        //originate from the expected site" where the catalog says "The form submitted was
        //expired, please resubmit".
        AbstractValidator::setDefaultTranslator($inner, 'default');

        //The same map the writer side gets from App\Laminas\TranslationsTableConfigurator,
        //a delegator on the table itself — because a caller that *exports* catalogs must
        //not depend on whether a translator was ever built. See
        //App\Laminas\ModuleLanguageDirectories.
        $modules = ModuleLanguageDirectories::forContainer($container);
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

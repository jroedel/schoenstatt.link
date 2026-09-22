<?php

declare(strict_types=1);

namespace App\Laminas;

use JTranslate\Model\TranslationsTable;
use Psr\Container\ContainerInterface;

/**
 * Tells `TranslationsTable` which text domains are modules, whoever built it.
 *
 * `setUserModules()` is the only thing that decides where
 * `writePhpTranslationArrays()` puts a catalog: `module/<M>/language/<locale>.lang.php`
 * for a loaded module, `language/<M>/<locale>.lang.php` for every other text domain.
 * `JTranslate\Module::onBootstrap()` calls it on every laminas request, and
 * `ExportCatalogsCommand` calls it for itself, so the two paths that existed before the
 * Symfony front controller were both covered.
 *
 * ## Why this is a delegator on the table and not one more line in the kernel
 *
 * `App\Laminas\TranslatorConfigurator` already called it — but that runs when something
 * asks for a **translator**, and the caller that needs it most does not.
 * `JTranslate\Controller\PhraseEditController`'s successful save *redirects*: nothing
 * renders, nothing translates, no translator is built, and the export ran with an empty
 * map. Measured 2026-09-08 by a smoke test asserting the catalog contains what was just
 * saved; it did not, because the file had been written somewhere else.
 *
 * Hanging it off the table instead makes the guarantee unconditional — any consumer, on
 * either front controller, gets a table that knows where to write. That is worth more than
 * one call site's tidiness, because the failure it prevents is invisible: both directories
 * are registered as *read* paths, so a misplaced catalog is still loaded and the page looks
 * right. What goes wrong is the pair of copies. The console export rewrites the module one,
 * the GUI wrote the other, `language/*` patterns are registered last and therefore win, and
 * a translation deleted through the GUI goes on being served from the copy nothing rewrote.
 *
 * ## It does not make the translator delegator's call redundant
 *
 * That one stays, because it is `onBootstrap`'s step in `onBootstrap`'s order and the
 * faithfulness of that reproduction is checked as a whole. Calling the setter twice with
 * the same map costs one `glob()`.
 */
final class TranslationsTableConfigurator
{
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
        /** @var mixed $table */
        $table = $callback();
        if ($table instanceof TranslationsTable) {
            $table->setUserModules(ModuleLanguageDirectories::forContainer($container));
        }

        return $table;
    }
}

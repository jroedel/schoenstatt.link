<?php

declare(strict_types=1);

namespace App\Twig;

use App\Books\CurrentLibrary;
use App\Form\BootstrapFormRenderer;
use App\Http\CspNonce;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Laminas\ViewHelpers;
use App\View\SiteChrome;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function dirname;
use function is_dir;
use function is_writable;
use function mkdir;

/**
 * Builds the Twig environment the ported HTML routes render through.
 *
 * Three settings are decisions rather than defaults:
 *
 * **autoescape: html.** Twig's default, kept, and the whole reason a Twig layout
 * is safer than the .phtml it replaces — those escaped by hand, per interpolation,
 * and did not always remember. What that costs is one obligation, discharged in
 * App\Twig\LaminasExtension: a function returning markup has to say so
 * (`is_safe: html`), or its output arrives double-escaped.
 *
 * **strict_variables: true**, which laminas' PhpRenderer was the opposite of — it
 * returns null for anything undefined. Turning it on is only safe because the data
 * was checked: every array key the shrine templates read is present in all 207 rows
 * (measured), and the two the original guarded with isset() are guarded here with
 * `is defined`. The payoff is that a renamed column or a typo'd variable fails
 * loudly instead of rendering a blank cell nobody notices for a year.
 *
 * **The compile cache is used when it can be, and skipped when it cannot.** Twig
 * compiles each template to PHP; on a site running with APP_ENV=production and
 * OPcache on, recompiling every request is waste. But the directory has to be
 * writable by the web server, and that is not something to assume: in the capsule
 * `data/cache` was root-owned when this was written, and production is deployed
 * over SFTP by phploy, which creates no directories. So writability is *checked*
 * rather than hoped for, and a failure degrades to in-memory compilation — a slower
 * page, not a 500 on the first HTML route ported. `data/cache/*` is already
 * gitignored, so the cache needs no new ignore rule.
 *
 * That probe alone did not keep the promise, and App\Twig\ForgivingCache is why it
 * now does: Twig writes into a per-template subdirectory it creates *itself*, not
 * into the directory probed here, so one render performed by a different user leaves
 * a subdirectory this check cannot see and the write throws mid-render. Read that
 * class before touching the cache wiring — it cost every ported HTML route an empty
 * 200 on 2026-08-07.
 *
 * **auto_reload: true**, and not as a leftover from development. Twig keys a
 * compiled file by a hash of the template's *name*, not of its contents, so with
 * auto_reload off an edited template is simply never recompiled — measured the hard
 * way here, on a template edit that produced no change in the response. Since the
 * deploy is a phploy file sync with no Twig cache-warming or purging step, "check
 * the mtime" is the only thing that makes a deployed template change take effect.
 * It costs one stat() per rendered template.
 */
final class TwigFactory
{
    public const CACHE_DIR = 'data/cache/twig';
    public const TEMPLATE_DIR = 'templates';

    /**
     * The whole Twig layer, wired. Kept in one method rather than assembled at the
     * call site so that App\Kernel and the integration tests build the *same*
     * environment — a test rendering against a differently-wired Twig would prove
     * very little about the page a visitor gets.
     */
    public function create(
        ServiceBridge $laminas,
        ViewHelpers $helpers,
        RouteUrl $urls,
        RequestStack $requests,
        CspNonce $nonce
    ): Environment {
        $root = dirname(__DIR__, 2);

        $cacheDir = $this->cacheDir($root . '/' . self::CACHE_DIR);

        $twig = new Environment(new FilesystemLoader($root . '/' . self::TEMPLATE_DIR, $root), [
            'autoescape'       => 'html',
            'strict_variables' => true,
            //FORCE_BYTECODE_INVALIDATION is not decoration: it is what Environment
            //passes when it builds the FilesystemCache itself from a string and
            //auto_reload is on, and building the cache by hand silently drops it.
            //Without it a rewritten compiled template stays in OPcache — and with
            //`revalidate_freq=2` in both the capsule and production, "my template
            //edit did nothing" would be back, one layer deeper than last time.
            'cache'            => null === $cacheDir
                ? false
                : new ForgivingCache(new FilesystemCache($cacheDir, FilesystemCache::FORCE_BYTECODE_INVALIDATION)),
            'auto_reload'      => true,
        ]);
        $laminasExtension = new LaminasExtension($laminas, $helpers, $urls, $requests);
        $twig->addExtension($laminasExtension);
        //The form helpers, translating through the same page-aware translate() the
        //rest of the templates use — a form label lives in its module's text domain
        //just as a heading does, and a renderer with its own translator would render
        //every label in English on /es.
        $twig->addExtension(new FormExtension(
            new BootstrapFormRenderer($laminasExtension->translate(...))
        ));
        $twig->addExtension(new ChromeExtension(
            new SiteChrome(
                $laminas,
                $helpers,
                $urls,
                new CurrentLibrary($laminas),
                $laminasExtension->translate(...)
            ),
            $requests,
            $nonce
        ));
        $twig->addExtension(new MarkdownExtension());
        //One page minifies its inline script on laminas; see the extension's docblock.
        $twig->addExtension(new ScriptExtension());

        return $twig;
    }

    /** Null when nothing here can be written to, which is a reason to compile in memory, not to fail. */
    private function cacheDir(string $path): ?string
    {
        if (is_dir($path)) {
            return is_writable($path) ? $path : null;
        }
        if (! is_dir(dirname($path)) || ! is_writable(dirname($path))) {
            return null;
        }

        return @mkdir($path, 0775, true) && is_writable($path) ? $path : null;
    }
}

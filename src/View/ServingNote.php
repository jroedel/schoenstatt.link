<?php

declare(strict_types=1);

namespace App\View;

use function implode;

/**
 * The one-line "how was this page served" note in every page footer.
 *
 * A temporary migration instrument, and the reason it exists is that the answer is
 * genuinely not guessable from looking at a page. While the strangler runs there are
 * **three** ways a request can be answered, and two of them render identically:
 *
 *   1. Laminas\Mvc\Application  → .phtml            (SYMFONY_KERNEL off for this visitor)
 *   2. App\Kernel               → Twig              (a ported route)
 *   3. App\Kernel → LegacyBridge → .phtml           (an unported route; most of the site)
 *
 * Rows 1 and 3 produce the same markup by design — that is the whole point of the
 * bridge — so "which front controller am I on" cannot be read off the page, and the
 * cookie that decides it is invisible too. Hence a note that states each fact
 * separately.
 *
 * **Every field is reported independently rather than derived from a conclusion**, and
 * that is deliberate: if the front controller and the renderer ever disagree in a way
 * this file does not expect, the note shows the contradiction instead of smoothing it
 * into a plausible sentence. A diagnostic that cannot report a surprise is not worth
 * having.
 *
 * The most useful field is the last one. `SYMFONY_KERNEL=1 from server config; the
 * sl_symfony_canary=0 cookie is being ignored` is a real state — it is what the Docker
 * capsule always says, because docker/apache-vhost.conf uses `SetEnv` and mod_env beats
 * all of mod_setenvif — and without it "my cookie did nothing" looks like a bug in the
 * toggle rather than the environment overruling it.
 *
 * **Removing this is one commit**, and it should happen when the migration ends: delete
 * the two `serving-note` paragraphs from templates/layout.html.twig and
 * module/Application/view/layout/layout.phtml, this class, its two callers, its tests,
 * and rule 8 in tools/port-baseline.php. See docs/strangler.md.
 */
final class ServingNote
{
    /** Rendered by templates/ — only ever reachable through App\Kernel. */
    public const RENDERER_TWIG = 'Twig template';

    /** Rendered by a laminas view script, whichever front controller got there. */
    public const RENDERER_PHTML = '.phtml view script';

    /** The class attribute both layouts wear, so tests and tools have one hook. */
    public const CSS_CLASS = 'serving-note';

    /**
     * @param string                           $renderer one of the RENDERER_* constants
     * @param string|null                      $route    the matched route's name, as the router knows it
     * @param string|null                      $handler  controller (and action) that produced the body
     * @param array<string, mixed>|object|null $cookies  $_COOKIE, or a laminas/Symfony cookie bag
     */
    public static function describe(
        string $renderer,
        ?string $route = null,
        ?string $handler = null,
        mixed $cookies = null
    ): string {
        //$cookies is accepted and ignored since the SYMFONY_KERNEL canary was retired
        //(2026-09-08): there is one front controller now, so the note no longer reports
        //which one served the page or whether a cookie overrode it. It kept the parameter
        //so the two callers (the Twig function and the laminas view helper) need no edit.
        $how = 'Served by the Symfony kernel → ' . $renderer;
        if (self::RENDERER_PHTML === $renderer) {
            //still worth stating while LegacyBridge exists: a .phtml reaching a visitor
            //came through the bridge, not a ported Twig template
            $how .= ' via LegacyBridge';
        }

        $parts = [$how];
        if (null !== $route && '' !== $route) {
            //the raw name, `.locale` suffix and all: that suffix is how you see whether
            //the locale-prefixed twin matched, which is worth knowing when a page looks
            //subtly wrong
            $parts[] = 'route ' . $route;
        }
        if (null !== $handler && '' !== $handler) {
            $parts[] = $handler;
        }

        return implode(' · ', $parts);
    }
}

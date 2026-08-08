<?php

declare(strict_types=1);

namespace App\Http;

use App\Laminas\RouteUrl;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The redirect an HTML route owes its own *unprefixed* form.
 *
 * Every ported path is declared twice — `/blog` and `/{_locale}/blog` — because
 * every caller uses the prefixed form and Symfony, unlike laminas, has no
 * SlmLocale\Strategy\UriPathStrategy to strip the segment before routing. But
 * SlmLocale does not *serve* the unprefixed form either: it answers it with a 302 to
 * the negotiated language, so the page exists at one URL rather than two. A ported
 * controller has to reproduce that, and the absence of the `_locale` request
 * attribute is how it knows which form it is answering.
 *
 * ## Why this is a helper and not a listener
 *
 * A listener would be better and is still the right end state — docs/strangler.md
 * says so, and says why it has not happened: the maintenance endpoints must *not*
 * redirect, so a listener needs a per-route declaration of its own, which is a design
 * change rather than a refactor. Until then the check lives in the controllers.
 *
 * What this class changes is only how many copies of it there are. Three controllers
 * had written it out by hand when this batch began; the batch adds eight more routes,
 * and eleven hand-written copies of a rule about *when a page is allowed to exist at
 * two URLs* is how one of them quietly stops doing it. The three originals are left
 * as they are — rewriting working, production-facing code to adopt a helper is not
 * what a porting batch should spend its risk budget on — so this is used by the new
 * controllers only. Consolidating the other three belongs with the move to a
 * listener.
 */
final class LocalePrefix
{
    /**
     * A 302 to the prefixed form when the request has no locale, or null when it does
     * and the controller should carry on.
     *
     * The route named here is the *laminas* route the ported one shadows, because
     * RouteUrl assembles from the laminas router — which is also what makes the target
     * carry the prefix at all: App\Http\LocaleListener has already set
     * \Locale::setDefault() from the negotiation, and RouteUrl reads it.
     *
     * @param array<string, mixed> $params route parameters the target needs, e.g. the
     *                                     dictionary's `inLanguage`. Without them a
     *                                     parameterised route assembles to the wrong
     *                                     URL, or throws.
     */
    public static function redirect(
        Request $request,
        RouteUrl $urls,
        string $route,
        array $params = []
    ): ?RedirectResponse {
        if (null !== $request->attributes->get('_locale')) {
            return null;
        }

        return new RedirectResponse($urls->path($route, $params), Response::HTTP_FOUND);
    }
}

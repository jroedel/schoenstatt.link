<?php

declare(strict_types=1);

namespace App\Http;

use App\Laminas\RouteUrl;
use Closure;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

use function is_string;

/**
 * Answers the *unprefixed* form of a ported path with the 302 to the prefixed one that
 * SlmLocale issues under laminas, for every route that declares
 * {@see LocalePrefix::redirectsToPrefixed()}.
 *
 * ## Why this had to stop being controller code
 *
 * Forty-two controllers opened with four lines of it. That was already a rule about
 * *when a page is allowed to exist at two URLs* copied forty-two times, but the reason
 * it moved is not the duplication — it is the ordering, and the ordering was wrong in a
 * way no controller could fix.
 *
 * Since the authorization bridge landed the two front controllers disagreed about the
 * unprefixed form of a *restricted* path. Under laminas, SlmLocale redirects
 * `/admin` → `/en/admin` first and `BjyAuthorize\Guard\Route` denies on the second hop,
 * so a visitor sent to sign in comes back with `?redirect=/en/admin`. Under Symfony the
 * guard ran on `kernel.request` and the redirect lived in the controller *behind* it,
 * so the denial happened on the unprefixed hop and the return trip read
 * `?redirect=/admin`. Nobody's access differed and every real caller uses the prefixed
 * form, so this was tidiness — until the return trip started being carried in a login
 * link rather than a session, at which point the value in it is what the visitor
 * actually lands on.
 *
 * A listener above the check is the only place the order can be fixed, because a
 * controller by definition runs after it.
 *
 * ## Priority
 *
 * Between `App\Http\LocaleListener`'s 0 and `App\Http\AuthorizationListener`'s -16, and
 * both bounds are load-bearing:
 *
 * - **Below LocaleListener**, because the redirect target has no locale of its own to
 *   go on. `RouteUrl` assembles through the laminas router, which reads
 *   `\Locale::setDefault()` — and LocaleListener is what sets it from the negotiation.
 *   Above it, every unprefixed path would redirect to `en_US_POSIX`.
 * - **Above AuthorizationListener**, which is the whole point: see above.
 *
 * Stated as an explicit constant rather than left to registration order, so that adding
 * a listener cannot silently reorder the three.
 */
final class LocalePrefixListener
{
    /** Fires after LocaleListener (0) and before AuthorizationListener (-16). */
    public const PRIORITY = -8;

    /**
     * @param Closure(): RouteUrl $urls resolved when the listener fires rather than when
     *        it is registered, for the reason AuthorizationListener gives: RouteUrl
     *        memoizes the request's base URL, and at registration time there is no
     *        request.
     */
    public function __construct(private readonly Closure $urls)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        //a sub-request renders inside a response that has already been located; it has
        //no URL of its own to send anybody to
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        //the prefixed twin, which is the overwhelmingly common case: nothing to do
        if (null !== $request->attributes->get('_locale')) {
            return;
        }

        $declaration = $request->attributes->get(LocalePrefix::ATTRIBUTE);
        //A route that declares nothing serves itself. Unlike RouteAccess this is not an
        //error, and the asymmetry is deliberate: an undeclared *authorization* admits
        //everyone, which is a hole, while an undeclared prefix policy answers a page at
        //two URLs, which is what laminas did for years before the strangler and costs a
        //canonical link rather than a permission. `legacy` reaches here on every bridged
        //request and has no declaration by construction — see App\Http\SymfonyRoute.
        if (! $declaration instanceof LocalePrefix || ! $declaration->redirects()) {
            return;
        }

        $route = SymfonyRoute::routeName($request);
        if ('' === $route) {
            return;
        }

        $params = [];
        foreach ($declaration->params as $name) {
            $value = $request->attributes->get($name);
            //an optional placeholder the request did not match is left out rather than
            //assembled as an empty segment, which is what the call sites this replaced
            //did with `+ (is_string($slug) ? … : [])`
            if (is_string($value) && '' !== $value) {
                $params[$name] = $value;
            }
        }

        $options = [];
        $query   = $request->query->all();
        //SlmLocale redirects the URI, query included. Assembling from the route name
        //alone dropped it, which sent `/texts?search=Bund` to `/en/texts` with the
        //search silently gone — latent for two batches, because it takes a route whose
        //input *is* the query string for the loss to change what the page shows.
        if ([] !== $query) {
            $options['query'] = $query;
        }

        $event->setResponse(new RedirectResponse(
            ($this->urls)()->path($route, $params, $options),
            Response::HTTP_FOUND
        ));
    }
}

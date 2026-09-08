<?php

declare(strict_types=1);

namespace App\Twig;

use App\Http\CspNonce;
use App\Http\SymfonyRoute;
use App\Locale\Locales;
use App\View\SiteChrome;
use Locale;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function is_string;
use function json_encode;

use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_UNESCAPED_UNICODE;

/**
 * What templates\layout.html.twig needs to know about the current request.
 *
 * Every one of these is a function rather than a Twig global for the same reason:
 * globals are evaluated when the Environment is built, so a page that renders no
 * navbar would still pay for the ACL checks, the session read and the module load
 * behind them. As functions they cost nothing until a template asks.
 *
 * `current_route()` is the piece worth knowing about, because it is why no
 * controller has to pass the layout its route name. A ported Symfony route is named
 * after the laminas route it shadows (`shrines`), and its locale-prefixed twin adds
 * `.locale` (`shrines.locale`); App\Http\SymfonyRoute strips the suffix, so the
 * layout can compare against the names in the `navigation` config directly. Keep
 * naming ported routes that way and the chrome keeps working for free.
 */
final class ChromeExtension extends AbstractExtension
{
    public function __construct(
        private readonly SiteChrome $chrome,
        private readonly RequestStack $requests,
        private readonly CspNonce $nonce
    ) {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('current_route', $this->currentRoute(...)),
            new TwigFunction('current_path', $this->currentPath(...)),
            new TwigFunction('current_locale', $this->currentLocale(...)),
            new TwigFunction('current_language', $this->currentLanguage(...)),
            new TwigFunction('server_url', $this->serverUrl(...)),
            new TwigFunction('csp_nonce', $this->cspNonce(...)),
            new TwigFunction('navigation_items', $this->navigationItems(...)),
            new TwigFunction('language_options', $this->languageOptions(...)),
            new TwigFunction('canonical_links', $this->canonicalLinks(...)),
            new TwigFunction('search_box', $this->searchBox(...)),
            new TwigFunction('display_name', $this->displayName(...)),
            new TwigFunction('json_ld', $this->jsonLd(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * A JSON-LD payload, encoded so it cannot break out of the <script> element it
     * is written into — which is why it is a function and not `|json_encode|raw`:
     * plain json_encode leaves `</script>` intact, and a shrine name is user-edited
     * data. Used by both the layout's site schema and the pages' own blocks.
     */
    public function jsonLd(mixed $data): string
    {
        return (string) json_encode(
            $data,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );
    }

    public function currentRoute(): string
    {
        $request = $this->request();

        return null === $request ? '' : SymfonyRoute::routeName($request);
    }

    /**
     * The requested path with no query string, which is what a breadcrumb href is
     * compared against to decide whether the crumb is the current page.
     *
     * That comparison is how the layout reproduces Laminas\Navigation\Page\Mvc::
     * isActive(), which the breadcrumbs partial calls non-recursively: a crumb is
     * active when it *is* the current page, not when it is an ancestor of it.
     * Measured against the laminas rendering of /es/developers, where the "Home"
     * crumb carries no class at all — so "every ancestor is active", which this
     * layout assumed until 2026-08-07, was simply wrong. Comparing hrefs also gets
     * the shrine index right, where two crumbs legitimately share the current route
     * and laminas marks both.
     */
    public function currentPath(): string
    {
        $request = $this->request();

        return null === $request
            ? '/' . Locales::aliasFor(Locale::getDefault())
            : $request->getBaseUrl() . $request->getPathInfo();
    }

    public function currentLocale(): string
    {
        return Locale::getDefault();
    }

    /** The two-letter form, for the <html lang> attribute. */
    public function currentLanguage(): string
    {
        return (string) Locale::getPrimaryLanguage(Locale::getDefault());
    }

    /** Scheme and host with no trailing slash, the way laminas' `serverUrl` helper gives it. */
    public function serverUrl(): string
    {
        $request = $this->request();

        return null === $request ? '' : $request->getSchemeAndHttpHost();
    }

    public function cspNonce(): string
    {
        return $this->nonce->value();
    }

    /** @return list<array{label: string, href: string, active: bool}> */
    public function navigationItems(): array
    {
        return $this->chrome->navigationItems($this->navigationRoute());
    }

    /**
     * The route the navbar should match against, which is the page's own route unless
     * it declares otherwise.
     *
     * A page that laminas reaches through a database-derived navigation branch — a
     * dictionary under Literature, say — has to name the ancestor it wants
     * lit, because App\View\SiteChrome can only see the static config. Only the
     * *navbar* uses this: the search box and the breadcrumbs keep asking about the real
     * route, which is what they compare in laminas too.
     */
    private function navigationRoute(): string
    {
        $request = $this->request();
        if (null === $request) {
            return '';
        }
        $declared = $request->attributes->get(SiteChrome::NAV_ROUTE);

        return is_string($declared) && '' !== $declared ? $declared : $this->currentRoute();
    }

    /** @return list<array{locale: string, label: string, flag: string, href: string, current: bool}> */
    public function languageOptions(): array
    {
        return $this->chrome->languageOptions($this->path());
    }

    /**
     * @param array<string, string>|null $preferredPaths language code => path, which a
     *        record page passes so the canonical names the record's preferred URL rather
     *        than whichever decorative slug was requested. See App\View\PreferredUrls.
     * @return array{canonical: string, alternates: array<string, string>, x_default: string}
     */
    public function canonicalLinks(?array $preferredPaths = null): array
    {
        return $this->chrome->canonicalLinks($this->serverUrl(), $this->path(), $preferredPaths);
    }

    /** @return array{action: string, placeholder: string}|null */
    public function searchBox(): ?array
    {
        return $this->chrome->searchBox($this->currentRoute(), $this->libraryRouteParams());
    }

    /**
     * The three route parameters that can name the page's library, read straight off the
     * request attributes.
     *
     * Only these three, rather than handing the chrome every attribute: the rest of a
     * Symfony request's attribute bag is framework bookkeeping (`_route`,
     * `_controller`, the text domain, SiteChrome::NAV_ROUTE), and a chrome that received
     * all of it would be free to start depending on any of it.
     *
     * @return array<string, mixed>
     */
    private function libraryRouteParams(): array
    {
        $request = $this->request();
        if (null === $request) {
            return [];
        }

        $params = [];
        foreach (['library_id', 'book_id', 'import_id'] as $name) {
            $value = $request->attributes->get($name);
            if (null !== $value) {
                $params[$name] = $value;
            }
        }

        return $params;
    }

    public function displayName(): string|false
    {
        return $this->chrome->displayName();
    }

    /**
     * The requested path *with* its query string, which is what the locale links are
     * rewritten from — SlmLocale's helper keeps the query too, minus `lang`. Falls
     * back to the current locale's root so a template rendered outside a request — a
     * test, a future CLI renderer — still produces valid hrefs rather than an empty
     * one.
     */
    private function path(): string
    {
        $request = $this->request();

        return null === $request ? '/' . Locales::aliasFor(Locale::getDefault()) : $request->getRequestUri();
    }

    private function request(): ?Request
    {
        return $this->requests->getMainRequest();
    }
}

<?php

declare(strict_types=1);

namespace App\View;

use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Laminas\ViewHelpers;
use App\Locale\Locales;
use Laminas\Math\Rand;
use Locale;
use Throwable;

use function in_array;
use function is_array;
use function is_string;
use function str_contains;

/**
 * The parts of module/Application/view/layout/layout.phtml that are decisions
 * rather than markup: which navigation items a visitor may see, which flag the
 * language chooser shows, whether the navbar carries a search box.
 *
 * Kept out of the Twig extension so the rules are plain PHP under PHPStan level 8
 * and can be read without knowing Twig, and out of the controllers because the
 * layout needs all of it on *every* page — a per-controller obligation to hand the
 * chrome its data is one every later port would eventually forget.
 *
 * The navigation is the interesting piece. Laminas builds its navbar from the
 * `Navigation` service, which cannot be resolved here at all: its factory
 * (Laminas\Navigation\Service\AbstractNavigationFactory::preparePages) asks
 * `$application->getMvcEvent()->getRouteMatch()`, so it dies exactly the way the
 * `url` helper does. Two things follow, and both are why reading the raw
 * `navigation` config here loses nothing that matters:
 *
 *  - The navbar renders `setMinDepth(0)->setMaxDepth(0)`, i.e. **top-level pages
 *    only**. Everything Application\Module::onBootstrap() spends a request budget
 *    priming — the dictionary, publication, association, library and music
 *    branches it builds from the database and caches in APCu — hangs below depth 0
 *    and never reaches the navbar. It feeds the breadcrumbs and the deeper menus
 *    on pages that render them, not this.
 *  - So the seven items in `config/autoload/global.php` under `navigation.default`
 *    are the whole navbar, ACL-filtered. Which the laminas output confirms: five
 *    render for a guest, because `Movement` and `Admin` declare resources
 *    (`route/schoenstatt`, `route/admin`) that a guest is denied.
 *
 * A top-level item lighting up because a *database-derived* descendant is the current
 * page is **declared, not derived**. Matching descends the static `navigation` config
 * only, and Application\Module::onBootstrap() hangs a page per dictionary, per library
 * and per publication underneath it — branches this class
 * cannot see, because the Navigation service they are attached to cannot be built
 * without an MvcEvent.
 *
 * So a ported route whose laminas twin lights up an ancestor says which one, as a
 * `NAV_ROUTE` route default: /dictionary/{lang} and
 * /literature/150-preguntas-sobre-schoenstatt both declare `publications`. Declared
 * rather than inferred from the route name because the route names do **not** encode
 * it — `dictionary/inLanguage` hangs under `publications`, and no prefix rule gets that
 * right. Measured against the laminas rendering of each page, not guessed.
 */
final class SiteChrome
{
    /**
     * Route default naming the navigation item a page should mark active, when that is
     * not the page's own route. See the class docblock.
     */
    public const NAV_ROUTE = '_nav_route';

    /**
     * Route names the layout refuses to put a search box on, verbatim from the
     * long conditional in layout.phtml.
     *
     * @var list<string>
     */
    private const NO_SEARCH_ROUTES = [
        'assignments/search',
        'assignments',
        'publications/search',
        'publications',
        'schoenstatt',
        'welcome',
        'libraries/library',
    ];

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly ViewHelpers $helpers,
        private readonly RouteUrl $urls
    ) {
    }

    /**
     * @return list<array{label: string, href: string, active: bool}>
     */
    public function navigationItems(string $activeRoute): array
    {
        $items = [];
        foreach ($this->navigationConfig() as $page) {
            if (! $this->isPageVisible($page)) {
                continue;
            }
            $route = $page['route'] ?? null;
            if (! is_string($route)) {
                continue;
            }
            $items[] = [
                'label'  => is_string($page['label'] ?? null) ? $page['label'] : '',
                'href'   => $this->urls->path($route),
                'active' => $this->matchesRoute($page, $activeRoute),
            ];
        }

        return $items;
    }

    /**
     * The language chooser's entries, current locale first in the dropdown order
     * the laminas layout uses (the configured order, not "current first").
     *
     * The flag for most languages is drawn at random on every request — `us` or
     * `gb` for English, one of four for Spanish. That is deliberate whimsy in the
     * original and is kept, at the cost of making byte-for-byte output comparison
     * between the two renderings meaningless.
     *
     * @return list<array{locale: string, label: string, flag: string, href: string, current: bool}>
     */
    public function languageOptions(string $currentPath): array
    {
        $current = Locale::getDefault();
        $options = [];
        foreach (self::flags() as $locale => $flag) {
            $options[] = [
                'locale'  => $locale,
                'label'   => (string) Locale::getDisplayLanguage($locale),
                'flag'    => $flag,
                'href'    => $this->urls->localized($currentPath, $locale),
                'current' => $locale === $current,
            ];
        }

        return $options;
    }

    /**
     * The canonical URL for this page and the hreflang set it belongs to.
     *
     * ## This used to name the English page as canonical for all five languages
     *
     * Every locale's rendering declared `<link rel="canonical">` pointing at `/en/…`,
     * and the hreflang set omitted the page's own language. Both are changed here,
     * 2026-08-13, because between them they told Google to index only the English copy
     * of all 7,062 records and to ignore the language cluster while doing it:
     *
     * 1. **A canonical pointing at another language is an instruction to drop this
     *    page.** Google treats the canonical as "index that one instead", so declaring
     *    `/en/SL100319A` on the German page asks for the German page not to be indexed.
     *    Whatever the intent, the effect was four fifths of the site withdrawn from the
     *    index — while the sitemap went on offering all five, which is the contradiction
     *    Search Console reports as "Alternate page with proper canonical tag".
     * 2. **An hreflang set that omits itself is not a valid cluster.** Google requires
     *    each version to list *all* versions including itself; a page missing its own
     *    entry makes the annotations non-reciprocal and they are discarded. So the one
     *    mechanism that would have kept the five copies from competing was inert.
     *
     * So: the canonical is this page's own language, the alternate set is complete, and
     * `x_default` names the English URL for a visitor whose language we do not publish.
     *
     * ## `$preferredPaths` is how a record page avoids canonicalising a decorative slug
     *
     * Without it the five URLs are built by swapping the locale prefix of the current
     * path, which is right for a static page and wrong for a record: the slug does not
     * select anything, several URL forms answer 200, and an association's slug differs
     * per locale. A page that knows its record passes `App\View\PreferredUrls::forRecord()`
     * output here and the canonical becomes the *preferred* form rather than the
     * requested one — which is exactly what a canonical is for.
     *
     * @param array<string, string>|null $preferredPaths language code => path
     * @return array{canonical: string, alternates: array<string, string>, x_default: string}
     */
    public function canonicalLinks(string $origin, string $currentPath, ?array $preferredPaths = null): array
    {
        $current    = Locale::getPrimaryLanguage(Locale::getDefault());
        $alternates = [];
        foreach (Locales::ALIASES as $alias => $locale) {
            $path               = $preferredPaths[$alias] ?? $this->urls->localized($currentPath, $locale);
            $alternates[$alias] = $origin . $path;
        }

        $english = Locales::aliasFor('en_US');

        return [
            //this page's own language — and its own entry in the set it publishes, so the
            //canonical and the hreflang annotation can never disagree
            'canonical'  => $alternates[$current] ?? $alternates[$english],
            'alternates' => $alternates,
            //"none of the above": the copy to serve a searcher whose language is not one
            //of ours. English is the site's source language.
            'x_default'  => $alternates[$english],
        ];
    }

    /**
     * The navbar search form, or null when this page gets none.
     *
     * Only reachable for a signed-in visitor, because that is the branch of the
     * layout it lives in. The placeholders really are untranslated in the original.
     *
     * The library-scoped variant is missing on purpose: it needs the `libraryInfo`
     * view helper, which needs an MvcEvent, and it only ever applies to library,
     * book, checkout and import routes — none of them ported, and each will have to
     * bring that helper's replacement along when it moves.
     *
     * @return array{action: string, placeholder: string}|null
     */
    public function searchBox(string $activeRoute): ?array
    {
        if ('' === $activeRoute || in_array($activeRoute, self::NO_SEARCH_ROUTES, true)) {
            return null;
        }
        if (! str_contains($activeRoute, 'publication') && $this->isAllowed('route/assignments/search')) {
            return ['action' => $this->urls->path('assignments/search'), 'placeholder' => 'Search contacts'];
        }
        if ($this->isAllowed('route/publications/search')) {
            return ['action' => $this->urls->path('publications/search'), 'placeholder' => 'Search publications'];
        }

        return null;
    }

    /**
     * The signed-in visitor's display name, or false when anonymous — the layout
     * branches on the truthiness of exactly this and never prints it.
     */
    public function displayName(): string|false
    {
        $name = $this->helpers->displayName()->__invoke();

        return is_string($name) && '' !== $name ? $name : false;
    }

    /**
     * The flag each locale is shown with. `Rand::getInteger()` on every request is
     * the original's behaviour, not an accident.
     *
     * @return array<string, string>
     */
    private static function flags(): array
    {
        return [
            'en_US' => ['us', 'gb'][Rand::getInteger(0, 1)],
            'es_ES' => ['ar', 'es', 'cl', 'mx'][Rand::getInteger(0, 3)],
            'de_DE' => ['de', 'ch'][Rand::getInteger(0, 1)],
            'pt_BR' => ['pt', 'br'][Rand::getInteger(0, 1)],
            'it_IT' => 'it',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function navigationConfig(): array
    {
        $navigation = $this->laminas->config()['navigation'] ?? null;
        if (! is_array($navigation) || ! is_array($navigation['default'] ?? null)) {
            return [];
        }

        $pages = [];
        foreach ($navigation['default'] as $page) {
            if (is_array($page)) {
                /** @var array<string, mixed> $page */
                $pages[] = $page;
            }
        }

        return $pages;
    }

    /**
     * Laminas\View\Helper\Navigation\AbstractHelper::acceptAcl(): a page with no
     * resource is always shown, and a page naming a resource the ACL has never
     * heard of is hidden rather than allowed. BjyAuthorize's isAllowed throws on an
     * unknown resource where the navigation helper asks hasResource() first, so the
     * catch here is that hasResource() check.
     *
     * @param array<string, mixed> $page
     */
    private function isPageVisible(array $page): bool
    {
        $resource = $page['resource'] ?? null;
        if (! is_string($resource)) {
            return true;
        }

        try {
            return $this->isAllowed($resource);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $page
     */
    private function matchesRoute(array $page, string $activeRoute): bool
    {
        if ('' === $activeRoute) {
            return false;
        }
        if (($page['route'] ?? null) === $activeRoute) {
            return true;
        }
        if (! is_array($page['pages'] ?? null)) {
            return false;
        }
        foreach ($page['pages'] as $child) {
            if (is_array($child) && $this->matchesRoute($child, $activeRoute)) {
                return true;
            }
        }

        return false;
    }

    private function isAllowed(string $resource): bool
    {
        return (bool) $this->helpers->isAllowed()->__invoke($resource);
    }
}

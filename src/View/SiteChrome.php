<?php

declare(strict_types=1);

namespace App\View;

use App\Books\CurrentLibrary;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use JUser\Host\IdentityInterface;
use JUser\Model\DisplayName;
use App\Laminas\ViewHelpers;
use App\Locale\Locales;
use Closure;
use Locale;
use Throwable;

use function in_array;
use function is_array;
use function is_string;
use function random_int;
use function sprintf;
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

    /**
     * @param Closure(string, ?string): string $translate the page-aware translator
     *        App\Twig\LaminasExtension exposes to templates. Taken as a callable rather
     *        than by resolving MvcTranslator here so the chrome and the templates around
     *        it can never disagree about what a phrase says.
     */
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly ViewHelpers $helpers,
        private readonly RouteUrl $urls,
        private readonly CurrentLibrary $library,
        private readonly Closure $translate
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
     * layout it lives in. Two of the three placeholders really are untranslated in the
     * original; the library one is translated, in the `Application` domain, and asking
     * for that exact string in that exact domain is what keeps the port from filing a
     * second phrase row for a string laminas already has.
     *
     * ## The library branch, added 2026-08-18
     *
     * A page belonging to a library searches *that library* rather than the site's
     * contacts. It was left out when this class was written, on the reasoning that no
     * library route was ported yet — but `books/book/edit`, `books/create` and
     * `libraries/library/edit` all match the prefixes, so three pages have been showing
     * a contacts search where laminas shows a library one. Batch 11b would have added
     * about thirty more, which is why it is closed first rather than accepted again.
     *
     * The branch reproduces layout.phtml exactly, including the part that reads as
     * redundant: **both** `route/libraries/library` (may this visitor see library pages
     * at all) and `library_<id>`+`show` (may they see *this* one) must pass. They are
     * genuinely different questions here — the per-library rule is what
     * Books\Model\LibraryTable::getRules() emits from each row's `viewRole`, so a
     * visitor can hold the route guard and still be refused one library. Failing either
     * falls through to the contacts box rather than dropping the search entirely, which
     * is also the original's behaviour.
     *
     * The library's name is returned **unescaped**, where layout.phtml escapes it before
     * sprintf. Not a divergence: the layout echoes its placeholder raw and Twig escapes
     * the value it is handed, so the same bytes reach the attribute either way.
     *
     * @param array<string, mixed> $routeParams the request's route parameters, which the
     *        library branch needs — see App\Books\CurrentLibrary
     * @return array{action: string, placeholder: string}|null
     */
    public function searchBox(string $activeRoute, array $routeParams = []): ?array
    {
        if ('' === $activeRoute || in_array($activeRoute, self::NO_SEARCH_ROUTES, true)) {
            return null;
        }

        $library = $this->library->forRoute($activeRoute, $routeParams);
        if (
            null !== $library
            && is_string($library['resourceId'] ?? null)
            && $this->isAllowed('route/libraries/library')
            && $this->isAllowed($library['resourceId'], 'show')
        ) {
            return [
                'action'      => $this->urls->path('libraries/library', ['library_id' => $library['libraryId']]),
                'placeholder' => sprintf(
                    ($this->translate)('Search %s', 'Application'),
                    is_string($library['name'] ?? null) ? $library['name'] : ''
                ),
            ];
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
     *
     * Asked of JUser directly. Until 2026-09 this went through the `zfcUserDisplayName`
     * view helper, which existed to give a laminas layout the same three-step fallback
     * {@see DisplayName} now holds: chosen name, then username, then the local part of the
     * email address. The last step is why this is not a property read — showing a whole
     * address where a name belongs publishes it, on every page.
     */
    public function displayName(): string|false
    {
        $name = DisplayName::of($this->identity()?->current());

        return is_string($name) && '' !== $name ? $name : false;
    }

    /** Null when the host has registered no identity, which is a console process. */
    private function identity(): ?IdentityInterface
    {
        if (! $this->laminas->has(IdentityInterface::class)) {
            return null;
        }
        $identity = $this->laminas->get(IdentityInterface::class);

        return $identity instanceof IdentityInterface ? $identity : null;
    }

    /**
     * The flag each locale is shown with. Picking one at random on every request is the
     * original's behaviour, not an accident. (`Laminas\Math\Rand` until 2026-09, whose
     * getInteger() called random_int().)
     *
     * @return array<string, string>
     */
    private static function flags(): array
    {
        return [
            'en_US' => ['us', 'gb'][random_int(0, 1)],
            'es_ES' => ['ar', 'es', 'cl', 'mx'][random_int(0, 3)],
            'de_DE' => ['de', 'ch'][random_int(0, 1)],
            'pt_BR' => ['pt', 'br'][random_int(0, 1)],
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

    private function isAllowed(string $resource, ?string $privilege = null): bool
    {
        return (bool) $this->helpers->isAllowed()->__invoke($resource, $privilege);
    }
}

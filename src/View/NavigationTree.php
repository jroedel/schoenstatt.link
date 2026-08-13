<?php

declare(strict_types=1);

namespace App\View;

use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Application\Navigation\PageBuilder;
use Locale;

use function is_array;
use function is_string;

/**
 * The whole navigation tree, on the Symfony side, without `Laminas\Navigation`.
 *
 * The Navigation *service* cannot be built here and that is not a limitation anyone can
 * work around: `AbstractNavigationFactory::preparePages()` calls
 * `$container->get('Application')->getMvcEvent()->getRouteMatch()`, and outside a
 * bootstrapped MVC application `getMvcEvent()` is null. Measured, rather than assumed —
 * asking a ServiceBridge for it answers
 * `Error: Call to a member function getRouteMatch() on null`.
 *
 * Nothing is lost by not having it. A navigation container is three things, and this
 * class has all three by other means:
 *
 *  - the static tree, which is `navigation.default` in `config/autoload/global.php` and
 *    readable straight off the merged config;
 *  - five database-derived branches, which are `Application\Navigation\PageBuilder`'s —
 *    the *same* builder `Application\Module::onBootstrap()` uses, deliberately, so the
 *    two front controllers cannot disagree about what pages exist;
 *  - a URL per page, which is `App\Laminas\RouteUrl`.
 *
 * `Laminas\Navigation\Page\Mvc` would additionally give `isActive()`, which nothing here
 * wants: the Twig layout decides which crumb and which navbar item is current by
 * comparing hrefs, and has since the first port.
 *
 * ## What this costs, and therefore who may call it
 *
 * Cold, the branches are 0.48 s and 51 MiB of table rows; warm, `publication-pages` alone
 * is 2.24 MB in APCu — the largest single entry in the persistent cache — and
 * unserializing that much data costs on the order of 8-10 ms. So **this is not
 * per-request chrome.** `/sitemap.xml` walks the entire tree and is the one thing that
 * genuinely needs it. The navbar reads a small derived projection instead
 * (`App\View\NavigationAncestors`), and breadcrumbs on ported pages are stated by the
 * controller that already knows them. Anything that renders on every page and reaches for
 * this class is a mistake worth catching in review.
 *
 * ## Attachment points are laminas' own
 *
 * `onBootstrap()` hangs each branch off a page it finds with `findOneBy()`, and the seven
 * lookups are reproduced verbatim below — by *label* where it uses a label and by *route*
 * where it uses a route, because those are not interchangeable here. `World` and `Shrines`
 * both carry route `shrines`, and `Dictionaries` carries route `publications` exactly like
 * the `Literature` page it hangs under, so matching on the other property would attach two
 * branches to the wrong node and the mistake would look like a sitemap with plausible
 * pages in it.
 */
final class NavigationTree
{
    /**
     * Where each branch attaches, in `onBootstrap()`'s order: [property, value, branch
     * path]. The branch path is a key of PageBuilder::PAGES_CACHE_KEYS, optionally with a
     * sub-key for the association branch's four lists.
     *
     * @var list<array{0: string, 1: string, 2: string, 3?: string}>
     */
    private const ATTACHMENTS = [
        ['label', 'Dictionaries', 'dictionary-pages'],
        ['route', 'publications', 'publication-pages'],
        ['route', 'schoenstatt', 'association-pages', 'movement'],
        ['label', 'Shrines', 'association-pages', 'shrinesByRegion'],
        ['label', 'World', 'association-pages', 'shrinesWorld'],
        ['route', 'libraries', 'library-pages'],
        ['route', 'music', 'music-pages'],
    ];

    /** @var list<array<string, mixed>>|null */
    private ?array $nodes = null;

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly RouteUrl $urls
    ) {
    }

    /**
     * The tree, as nested nodes.
     *
     * Each node is `['label' => string, 'href' => string, 'labelIsData' => bool,
     * 'children' => list<node>]`. `labelIsData` is PageBuilder's LABEL_IS_DATA, carried
     * through unchanged: a label that is a record's own title must never be translated,
     * because a translator miss is how a phrase is filed and there is one of these per
     * publication.
     *
     * Memoized per instance. A single request that asks twice pays once; nothing here is
     * shared between requests beyond what APCu already holds.
     *
     * @return list<array<string, mixed>>
     */
    public function nodes(): array
    {
        if (null !== $this->nodes) {
            return $this->nodes;
        }

        $locale   = Locale::getDefault();
        $branches = (new PageBuilder(fn (string $id): mixed => $this->service($id)))->branches($locale);

        /** @var array<string, mixed> $config */
        $config = $this->laminas->config();
        /** @var array<int, array<string, mixed>> $pages */
        $pages = $config['navigation']['default'] ?? [];

        //One attachment fires once, like findOneBy() returning one page. Passed by
        //reference down the walk so a branch attached at depth two is not attached again
        //to a later sibling that happens to match.
        $pending = self::ATTACHMENTS;

        return $this->nodes = $this->convert($pages, $branches, $pending);
    }

    /**
     * Every page in the tree, flattened depth-first with each parent before its children —
     * `RecursiveIteratorIterator::SELF_FIRST`, which is how the sitemap walks it.
     *
     * @return list<array{label: string, href: string, labelIsData: bool}>
     */
    public function flattened(): array
    {
        return self::flatten($this->nodes());
    }

    /**
     * @param array<int|string, mixed> $pages
     * @param array<string, mixed> $branches
     * @param list<array{0: string, 1: string, 2: string, 3?: string}> $pending
     * @return list<array<string, mixed>>
     */
    private function convert(array $pages, array $branches, array &$pending): array
    {
        $nodes = [];
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            /** @var array<int|string, mixed> $children */
            $children = is_array($page['pages'] ?? null) ? $page['pages'] : [];
            $attached = $this->branchFor($page, $branches, $pending);
            if (null !== $attached) {
                //addPages() appends, so the configured children come first
                $children = [...$children, ...$attached];
            }

            $nodes[] = [
                'label'       => is_string($page['label'] ?? null) ? $page['label'] : '',
                'href'        => $this->href($page),
                'labelIsData' => true === ($page[PageBuilder::LABEL_IS_DATA] ?? false),
                'children'    => $this->convert($children, $branches, $pending),
            ];
        }

        return $nodes;
    }

    /**
     * The branch that attaches to this page, if one does and has not already.
     *
     * @param array<string, mixed> $page
     * @param array<string, mixed> $branches
     * @param list<array{0: string, 1: string, 2: string, 3?: string}> $pending
     * @return array<int|string, mixed>|null
     */
    private function branchFor(array $page, array $branches, array &$pending): ?array
    {
        foreach ($pending as $index => $attachment) {
            [$property, $value, $branchKey] = $attachment;
            if (($page[$property] ?? null) !== $value) {
                continue;
            }

            unset($pending[$index]);
            $branch = $branches[$branchKey] ?? null;
            if (isset($attachment[3])) {
                $branch = is_array($branch) ? ($branch[$attachment[3]] ?? null) : null;
            }

            return is_array($branch) ? $branch : null;
        }

        return null;
    }

    /**
     * A page's URL, assembled the way `Page\Mvc::getHref()` assembles it — the fragment
     * goes to the router as an *option*, not concatenated onto the result, because that
     * is what puts `#Africa` after the query string rather than before it.
     *
     * @param array<string, mixed> $page
     */
    private function href(array $page): string
    {
        $route = $page['route'] ?? null;
        if (! is_string($route) || '' === $route) {
            return '';
        }

        /** @var array<string, mixed> $params */
        $params  = is_array($page['params'] ?? null) ? $page['params'] : [];
        $options = [];
        if (is_string($page['fragment'] ?? null)) {
            $options['fragment'] = $page['fragment'];
        }

        return $this->urls->path($route, $params, $options);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array{label: string, href: string, labelIsData: bool}>
     */
    private static function flatten(array $nodes): array
    {
        $flat = [];
        foreach ($nodes as $node) {
            /** @var string $label */
            $label = $node['label'];
            /** @var string $href */
            $href = $node['href'];
            $flat[] = [
                'label'       => $label,
                'href'        => $href,
                'labelIsData' => true === $node['labelIsData'],
            ];
            /** @var list<array<string, mixed>> $children */
            $children = $node['children'];
            foreach (self::flatten($children) as $descendant) {
                $flat[] = $descendant;
            }
        }

        return $flat;
    }

    /** PageBuilder's resolver: a missing service is null rather than an exception. */
    private function service(string $id): mixed
    {
        return $this->laminas->has($id) ? $this->laminas->get($id) : null;
    }
}

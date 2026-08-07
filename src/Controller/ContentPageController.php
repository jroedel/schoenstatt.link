<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_array;
use function is_string;
use function sprintf;

/**
 * The site's static content pages: `/`, /developers, /acknowledgements, /privacy and
 * /shrines/submitting-photos.
 *
 * One controller for five routes, which is a departure from the one-class-per-route
 * shape of the ports before it and deliberate. On the laminas side these are five
 * actions that differ in nothing but which heredoc they echo —
 * `developersAction()`, `acknowledgementsAction()`, `privacyAction()` and
 * `submittingPhotosAction()` are each literally `return new ViewModel()`, and
 * `indexAction()` fetches five blog posts that index.phtml never reads. Reproducing
 * that as five identical Symfony controllers would port the duplication along with
 * the pages, which is the mistake docs/strangler.md records from the wayside-shrine
 * port: between two *ported* routes, share.
 *
 * What differs per page therefore lives in the route declaration, not in a subclass:
 *
 *     ContentPageController::TEMPLATE     which template under templates/content/
 *     ContentPageController::PAGE_TITLE   the <title> prefix, '' for none
 *     ContentPageController::BREADCRUMBS  [[label, route]], outermost first
 *
 * A breadcrumb names a **laminas route**, not a URL, and this assembles it through
 * App\Laminas\RouteUrl so the locale prefix is right and a link keeps working when
 * its target is still on the laminas side. That is also what lets the layout decide
 * which crumb is the current page by comparing hrefs.
 *
 * `indexAction()`'s unread blog-post query is not reproduced. It is five rows read
 * out of the text table and discarded before rendering, so dropping it changes no
 * byte of the response and takes one query off the site's busiest page. The parity
 * test asserts the rendered equality rather than trusting that reading.
 */
final class ContentPageController
{
    public const TEMPLATE    = '_content_template';
    public const PAGE_TITLE  = '_content_page_title';
    public const BREADCRUMBS = '_content_breadcrumbs';

    public function __construct(
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    public function __invoke(Request $request): Response
    {
        //no prefix means no locale was asked for, and SlmLocale answers that with a
        //redirect to the negotiated language rather than by serving the same body at
        //two URLs — App\Http\LocaleListener has already settled which language that
        //is. The route name is what the redirect is built from, so this works for all
        //five pages without any of them naming itself.
        if (null === $request->attributes->get('_locale')) {
            return new RedirectResponse(
                $this->urls->path($this->routeName($request)),
                Response::HTTP_FOUND
            );
        }

        return new Response($this->twig->render($this->template($request), [
            'page_title'  => $this->pageTitle($request),
            'breadcrumbs' => $this->breadcrumbs($request),
        ]));
    }

    /**
     * The laminas route this page shadows, recovered from the matched Symfony route
     * name the way App\Http\SymfonyRoute does it — the unprefixed twin is named after
     * the laminas route and the prefixed one adds `.locale`.
     *
     * Only ever called on the unprefixed twin, so there is no suffix to strip; asking
     * for `_route` rather than hardcoding is what keeps this controller page-agnostic.
     */
    private function routeName(Request $request): string
    {
        $route = $request->attributes->get('_route');
        if (! is_string($route) || '' === $route) {
            throw new LogicException('ContentPageController was reached with no matched route');
        }

        return $route;
    }

    private function template(Request $request): string
    {
        $template = $request->attributes->get(self::TEMPLATE);
        if (! is_string($template) || '' === $template) {
            throw new LogicException(sprintf(
                'Route "%s" reaches ContentPageController without a %s default',
                $this->routeName($request),
                self::TEMPLATE
            ));
        }

        return $template;
    }

    /** '' is a legitimate value and means "no prefix", which the layout renders as the site name alone. */
    private function pageTitle(Request $request): string
    {
        $title = $request->attributes->get(self::PAGE_TITLE);

        return is_string($title) ? $title : '';
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    private function breadcrumbs(Request $request): array
    {
        $declared = $request->attributes->get(self::BREADCRUMBS);
        if (! is_array($declared)) {
            return [];
        }

        $crumbs = [];
        foreach ($declared as $crumb) {
            if (! is_array($crumb) || ! isset($crumb['label'], $crumb['route'])) {
                throw new LogicException('A content-page breadcrumb needs both a label and a route');
            }
            /** @var string $label */
            $label = $crumb['label'];
            /** @var string $route */
            $route    = $crumb['route'];
            $crumbs[] = ['label' => $label, 'href' => $this->urls->path($route)];
        }

        return $crumbs;
    }
}

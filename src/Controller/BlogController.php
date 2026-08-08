<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Books\Model\EventTextTable;
use Schoenstatt\Filter\SchoenstattLinkIdentifier as SchoenstattLinkIdentifierFilter;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Markup;

use function is_array;
use function is_string;

/**
 * GET /blog and GET /blog/posts/{sw_id}[/{slug}] — the site's blog.
 *
 * Both are guarded `['guest', 'user']`, i.e. everyone. Two routes, one controller,
 * because they are one feature and share the front-matter partial and the id filter.
 *
 * ## The site-wide id
 *
 * `/blog/posts/SL402801T/…` names a row by its *site-wide* identifier, not by its
 * primary key. `Schoenstatt\Validator\SchoenstattLinkIdentifier` and its matching
 * filter — both plain classes, no MvcEvent, no view — do the check and the conversion,
 * exactly as `Books\Controller\BlogController::getEntityIdParam()` does. Reused rather
 * than reproduced: the identifier scheme is a site-wide contract and a second
 * implementation of it is a second thing to keep right.
 *
 * ## Four branches, all of them the original's
 *
 * 1. **No slug** — canonicalise to the slugged URL. The original assembles that
 *    redirect with `text_id`, which is not a parameter of the `blog/blog-post` route
 *    (`sw_id` is), so laminas throws rather than redirecting. See `showAction()` below:
 *    the ported route reproduces the *working* redirect instead, and that difference is
 *    deliberate and recorded, not accidental.
 * 2. **No such row** — the original flashes and redirects to the index.
 * 3. **Wrong kind** — a `text` row that is not a blog post is not reachable here;
 *    flash, and back to the index.
 * 4. **Otherwise** render, having registered a visit.
 *
 * `isActionAllowed('show')` is *not* reproduced and does not need to be: it returns
 * true immediately for any entity whose spec sets no `aclResourceIdField`, and
 * `blog-post` sets none. Checked against the spec rather than assumed — `library` does
 * set one, which is why /libraries filters its rows.
 */
final class BlogController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    /** GET /blog — every post, newest first, each excerpted to 1,000 display columns. */
    public function index(Request $request): Response
    {
        $redirect = LocalePrefix::redirect($request, $this->urls, 'blog');
        if (null !== $redirect) {
            return $redirect;
        }

        //SionTable::queryObjects() documents $query as PredicateInterface[], but its body
        //explicitly handles a plain field => value array and that is how every caller in
        //module/ uses it — Books\Controller\BlogController::indexAction() passes this exact
        //array. The docblock is wrong, not the call, and correcting it is a SionModel
        //change rather than one this batch should smuggle in. Hoisted out of the render
        //array so the ignore has a line of its own to sit above.
        //@phpstan-ignore argument.type
        $objects = $this->table()->queryObjects('blog-post', ['kind' => EventTextTable::TEXT_KIND_BLOG]);

        return new Response($this->twig->render('books/blog-index.html.twig', [
            //index.phtml calls no headTitle(); `blog` is a top-level navigation item and
            //so has a one-crumb trail
            'page_title'  => '',
            'breadcrumbs' => [['label' => 'Blog', 'href' => $this->urls->path('blog')]],
            'objects'     => $objects,
        ]));
    }

    /** GET /blog/posts/{sw_id}[/{slug}] — one post. */
    public function show(Request $request): Response
    {
        $identifier = $request->attributes->get('sw_id');
        if (! is_string($identifier) || ! (new SchoenstattLinkIdentifier('text'))->isValid($identifier)) {
            //the original raises here rather than 404ing, and the route constraint means
            //only a well-formed id reaches us at all; this is the belt to that braces
            return $this->toIndex($request);
        }

        //`slug` is omitted rather than passed empty when the request has none: the route
        //segment is optional, and assembling it with '' yields a trailing slash —
        //`/en/blog/posts/SL402801T/` where laminas redirects to `/en/blog/posts/SL402801T`.
        //Measured; a one-character difference in a Location header.
        $slug        = $request->attributes->get('slug');
        $localeParams = ['sw_id' => $identifier];
        if (is_string($slug) && '' !== $slug) {
            $localeParams['slug'] = $slug;
        }
        $redirect = LocalePrefix::redirect($request, $this->urls, 'blog/blog-post', $localeParams);
        if (null !== $redirect) {
            return $redirect;
        }

        $textId = (new SchoenstattLinkIdentifierFilter('text'))->filter($identifier);
        $table  = $this->table();
        //`existsEntity()` really does return a bool. PHPStan disagrees, and it is right
        //to: the method is annotated `@return boolean` in a file that has
        //`use Laminas\Filter\Boolean;` at the top, so the name resolves to *that class*
        //and the call looks like it returns an always-truthy object. A docblock defect in
        //SionModel that misleads any analysis of SionTable, not something wrong here.
        //@phpstan-ignore booleanNot.alwaysFalse
        if (! $table->existsEntity('blog-post', $textId)) {
            return $this->toIndex($request);
        }

        //existsEntity() has already said there is a row, so getObject() returns one; the
        //check that remains is the original's own — BlogController::showAction() bounces
        //a `text` row that is not a blog post, because /blog/posts/… and /texts/… name
        //the same table.
        $object = $table->getObject('blog-post', $textId, true);
        if (EventTextTable::TEXT_KIND_BLOG !== ($object['kind'] ?? null)) {
            return $this->toIndex($request);
        }

        //`slug` is optional in the route, and a post reached without it is served from a
        //second URL unless it is canonicalised. The original means to do this and cannot
        //— see the class docblock — so this is the one place the ported route does
        //something laminas does not, and it does the thing the original was reaching for.
        if (null === $request->attributes->get('slug')) {
            return new RedirectResponse(
                $this->urls->path('blog/blog-post', [
                    'sw_id' => $identifier,
                    'slug'  => (string) ($object['slug'] ?? ''),
                ]),
                Response::HTTP_FOUND
            );
        }

        //showAction()'s side effect, and the reason this is not a pure read: every view
        //of a post is a row in `visits`. The counts it feeds are not rendered on this
        //page — show.phtml prints none — but the row is still written, and a ported
        //route that stopped writing it would silently stop counting the blog.
        $table->registerVisit('blog-post', $object['textId']);

        //`$this->serverUrl() . $this->url(null, [], [], true)` in the original: the
        //absolute URL of the page being rendered. Rebuilt from the request rather than
        //re-assembled from the route, which is what RouteUrl's own docblock recommends
        //and which cannot depend on a RouteMatch that does not exist here. getPathInfo()
        //rather than getRequestUri() so a query string never leaks into the schema — the
        //laminas assemble() would not have carried one either.
        $schema = EventTextTable::getBlogPostSchema($object);
        $schema->setProperty(
            'url',
            $request->getSchemeAndHttpHost() . $request->getBaseUrl() . $request->getPathInfo()
        );

        return new Response($this->twig->render('books/blog-post.html.twig', [
            //The title is *translated* on this page — show.phtml passes it through
            //translate() for both headTitle() and its <h1>, and laminas' navigation
            //helper translates the breadcrumb label too. So the template sets
            //`page_title` and the crumb label itself rather than receiving them
            //pre-rendered: translation belongs where the text domain is in scope.
            'post_url'    => $this->urls->path('blog/blog-post', [
                'sw_id' => $identifier,
                'slug'  => (string) ($object['slug'] ?? ''),
            ]),
            'blog_url'    => $this->urls->path('blog'),
            'entity'      => $object,
            //A Twig\Markup rather than a string, so the template can print it without
            //`|raw`. That is a convention worth keeping: App\Twig\LaminasExtension's
            //docblock explains why — a `|raw` in a template is impossible for a reviewer
            //to tell from a mistake, whereas the safety claim belongs where the value is
            //produced. The markup is Spatie's own toScript(), i.e. the identical bytes
            //laminas emits.
            'schema'      => new Markup($schema->toScript(), 'UTF-8'),
        ]));
    }

    /**
     * Where the original sends a request for a post that is not there.
     *
     * **`welcome`, not `blog`** — and the difference is not obvious, which is why it is
     * measured rather than reasoned. `SionController::showAction()` redirects to
     * `$entitySpec->indexRoute ?: $this->getDefaultRedirectRoute()`, and the `blog-post`
     * entity spec sets **no** `index_route` (`composition` and `library` do; it does
     * not). So the fallback wins and the visitor lands on the home page, which is what
     * `/en/blog/posts/SL409999T/nothing-here` answers today: `302 -> /en/`.
     *
     * The route name is read from `sion_model.default_redirect_route` rather than
     * hardcoded, so the two front controllers cannot drift if that config changes.
     */
    private function toIndex(Request $request): RedirectResponse
    {
        $config = $this->laminas->config()['sion_model'] ?? [];
        $route  = is_array($config) && is_string($config['default_redirect_route'] ?? null)
            ? $config['default_redirect_route']
            : 'welcome';

        return new RedirectResponse($this->urls->path($route), Response::HTTP_FOUND);
    }

    private function table(): EventTextTable
    {
        /** @var EventTextTable $table */
        $table = $this->laminas->get(EventTextTable::class);

        return $table;
    }
}

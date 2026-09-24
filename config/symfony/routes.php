<?php

/**
 * Routes served by the Symfony kernel.
 *
 * Read top to bottom: UrlMatcher takes the *first* route that matches, so the
 * order in this file is the migration status of the site. Everything declared
 * above the catch-all is Symfony's; everything else still belongs to
 * laminas-mvc. Porting a route means moving one line up past `legacy`.
 *
 * Every route here also declares **who may reach it**, as an
 * App\Authorization\RouteAccess in its defaults. That is not decoration: a
 * Symfony-served route never boots laminas-mvc, so BjyAuthorize\Guard\Route never
 * runs for it, and before App\Authorization\RouteGuard existed a ported guarded
 * route admitted everyone while continuing to look perfectly healthy. The
 * declaration names an ACL resource the laminas guards already define, so one guard
 * entry governs both front controllers — and a route that declares nothing raises
 * App\Authorization\UndeclaredRouteAccess rather than being assumed either way.
 * `tools/acl-table.php` prints the whole picture and warns on a gap.
 *
 * Deliberately outside config/autoload/, which laminas-mvc glob-loads: this file
 * is not application config, and returning a RouteCollection into that merge
 * would break it.
 */

declare(strict_types=1);

use App\Authorization\RouteAccess;
use JTranslate\Routing\RouteAudience as JTranslateAudience;
use JUser\Routing\RouteAudience;
use App\Controller\AdminController;
use App\Controller\AssignmentSearchController;
use App\Controller\Api\ApiSchemaController;
use App\Controller\Api\ApiRouteNotFoundController;
use App\Controller\Api\AssociationsV3Controller;
use App\Controller\Api\MethodNotAllowedController;
use App\Controller\Api\PhrasesV3Controller;
use App\Controller\AssociationController;
use App\Controller\AssociationEditController;
use App\Controller\AssociationsController;
use App\Controller\BorrowerCheckoutsController;
use App\Controller\BookController;
use App\Controller\BorrowerController;
use App\Controller\CacheStatusController;
use App\Controller\CheckoutsController;
use App\Controller\ClearPersistentCacheController;
use App\Controller\CommentCreateController;
use App\Controller\CompositionController;
use App\Controller\EntityCreateController;
use App\Controller\ContentPageController;
use App\Controller\DataProblemsController;
use App\Controller\DictionaryController;
use App\Controller\EntityDeleteController;
use App\Controller\EntityEditController;
use App\Controller\HealthController;
use App\Controller\ImportFatherController;
use App\Controller\LibrariesController;
use App\Controller\LibraryCollectionsController;
use App\Controller\LibraryCheckoutController;
use App\Controller\LibraryController;
use App\Controller\LibraryDeleteController;
use App\Controller\LibraryFormController;
use App\Controller\LibraryImportConfigureController;
use App\Controller\LibraryImportsController;
use App\Controller\LibraryMassCheckoutController;
use App\Controller\LibraryNoticesController;
use App\Controller\LibraryPageController;
use App\Controller\LibrarySortController;
use App\Controller\LiteratureController;
use App\Controller\MovementController;
use App\Controller\MusicController;
use App\Controller\OneFiftyPreguntasController;
use App\Controller\PersonController;
use App\Controller\PersonsController;
use App\Controller\PhpInfoController;
use App\Controller\PreApril2020RedirectController;
use App\Controller\PublicationController;
use App\Controller\PublicationDuplicateController;
use App\Controller\PublicationReportsController;
use App\Controller\RolesController;
use App\Controller\SendToNewUrlController;
use App\Controller\ShrinesController;
use App\Controller\SitemapController;
use App\Controller\TextController;
use App\Controller\TextsController;
use App\Controller\TimelineController;
use App\Controller\ViewChangesController;
use App\Controller\WaysideShrinesController;
use App\Controller\NotFoundController;
use App\Http\LocalePrefix;
use App\Sion\ReservedVerbs;
use App\Sion\SiteWideIdentifier;
use App\Twig\LaminasExtension;
use App\View\SiteChrome;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use App\Locale\Locales;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$routes = new RouteCollection();

// The one route with no laminas twin at all: it predates the strangler and exists to
// answer a load balancer. There is no `route/…` resource to consult, so openness is
// stated rather than inferred.
$routes->add('health', new Route('/_health', [
    '_controller'          => HealthController::class,
    RouteAccess::ATTRIBUTE => RouteAccess::openToEveryone(
        'shadows no laminas route, so there is no guard entry to consult; it reports liveness only, '
        . 'which is nothing a visitor could not learn from the site answering at all'
    ),
]));

/**
 * Every ported path also has to answer under a locale prefix, because every
 * caller uses that form: `/en/sm/cache-status` is what tools/smoke-prod.sh, the
 * smoke suite and the phploy hooks ask for. Under laminas the prefix never
 * reaches the router — SlmLocale\Strategy\UriPathStrategy strips it first — and
 * Symfony has no such listener, so the literal path has to be matched here.
 *
 * The five values come from App\Locale\Locales, which mirrors the aliases in
 * config/autoload/juser.global.php (`slm_locale`) — the class rather than the config
 * because reading the merged config here would mean loading every laminas module
 * before the first route is declared, and test/Integration guards the two against
 * drift. They are constrained rather than left open so that a path with some *other*
 * first segment keeps falling through to `legacy` instead of being swallowed by a
 * two-segment pattern.
 *
 * The maintenance endpoints ignore the matched value — they emit no localized text.
 * An HTML route does not: App\Http\LocaleListener turns `_locale` into
 * \Locale::setDefault() before the controller runs, and the *absence* of `_locale`
 * is how a controller knows to redirect to the prefixed form the way SlmLocale would.
 *
 * The `.locale` suffix on the second name is load-bearing: App\Http\SymfonyRoute
 * strips it to recover the laminas route name a ported route shadows, which is what
 * the Twig layout compares against to mark a navigation item active.
 *
 * $access is a required argument rather than a defaulted one, and both twins get the
 * same instance: they are one page reached two ways, and a check that applied to only
 * one of them would be a hole shaped exactly like the locale prefix.
 *
 * $extra is for a controller that serves more than one route and needs to know which
 * — App\Controller\ContentPageController is the case, five static pages behind one
 * class. It merges *under* the two keys above, so a page cannot redeclare its own
 * authorization by accident.
 */
/**
 * The module text domain each page's strings live in — the domain JTranslate's dispatch
 * listener would have set from the laminas controller's namespace. Without it every
 * phrase that lives only in a module domain renders in English: measured on production,
 * `/es/shrines` showed "Schoenstatt shrine" where laminas shows "Santuario de
 * Schoenstatt". See App\Twig\LaminasExtension::translate().
 */
$textDomain = static fn (string $domain): array => [LaminasExtension::TEXT_DOMAIN_ATTRIBUTE => $domain];

$locales = Locales::pattern();
/**
 * $controller is either a service id — the controller class, for a class with one
 * `__invoke()` — or `[serviceId, 'method']` for a class serving more than one route.
 * Symfony's ControllerResolver hands a string class straight to instantiateController(),
 * which App\Container answers, so both forms resolve out of the same container.
 *
 * @param string|array{class-string, string} $controller
 * @param array<string, mixed> $extra
 * @param array<string, string> $requirements patterns for the path's own placeholders.
 *        Merged *under* the `_locale` constraint so a route cannot widen it by accident.
 * @param LocalePrefix|null $localePrefix whether the *unprefixed* twin answers itself or
 *        302s to the prefixed one, as SlmLocale does. Defaults to redirecting, because
 *        that is what every ported HTML page wants and what all forty-two controllers
 *        that used to hand-write it did. Pass it — by name, since it sits after two
 *        optional arguments — only for the machine endpoints, where the reason is
 *        recorded on the declaration. App\Http\LocalePrefixListener acts on it, and
 *        test/Integration/LocalePrefixDeclarationTest pins the exact set that opts out, so a
 *        new machine endpoint cannot acquire the redirect by inheriting this default.
 */
$ported = static function (
    string $name,
    string $path,
    string|array $controller,
    RouteAccess $access,
    array $extra = [],
    array $requirements = [],
    ?LocalePrefix $localePrefix = null
) use (
    $routes,
    $locales
): void {
    //The placeholders of this path, in declaration order, so App\Http\LocalePrefixListener
    //can rebuild the redirect target without compiling the Route or being handed the
    //collection. Read off the string rather than from Route::compile() because the answer
    //cannot change between here and dispatch, and because the unprefixed twin is declared
    //first — its own compiled form would not yet mention `_locale` to exclude.
    preg_match_all('/\{(\w+)\}/', $path, $placeholders);

    $defaults = [
        '_controller'           => $controller,
        RouteAccess::ATTRIBUTE  => $access,
        LocalePrefix::ATTRIBUTE => ($localePrefix ?? LocalePrefix::redirectsToPrefixed())
            ->withParams($placeholders[1]),
    ] + $extra;
    $routes->add($name, new Route($path, $defaults, $requirements));
    $routes->add(
        $name . '.locale',
        new Route('/{_locale}' . $path, $defaults, $requirements + ['_locale' => $locales])
    );
};

// SionModel's maintenance endpoints, ported 2026-08-05. Both are machine
// endpoints gated by a maintenance key the controller checks itself, so they need
// nothing the laminas MVC listeners provide — no session, no ACL guard, no view
// layer. See docs/laminas-exit.md.
//
// Declared open, and the reason is worth stating precisely because `guardedBy` would
// also have "worked": the laminas guards they shadow
// (`route/sion-model/cache-status`, `route/sion-model/clear-persistent-cache`) carry
// `null` in their roles, i.e. they admit everyone, so consulting them could only ever
// answer yes. What it would cost is a session start plus the ACL build — several
// database queries, some through Books\Model\LibraryTable — on the two endpoints a
// deploy hook and the production monitor call. tools/acl-table.php cross-checks the
// claim: should either guard stop being public, it warns that this declaration now
// diverges from it.
$maintenance = RouteAccess::openToEveryone(
    'the laminas guard it shadows is public (a null role admits everyone), and its real gate is the '
    . 'maintenance key App\Http\MaintenanceKey checks inside the controller. Consulting the ACL here '
    . 'would put a session and the role/resource queries behind it on an endpoint the deploy hooks '
    . 'call, for a foregone answer'
);
// Neither takes SlmLocale's hop to the prefixed form, and this is the pair the whole
// declaration exists for: a deploy hook and the production monitor ask for the bare path,
// and a 302 in front of a machine that reads a JSON body is an extra round trip at best.
// Their sibling sion-model/phpinfo *does* redirect, because it is only ever reached from a
// browser — which is why this cannot be inferred from the /sm/ prefix.
$noLocaleHop = LocalePrefix::servedHere(
    'a machine endpoint: tools/deploy.sh and tools/smoke-prod.sh call it directly, it emits JSON '
    . 'rather than localized text, and its gate is the maintenance key rather than anything the '
    . 'locale affects'
);
$ported(
    'sm-cache-status',
    '/sm/cache-status',
    CacheStatusController::class,
    $maintenance,
    localePrefix: $noLocaleHop
);
$ported(
    'sm-clear-persistent-cache',
    '/sm/clear-persistent-cache',
    ClearPersistentCacheController::class,
    $maintenance,
    localePrefix: $noLocaleHop
);

// The sitemap. Ported 2026-08-13 and rewritten the same day, once it turned out that Google
// had been discarding every URL in it.
//
// **The sitemap is a static file now.** `bin/console sitemap:build` writes
// public/sitemap.xml plus one public/sitemap-<kind>.xml per entity kind, and the `-s` guard
// near the top of public/.htaccess hands any request for an existing file to Apache before
// the rewrite to index.php ever happens. So this route is a *fallback*: it is reached only
// when the file is missing — a first deploy, or someone deleted it — builds the files, and is
// then unreachable again. There is deliberately no route for the individual parts, because
// nothing but Apache ever serves them.
//
// Why the files sit at the docroot root rather than under /sitemap/, which is what the first
// version of this port did: a sitemap may only list URLs at or below its own directory, so a
// file at /sitemap/pages.xml cannot legally contain a single /en/… URL. Measured against
// production on 2026-08-13 — all 36,730 URLs were out of scope, plus robots.txt named
// /en/sitemap.xml, which put the index itself in the wrong directory for the parts it listed.
// docs/sitemap.md has the quotations from the specification.
//
// One deliberate deviation, unchanged: this answers the bare path instead of taking
// SlmLocale's hop to /en/…, which every ported *HTML* route reproduces. A sitemap has no
// locale — each file carries all five languages as xhtml:link alternates — so the prefix
// names nothing about the content, and a 302 in front of a crawler-facing file is cost
// without meaning. The prefixed form still answers, because $ported() declares both, and
// robots.txt now names the bare one.
$ported(
    'sitemap',
    '/sitemap.xml',
    SitemapController::class,
    RouteAccess::guardedBy('route/sitemap'),
    localePrefix: LocalePrefix::servedHere(
        'a sitemap has no locale of its own — each file names all five languages as xhtml:link '
        . 'alternates — so the prefix would describe nothing, and Apache serves this file '
        . 'directly at the bare path: PHP is reached only when it is missing'
    )
);

// The first HTML route, ported 2026-08-05, and the reason templates/ and the Twig
// layer exist. Checked against its own laminas resource rather than declared open,
// even though `route/shrines` is guarded ['null','guest','user'] and a null role
// means everyone: the page already builds the ACL for its moderator markup, so the
// check costs nothing extra, and if that guard is ever tightened both front
// controllers tighten together. The laminas route it shadows stays exactly as it was
// — production still serves it, SYMFONY_KERNEL being unset there.
$ported(
    'shrines',
    '/shrines',
    ShrinesController::class,
    RouteAccess::guardedBy('route/shrines'),
    $textDomain('Schoenstatt')
);

// The same index over the wayside-shrine associations, ported 2026-08-07. Guarded
// `['user', 'guest', null]` exactly as `shrines` is, and checked against its own
// resource for the same reason: the page builds the ACL anyway for its moderator
// markup, and one guard entry then governs both front controllers.
//
// Only `/wayside-shrines` itself. It has no laminas child routes — the "submitting
// photos" page it links to is a child of `shrines` and still falls through to
// `legacy`.
$ported(
    'wayside-shrines',
    '/wayside-shrines',
    WaysideShrinesController::class,
    RouteAccess::guardedBy('route/wayside-shrines'),
    $textDomain('Schoenstatt')
);

// The first *restricted* route, ported 2026-08-06 — the proof that
// App\Authorization\RouteGuard works, and so the reason the other 148 restricted
// routes became portable at all. `route/admin` admits sch_moderator and translator
// (plus their descendants): an anonymous visitor gets 302 to the sign-in page, a
// signed-in visitor holding neither gets 403. Measured in
// test/Smoke/AdminAuthorizationSmokeTest.
//
// Only `/admin` itself. Its laminas children — /admin/import-father and
// /admin/translations — are separate routes with their own guards, and a literal path
// with no trailing-slash variant is what leaves every one of them falling through to
// `legacy`. (/admin/maintenance and /admin/literature-maintenance were two more until
// 2026-08-17, when both were retired rather than ported — see docs/BACKLOG.md.)
$ported(
    'admin',
    '/admin',
    AdminController::class,
    RouteAccess::guardedBy('route/admin'),
    $textDomain('Schoenstatt')
);

// Batch 17, 2026-09-08: the last HTML page laminas served. `sch_administrator` only — the
// one admin-index link that even `sch_moderator` never sees. The form's option list is a
// remote call to Patres made by the laminas form factory; see App\Controller\ImportFatherController.
$ported(
    'admin/import-father',
    '/admin/import-father',
    ImportFatherController::class,
    RouteAccess::guardedBy('route/admin/import-father'),
    $textDomain('Schoenstatt')
);

// The static content pages, ported 2026-08-07. Five routes, one controller and one
// template each: on the laminas side these are five actions whose entire body is
// `return new ViewModel()` over a Markdown heredoc in the .phtml, so five Symfony
// controllers would have ported the duplication too. What differs per page is
// declared here instead — see App\Controller\ContentPageController.
//
// A breadcrumb names the laminas route it points at rather than a URL, so the
// prefix comes from App\Laminas\RouteUrl and the link keeps working while its
// target is still on the laminas side. The trails are copied from what the
// `navigation` config produces today, measured page by page: /privacy is not in the
// navigation at all and so has none, and `welcome` has exactly one crumb.
/**
 * @param list<array{label: string, route: string}> $breadcrumbs
 * @return array<string, mixed>
 */
$content = static fn (
    string $template,
    string $title,
    array $breadcrumbs = [],
    string $domain = 'Application'
): array => [
    ContentPageController::TEMPLATE         => $template,
    ContentPageController::PAGE_TITLE       => $title,
    ContentPageController::BREADCRUMBS      => $breadcrumbs,
    LaminasExtension::TEXT_DOMAIN_ATTRIBUTE => $domain,
];
$home = ['label' => 'Home', 'route' => 'welcome'];

// The site's front page. Empty page title on purpose — indexAction sets no
// headTitle, so laminas renders `<title>Schoenstatt Link</title>` and the layout
// reproduces that by omitting the separator.
$ported(
    'welcome',
    '/',
    ContentPageController::class,
    RouteAccess::guardedBy('route/welcome'),
    $content('content/welcome.html.twig', '', [$home])
);

$ported(
    'developers',
    '/developers',
    ContentPageController::class,
    RouteAccess::guardedBy('route/developers'),
    $content('content/developers.html.twig', 'Developers Center', [
        $home,
        ['label' => 'Developers Center', 'route' => 'developers'],
    ])
);

$ported(
    'acknowledgements',
    '/acknowledgements',
    ContentPageController::class,
    RouteAccess::guardedBy('route/acknowledgements'),
    $content('content/acknowledgements.html.twig', 'Security research acknowledgements', [
        $home,
        ['label' => 'Security research acknowledgements', 'route' => 'acknowledgements'],
    ])
);

// No breadcrumbs and no page title: privacy.phtml calls neither headTitle() nor
// appears in the `navigation` config, and /en/privacy renders neither today.
$ported(
    'privacy',
    '/privacy',
    ContentPageController::class,
    RouteAccess::guardedBy('route/privacy'),
    $content('content/privacy.html.twig', '')
);

// The one content page whose body is chosen by locale, and so the first ported route
// that would visibly break if App\Http\LocaleListener stopped working. A child of
// `shrines` in the router, which is why its breadcrumb trail starts there rather
// than at Home.
$ported(
    'shrines/submitting-photos',
    '/shrines/submitting-photos',
    ContentPageController::class,
    RouteAccess::guardedBy('route/shrines/submitting-photos'),
    $content('content/submitting-photos.html.twig', 'Submitting photos', [
        ['label' => 'Shrines', 'route' => 'shrines'],
        ['label' => 'Submitting photos', 'route' => 'shrines/submitting-photos'],
    ], 'Schoenstatt')
);

// SionModel's phpinfo page, ported 2026-08-07. Checked against its own resource, and
// that resource is the sharpest one on the site: `route/sion-model/phpinfo` admits
// `sch_administrator` alone, a role with no descendants, so exactly 1 of 43 roles gets
// in. /admin proved the guard admits the right people; this is the tightest available
// proof that it refuses everyone else.
$ported(
    'sion-model/phpinfo',
    '/sm/phpinfo',
    PhpInfoController::class,
    RouteAccess::guardedBy('route/sion-model/phpinfo'),
    $textDomain('SionModel')
);

// SionModel's data-problems list, ported 2026-08-07. Guarded `sch_general_moderator`
// (2 effective roles of 43). Until 2026-09-08 this was "the read-only route only": its
// sibling `sion-model/auto-fix-data-problems` rendered the same .phtml with a CSRF
// confirm form and stayed on laminas. That sibling was **retired rather than ported** —
// the only fix it could apply (backfilling `lib_books.sort_text`) is a strict subset of
// what the ported, `administrate`-gated `refresh-sort` does for a library. No method
// constraint, as the laminas route has none.
//
// This is the port that needed App\Laminas\EntityFormatter — `formatEntity` is the
// most-reused of the helpers a Symfony route cannot call, and reproducing it is what
// unblocks the rest of the admin pages.
$ported(
    'sion-model/data-problems',
    '/sm/data-problems',
    DataProblemsController::class,
    RouteAccess::guardedBy('route/sion-model/data-problems'),
    $textDomain('SionModel')
);

// SionModel's changes log, ported 2026-08-08 — the last of the ten in this batch, and
// the one that needed two other things fixed first. It exhausted a 512 MB limit before it
// could be characterized (fixed in SionModel), and its entity column formats whatever
// type each change row names, which meant App\Laminas\EntityFormatter had to stop
// refusing `publication` and `role` — sch_changes holds 18,243 and 1,317 of them.
//
// Guarded `sch_general_moderator, view_changes`. Only the page itself: the entity-level
// change panel that renders the same partial lives inside laminas' SionController and is
// not a route.
$ported(
    'sion-model/view-changes',
    '/sm/view-changes',
    ViewChangesController::class,
    RouteAccess::guardedBy('route/sion-model/view-changes'),
    $textDomain('SionModel')
);

// ---------------------------------------------------------------------------
// Batch 4, ported 2026-08-08: the public browse surface plus three restricted
// index pages. Everything here is a read-only GET whose laminas action is a query
// and a template — no form is ported, because there is no form layer on the
// Symfony side and JUser's static table adapter (see the onBootstrap table in
// docs/laminas-exit.md) is still unreproduced.
// ---------------------------------------------------------------------------

// The Fr. Kentenich timeline. Route name `events`, path /timeline — they disagree
// on the laminas side too, and the *name* is what has to be kept: it is how the
// layout recognises the current page.
$ported(
    'events',
    '/timeline',
    TimelineController::class,
    RouteAccess::guardedBy('route/events'),
    $textDomain('Books')
);

// Every composition, grouped by language.
$ported('music', '/music', MusicController::class, RouteAccess::guardedBy('route/music'), $textDomain('Books'));

// The dictionaries. `/dictionary` renders an empty body — that is what its .phtml does,
// see App\Controller\DictionaryController — and `/dictionary/{inLanguage}` is the page.
$ported(
    'dictionary',
    '/dictionary',
    [DictionaryController::class, 'index'],
    RouteAccess::guardedBy('route/dictionary'),
    $textDomain('Books')
);
$ported(
    'dictionary/inLanguage',
    '/dictionary/{inLanguage}',
    [DictionaryController::class, 'inLanguage'],
    RouteAccess::guardedBy('route/dictionary/inLanguage'),
    //Literature, not a "Dictionary" item — there is none in the navbar. The dictionary
    //pages hang under Literature > Dictionaries, so `publications` is what lights up.
    $textDomain('Books') + [SiteChrome::NAV_ROUTE => 'publications'],
    //the laminas route puts no constraint on this segment either, but it must not eat
    //`/dictionary/create` or `/dictionary/{entry_id}/edit`, both of which are still on
    //laminas. A two-letter language code is what every real caller uses.
    ['inLanguage' => '[a-z]{2,3}']
);

// A book served from a file on disk. Declared before nothing in particular — no other
// ported route lives under /literature yet — but it must stay above `legacy`.
$ported(
    'publications/one-fifty-preguntas',
    '/literature/150-preguntas-sobre-schoenstatt',
    OneFiftyPreguntasController::class,
    RouteAccess::guardedBy('route/publications/one-fifty-preguntas'),
    $textDomain('Books') + [SiteChrome::NAV_ROUTE => 'publications']
);

// The three restricted index pages. Each is one table behind one guard, and each guard
// is a different shape, which is what makes them worth having together: `associations`
// admits most signed-in movement roles, `roles` admits sch_moderator and its two
// descendants, and `libraries` admits lib_administrator alone.
$ported(
    'associations',
    '/associations',
    AssociationsController::class,
    RouteAccess::guardedBy('route/associations'),
    $textDomain('Schoenstatt')
);
$ported(
    'roles',
    '/roles',
    RolesController::class,
    RouteAccess::guardedBy('route/roles'),
    $textDomain('Schoenstatt')
);
$ported(
    'libraries',
    '/libraries',
    LibrariesController::class,
    RouteAccess::guardedBy('route/libraries'),
    $textDomain('Books')
);

// ---------------------------------------------------------------------------
// The first *form* route, ported 2026-08-09, and the reason src/Form/ exists.
// ---------------------------------------------------------------------------
//
// docs/laminas-exit.md called the form routes the largest single thing left, blocked on
// two things: no form layer, and JUser's unreproduced static table adapter. The first
// was true and SionModel\Form\BootstrapFormRenderer answers it. The second turned out not to
// apply here — only CreateRoleForm, EditUserForm, DeleteUserForm and EditPhraseForm
// read that adapter, through a NoRecordExists validator, and AssociationForm touches
// neither. So this form could move while the user forms could not. Both are settled
// now: those four take an Adapter as a constructor argument as of 2026-08-14 and
// GlobalAdapterFeature's static registry is gone from first-party code, which
// test/Unit/NoStaticDbAdapterTest keeps true.
//
// GET renders, POST validates and writes. Both methods on one route because the
// laminas route has no method constraint either, and adding one would turn a
// mistaken GET into a 405 where today it renders the form.
//
// Guarded by its own resource. `route/association-edit` admits sch_moderator and
// sch_user — and registration grants sch_user, so in practice any signed-in visitor
// may edit any association. That is the *existing* rule, unchanged by the port and
// stated here because it is surprising: the port neither widens nor narrows it.
//
// The `sw_id` constraint is the association identifier regex itself, from
// Schoenstatt\Validator\SchoenstattLinkIdentifier, so that /SL200001L/edit — a
// publication, still on laminas — keeps falling through to `legacy` rather than
// being swallowed here and 404'd.
$ported(
    'association-edit',
    '/{sw_id}/edit',
    AssociationEditController::class,
    RouteAccess::guardedBy('route/association-edit'),
    $textDomain('Schoenstatt'),
    ['sw_id' => trim(SchoenstattLinkIdentifier::ENTITY_REGEXS[
        SchoenstattLinkIdentifier::ENTITY_ASSOCIATION
    ], '/^$')]
);

// ---------------------------------------------------------------------------
// Batch 7, ported 2026-08-13: the edit surface. The entity edit forms that share
// SionModel\Controller\SionController::editAction().
// ---------------------------------------------------------------------------
//
// One controller for all of them — App\Controller\EntityEditController — because on the
// laminas side they are one *method*, reached through ten controllers that mostly add
// nothing to it. What differs per page is declared here; what differs behaviourally is
// the three hooks that class documents. App\Sion\EntityEdit is the shared action, the
// counterpart of App\Sion\EntityShow for batch 5's show pages.
//
// **These are the first ported routes that write to the database on POST**, other than
// `association-edit`, which this batch folds onto the same shared action. Everything that
// makes that safe was already in place and is worth naming: the form comes out of the
// laminas container so its validation is the application's own, CSRF works because
// App\Http\SessionListener has already started the laminas session, and the write goes
// through SionTable::updateEntity() exactly as SionController does it.
//
// `library-imports/library-import/edit` is not here, and never will be. It is not an edit
// form: it configures a spreadsheet import, previews what it would do, and performs it.
// It has its own controller — App\Controller\LibraryImportConfigureController — declared
// with the rest of the import surface further down.
/**
 * @param array<string, mixed> $extra
 * @param array<string, string> $requirements
 */
$edit = static function (
    string $name,
    string $path,
    string $entity,
    string $idParam,
    string $template,
    string $pageTitle,
    string $domain,
    array $extra = [],
    array $requirements = []
) use (
    $ported,
    $textDomain
): void {
    $ported(
        $name,
        $path,
        EntityEditController::class,
        RouteAccess::guardedBy('route/' . $name),
        $textDomain($domain) + [
            EntityEditController::ENTITY     => $entity,
            EntityEditController::ID_PARAM   => $idParam,
            EntityEditController::TEMPLATE   => $template,
            EntityEditController::PAGE_TITLE => $pageTitle,
        ] + $extra,
        $requirements
    );
};

// A document from the Kentenich corpus. First of the batch, and the simplest: every field
// goes through a plain row, and TextForm declares only element types
// SionModel\Form\BootstrapFormRenderer already rendered for the association form.
//
// `sw_id` rather than a numeric id, so ID_KIND names the entity whose identifier regex to
// translate — the same job TextsController::getEntityIdParam() does on laminas.
//
// **No breadcrumbs and no index route.** Measured from the laminas rendering: the page
// renders `<title>Edit text - Schoenstatt Link</title>`, an `<h1>` and no breadcrumb trail
// at all, because `text-edit` is not in the navigation config. The entity spec declares no
// `index_route` either, so REDIRECT_TARGET names where a successful write goes —
// reproducing TextsController::redirectAfterEdit(), which sends the moderator to the text
// itself rather than to a list.
$edit(
    'text-edit',
    '/{sw_id}/edit',
    'text',
    'sw_id',
    'books/text-edit.html.twig',
    'Edit text',
    'Books',
    [
        EntityEditController::ID_KIND        => SchoenstattLinkIdentifier::ENTITY_TEXT,
        EntityEditController::REDIRECT_TARGET => 'text',
    ],
    ['sw_id' => SiteWideIdentifier::pattern(SchoenstattLinkIdentifier::ENTITY_TEXT)]
);

// A song, guarded `sch_moderator, sch_user` — and sch_user is a default role, so this is
// another route whose guard means "signed in". Unlike the Books entities, `composition`
// declares no acl_resource_id_field, so there is no per-row check behind it either: any
// signed-in visitor may edit any composition. That is the existing rule, unchanged by the
// port and stated because it is surprising.
//
// Its laminas controller overrides `getEntityIdParam()` to translate the identifier, which
// is what ID_KIND does here. The redirect needs no override: the spec's
// `default_route_params` maps sw_id and slug, which is branch 3 of
// App\Sion\EntityEdit::redirectTarget().
$edit(
    'composition-edit',
    '/{sw_id}/edit',
    'composition',
    'sw_id',
    'books/composition-edit.html.twig',
    'Edit a composition',
    'Books',
    [EntityEditController::ID_KIND => SchoenstattLinkIdentifier::ENTITY_COMPOSITION],
    ['sw_id' => SiteWideIdentifier::pattern(SchoenstattLinkIdentifier::ENTITY_COMPOSITION)]
);

// A library book, and the largest form in the batch by field count. Two things it needs
// that no earlier one did: a `<button type="button">` for the next-free-call-number helper,
// and the value that button writes — `next_within_library_id`, read off
// `BookForm::getLibraryOptions()` rather than out of an element, which is why
// EXTRA_VARIABLES names a provider for it.
//
// Same per-row check as the collection route above, against the same `library_<id>`
// resource with the same `administrate` privilege.
$edit(
    'books/book/edit',
    '/books/{book_id}/edit',
    'book',
    'book_id',
    'books/book-edit.html.twig',
    'Edit book',
    'Books',
    [EntityEditController::EXTRA_VARIABLES => 'nextWithinLibraryId'],
    ['book_id' => '[0-9]{1,6}']
);

// A library's own configuration, the third of the library-scoped forms. Same per-row check
// as the collection and book routes; its form likewise comes from
// App\Books\LibraryScopedForms rather than the container.
$edit(
    'libraries/library/edit',
    '/libraries/{library_id}/edit',
    'library',
    'library_id',
    'books/library-edit.html.twig',
    'Edit library configuration',
    'Books',
    [],
    ['library_id' => '[0-9]{1,5}']
);

// An assignment — who holds which role in which association, and between which dates.
//
// **The first ported page with a delete-confirmation modal**, i.e. a second form, gated on
// `route/assignments/assignment/delete` (sch_general_moderator) rather than on the
// permission that let the visitor reach the page (sch_moderator). Its action is the laminas
// delete route, which this batch does not port; the CSRF token crosses because both front
// controllers read the same session.
//
// The form's three identity selects arrive disabled — `EditAssignmentFormFactory` calls
// `prepareforEdit()`, which also sets a validation group of the four date fields plus the
// token. That matters for the POST: `getData()` returns only what the validation group
// names, so the disabled selects cannot be smuggled past by a hand-built request.
$edit(
    'assignments/assignment/edit',
    '/assignments/{assignment_id}/edit',
    'assignment',
    'assignment_id',
    'schoenstatt/assignment-edit.html.twig',
    'Edit Assignment',
    'Schoenstatt',
    [EntityEditController::DELETE_ROUTE => 'assignments/assignment/delete'],
    ['assignment_id' => '[0-9]{1,6}']
);

// A person — the tenth and last of the edit surface, and the only one whose <title> is
// built from the record rather than fixed. `Edit %s` is declared here so this file stays
// the inventory of page titles; the template translates it and inserts the name, then sets
// `page_title_translate = false` so the name itself is not run through the translator.
//
// EXTRA_VARIABLES supplies that name. DELETE_ROUTE points at the laminas delete route
// behind the second delete-confirmation modal in the batch, gated on
// `route/persons/person/delete` rather than on the `sch_moderator` that reached this page.
//
// No REDIRECT_TARGET: the spec declares `show_route`/`show_route_key`, branch 2 of
// App\Sion\EntityEdit::redirectTarget(), and PersonsController overrides nothing.
//
$edit(
    'persons/person/edit',
    '/persons/{person_id}/edit',
    'person',
    'person_id',
    'schoenstatt/person-edit.html.twig',
    'Edit %s',
    'Schoenstatt',
    [
        EntityEditController::EXTRA_VARIABLES => 'personName',
        EntityEditController::DELETE_ROUTE    => 'persons/person/delete',
    ],
    ['person_id' => '[0-9]{1,5}']
);

// A movement role, guarded `sch_moderator`. Two peculiarities of its .phtml are carried
// over and documented in the template: the fields partial does not render its own submit
// button, and its first two selects render with the translator off because an association
// name and a role title are data rather than interface text.
//
// **`/roles/1/edit` is a 302, not a form**, and that is correct: `getRole()` answers null
// for 142 of the 1,468 rows in `sch_roles` — role 1 hangs off an association the
// projection filters — so `editAction()`'s not-found branch fires. Measured against
// laminas for an account holding every role, and reproduced here: flash, then a redirect
// to the `roles` index, which the entity spec names.
$edit(
    'roles/role/edit',
    '/roles/{role_id}/edit',
    'role',
    'role_id',
    'schoenstatt/role-edit.html.twig',
    'Edit Role',
    'Schoenstatt',
    [],
    //the laminas constraint exactly, which is also what keeps `/roles/create` out
    ['role_id' => '[0-9]{1,5}']
);

// A library collection, and the first route in the batch with a *per-row* check on top of
// its route guard: the `collection` spec declares `acl_resource_id_field => resourceId`
// with `acl_edit_permission => administrate`, so App\Sion\EntityEdit asks the ACL about
// `library_<id>` — the dynamic resource Books\Model\LibraryTable contributes — after the
// guard has already said yes.
//
// **And the row check is substantially the whole of the protection here.** The guard admits
// `lib_user`, which `user_role` marks `is_default = 1` — along with `pub_user`, `sch_user`
// and `bib_user` — so registration grants it and every signed-in visitor holds it. Measured
// 2026-08-13, by `grantRoles()` refusing to grant a role the fresh account already had. The
// same surprise `association-edit` records for `sch_user`, one entity over: the route-level
// guard means little more than "signed in", and what actually separates one library's
// moderator from another's is the per-row check.
// `test/Smoke/Batch7EditSurfaceSmokeTest` asserts that refusal explicitly, because a
// route that lost it would look perfectly healthy.
$edit(
    'collections/collection/edit',
    '/collections/{collection_id}/edit',
    'collection',
    'collection_id',
    'books/collection-edit.html.twig',
    'Configure Collection',
    'Books',
    [],
    ['collection_id' => '[0-9]{1,5}']
);

// A dictionary entry, guarded `dict_administrator` — one role, no descendants, the
// sharpest guard in the batch.
//
// Declared **after** `dictionary/inLanguage` (`/dictionary/{inLanguage}`), which it cannot
// collide with anyway: that route's segment is constrained to two or three letters and
// this path has three segments. The ordering is the file's convention.
//
// The entity spec declares no `index_route`, so REDIRECT_TARGET names where a successful
// write goes — reproducing DictionaryController::redirectAfterEdit().
$edit(
    'dictionary/entry/edit',
    '/dictionary/{entry_id}/edit',
    'dictionary-entry',
    'entry_id',
    'books/dictionary-entry-edit.html.twig',
    'Edit dictionary entry',
    'Books',
    [EntityEditController::REDIRECT_TARGET => 'dictionaryEntry'],
    ['entry_id' => '[0-9]{1,6}']
);

// A publication — the ninth of the edit surface and the one the batch left behind, because
// its partial is the only one that builds `<select>`s by hand instead of rendering rows.
// Three things it needs that none of the eight did:
//
//   - `form_select_without_options`, for five pickers whose option lists are the whole
//     person and publication tables. See SionModel\Form\BootstrapFormRenderer.
//   - EXTRA_VARIABLES, for the JSON those pickers hand to selectize. The provider
//     reproduces PublicationsController::injectPublicationValueOptions(), including its
//     removal of the publication's own id from the "main edition" list.
//   - DELETE_ROUTE, because edit.phtml offers a delete button. It is a plain link here,
//     not a confirmation modal — `publication-delete` renders its own confirmation, and
//     the template gates the link on the ACL exactly as the .phtml does.
//
// No REDIRECT_TARGET: the spec declares `show_route`/`show_route_key`, which is branch 2
// of App\Sion\EntityEdit::redirectTarget(), and PublicationsController overrides nothing.
$edit(
    'publication-edit',
    '/{sw_id}/edit',
    'publication',
    'sw_id',
    'books/publication-edit.html.twig',
    'Edit publication',
    'Books',
    [
        EntityEditController::ID_KIND         => SchoenstattLinkIdentifier::ENTITY_PUBLICATION,
        EntityEditController::EXTRA_VARIABLES => 'publicationValueOptions',
        EntityEditController::DELETE_ROUTE    => 'publication-delete',
    ],
    ['sw_id' => SiteWideIdentifier::pattern(SchoenstattLinkIdentifier::ENTITY_PUBLICATION)]
);

// ---------------------------------------------------------------------------
// Batch 9, ported 2026-08-15: the create surface. Nine of the sixteen `*/create` routes,
// behind App\Controller\EntityCreateController over App\Sion\EntityCreate — the third verb
// of the same shared action, after edit (batch 7) and delete (batch 8).
//
// **Seven are left on laminas, and none of them is simply "not done yet."**
//
// - `persons/create` and `texts/create` are **broken on laminas today** and porting them
//   would mean reproducing the breakage in new code. A person without a spouse cannot be
//   created at all — `SpousePersonId` receives `''` and MariaDB refuses the integer column,
//   where the *edit* form posts the identical value and saves, because updateEntity() writes
//   only changed columns and createEntity() writes them all. A text **is** created and then
//   the redirect never happens: `EventTextTable` reads `$entityData['kind']` off the row that
//   does not exist yet, and the warning reaches the page before the Location header. Both
//   measured against the capsule on 2026-08-15 and filed in docs/BACKLOG.md.
// - `juser/create` and `juser/create-role` do not use `createAction()` at all —
//   `UsersController::createAction()` is a standalone implementation — so they belong with
//   the `juser/*` bloc, not here.
// - `library-imports/library/create` takes a file upload and stores it before writing a
//   row, which no shared create action does.
// - `publication-create-new-edition` is its own action, not `createAction()`.
// - `events/create` is **unportable**: its spec's `create_action_form` is commented out, its
//   `create_action_valid_data_handler` names a method that exists nowhere in the repository,
//   it has no template, and docs/acl-baseline.json lists it under "no guard entry", i.e. default
//   deny. Three independent reasons, same conclusion as batch 8's unreachable deletes.
//
// **Order does not matter for these nine**, unusually. Every route that could swallow a
// `create` segment is already constrained away from it: `/dictionary/{inLanguage}` and
// `/literature/{inLanguage}` take two letters, `/literature/{publication_id}` takes digits,
// and `/associations/{sw_id}` takes a site-wide identifier. That is stated rather than
// relied on silently — `test/Integration/CreateRouteShadowingTest` asserts each create path
// matches its own route.
/**
 * @param array<string, mixed> $extra
 * @param array<string, string> $requirements
 */
$create = static function (
    string $name,
    string $path,
    string $entity,
    string $template,
    string $pageTitle,
    string $domain,
    array $extra = [],
    array $requirements = []
) use (
    $ported,
    $textDomain
): void {
    $ported(
        $name,
        $path,
        EntityCreateController::class,
        RouteAccess::guardedBy('route/' . $name),
        $textDomain($domain) + [
            EntityCreateController::ENTITY     => $entity,
            EntityCreateController::TEMPLATE   => $template,
            EntityCreateController::PAGE_TITLE => $pageTitle,
        ] + $extra,
        $requirements
    );
};

// A new association or shrine. Five query parameters prefill it, and the two that name a
// row are checked against the select's own options before being set — see the controller.
$create(
    'associations/create',
    '/associations/create',
    'association',
    'schoenstatt/association-create.html.twig',
    'Create New Association',
    'Schoenstatt',
    [EntityCreateController::PREFILL => 'association']
);

// A new assignment: who holds which role in which association. `?roleId=` and `?personId=`
// prefill it, and the role hint has to discover its association first — the role select is
// populated from the association in the browser.
$create(
    'assignments/create',
    '/assignments/create',
    'assignment',
    'schoenstatt/assignment-create.html.twig',
    'Create New Assignment',
    'Schoenstatt',
    [EntityCreateController::PREFILL => 'assignment']
);

// A new role on an association.
$create(
    'roles/create',
    '/roles/create',
    'role',
    'schoenstatt/role-create.html.twig',
    'Create New Role',
    'Schoenstatt'
);

// A new library book. The first of the two routes carrying LIBRARY_PERMISSION: BooksController
// checks `library_<id>` for `administrate` before it does anything else, and that check is the
// only authorization this page has beyond its route guard.
$create(
    'books/create',
    '/books/create/{library_id}',
    'book',
    'books/book-create.html.twig',
    'Add new library book',
    'Books',
    [
        EntityCreateController::LIBRARY_PARAM      => 'library_id',
        EntityCreateController::LIBRARY_PERMISSION => 'administrate',
        EntityCreateController::EXTRA_VARIABLES    => 'nextWithinLibraryId',
        EntityCreateController::PREFILL            => 'book',
    ],
    ['library_id' => '[0-9]{1,5}']
);

// A new collection inside a library. Same per-library check as the book route.
$create(
    'collections/create',
    '/collections/create/{library_id}',
    'collection',
    'books/collection-create.html.twig',
    'New collection',
    'Books',
    [
        EntityCreateController::LIBRARY_PARAM      => 'library_id',
        EntityCreateController::LIBRARY_PERMISSION => 'administrate',
    ],
    ['library_id' => '[0-9]{1,5}']
);

// A new library. Library-scoped like the two above and yet carries **no** library — the
// record being created is the library — so it declares neither LIBRARY_PARAM nor a
// permission, and LibraryScopedForms::formForLibrary() takes null. Its laminas guard admits
// `guest`, which docs/acl-baseline.json flags: `guest` is not a default role, so this page really
// is reachable signed-out. Unchanged by the port, and filed rather than fixed here.
$create(
    'libraries/create',
    '/libraries/create',
    'library',
    'books/library-create.html.twig',
    'Create new library',
    'Books'
);

// A new song.
$create(
    'music/create-composition',
    '/music/create-composition',
    'composition',
    'books/composition-create.html.twig',
    'Add new composition',
    'Books'
);

// A new publication. Needs the same three selectize lists the edit page does, minus the
// "cannot be its own main edition" exclusion, which has no id to exclude yet.
$create(
    'publications/create',
    '/literature/create',
    'publication',
    'books/publication-create.html.twig',
    'Create new publication',
    'Books',
    [EntityCreateController::EXTRA_VARIABLES => 'publicationValueOptions']
);

// A new dictionary entry. REDIRECT_TARGET because DictionaryController::redirectAfterCreate()
// delegates to its redirectAfterEdit(), which sends the moderator to the dictionary of the
// entry's own language rather than to the entry — so the spec's `dictionary/entry` redirect
// route is never used.
$create(
    'dictionary/create',
    '/dictionary/create',
    'dictionary-entry',
    'books/dictionary-entry-create.html.twig',
    'Create new dictionary entry',
    'Books',
    [EntityCreateController::REDIRECT_TARGET => 'dictionaryEntry']
);

// Batch 10, ported 2026-08-15: the two create routes batch 9 left behind, once the laminas
// bugs that made them unportable were fixed. **Eleven of the sixteen now.**
//
// A new person, guarded `sch_moderator`. The only route in either batch whose spec declares a
// `create_action_valid_data_handler`, hence VALID_DATA_RULE — `PersonsController::createPerson()`
// requires a first name or a last name before it writes anything, a relation between two
// individually optional fields that no input filter can express.
//
// Three defects stood between this route and a port, and the third is the one to remember.
// Two are fixed in code both front controllers share: `spousePersonId` posting `''` into an
// integer column (PersonForm), and — from batch 7 — the four patres date fields no template on
// this site renders, whose absence writes NULL to two NOT NULL columns. The third is that the
// fix for the second exists **only on the Symfony side**: `_person-fields.html.twig` carries
// the hidden inputs and `fields-partial.phtml` does not. Porting the route is what retires the
// laminas page, which is the same precedent batch 7 set for the edit form and the reason
// `/persons/create` answers 500 today while `/persons/{id}/edit` does not.
$create(
    'persons/create',
    '/persons/create',
    'person',
    'schoenstatt/person-create.html.twig',
    'Create New Person',
    'Schoenstatt',
    [EntityCreateController::VALID_DATA_RULE => 'person']
);

// A new Kentenich text, guarded `texts_moderator` — the sharpest guard of the eleven.
//
// REDIRECT_TARGET because `TextsController::redirectAfterCreate()` is an override, and an
// unusual one: it builds the identifier and the slug from the submitted data rather than
// re-reading the row, so the redirect lands on `/{sw_id}/{slug}` for a record it never loads.
// See App\Sion\EntityCreate::textTarget().
//
// What made this unportable was not the form: creating a text worked and then showed a blank
// page, because `EventTextTable::preprocessText()` read `$entityData['kind']` off a row that
// does not exist on a create and the warning beat the `Location` header out. Fixed in that
// method — see it for why the guard is on `legacyFile` and not on `kind` — and guarded by
// test/Integration/PreprocessorCreateSafetyTest.
$create(
    'texts/create',
    '/texts/create',
    'text',
    'books/text-create.html.twig',
    'New text',
    'Books',
    [EntityCreateController::REDIRECT_TARGET => 'text']
);

// Batch 8, ported 2026-08-14: the delete surface. The seven entity delete
// confirmations that share SionController::deleteAction().
// ---------------------------------------------------------------------------
//
// The destructive twin of the edit batch above, and every one of these is the target of a
// button or a modal on a page that batch already ported. One controller,
// App\Controller\EntityDeleteController, over one shared action, App\Sion\EntityDelete, and
// **one template for all seven** — because the laminas page is one view script with an `<h1>`
// and a three-element form in it, no breadcrumbs and no record name.
//
// **These are the first ported routes that destroy a record on POST.** What makes that safe
// is what made the edit batch safe — the form is SionModel's own, with its CSRF element;
// App\Http\SessionListener has started the laminas session before the guard runs; the write
// goes through SionTable::deleteEntity() — plus one thing that had to be *fixed* first: the
// Cancel button used to delete the record on both front controllers. See
// SionModel\Form\DeleteEntityForm, which now declares it a `Button`.
//
// **Seven of twelve, and the five left out are not a backlog.** `event-delete`,
// `libraries/library/delete` and `sion-model/delete-entity` have no route guard entry, and
// BjyAuthorize's Route guard is default-deny, so all three answer 403 to an account holding
// every role — measured, not deduced. `juser/user/delete` and `jtranslate/phrase/delete`
// have their own controllers and forms. App\Sion\EntityDelete records the detail.
//
// Declared **above** the batch-5 show block, like the edit routes: `/{sw_id}/{slug}` would
// otherwise swallow `/{sw_id}/delete`. `delete` is in App\Sion\ReservedVerbs so the slug
// constraint refuses it too, which makes this belt and braces rather than the only defence —
// and on a destructive page that is the right way round.
/**
 * @param array<string, string> $requirements
 */
$delete = static function (
    string $name,
    string $path,
    string $entity,
    string $idParam,
    string $domain,
    ?string $idKind = null,
    array $requirements = []
) use (
    $ported,
    $textDomain
): void {
    $ported(
        $name,
        $path,
        EntityDeleteController::class,
        RouteAccess::guardedBy('route/' . $name),
        $textDomain($domain) + [
            EntityDeleteController::ENTITY   => $entity,
            EntityDeleteController::ID_PARAM => $idParam,
        ] + (null === $idKind ? [] : [EntityDeleteController::ID_KIND => $idKind]),
        $requirements
    );
};

// The four identified by a site-wide identifier. Their text domain is the module the
// laminas controller lives in, which is what decides where `Delete <entity>` is looked
// up — `Delete association` is a `Schoenstatt` phrase, `Delete publication` a `Books` one,
// and all seven rows already exist in trans_phrases from the laminas rendering.
//
// `association-delete` is first because it is the one that was unreachable until
// 2026-08-14: its laminas constraint asked for four digits where an identifier has six, so
// the show route answered it on both front controllers. Deriving the constraint from
// SiteWideIdentifier::pattern() here is what stops that recurring.
$delete(
    'association-delete',
    '/{sw_id}/delete',
    'association',
    'sw_id',
    'Schoenstatt',
    SchoenstattLinkIdentifier::ENTITY_ASSOCIATION,
    ['sw_id' => SiteWideIdentifier::pattern(SchoenstattLinkIdentifier::ENTITY_ASSOCIATION)]
);

// The only one of the seven with a **per-row** check behind the route guard: `publication`
// declares `acl_resource_id_field => resourceId` with `acl_delete_permission => delete`, so
// `pub_moderator` reaching this page is necessary and not sufficient. The other six declare
// no resource id field, and for them the guard is the whole gate.
$delete(
    'publication-delete',
    '/{sw_id}/delete',
    'publication',
    'sw_id',
    'Books',
    SchoenstattLinkIdentifier::ENTITY_PUBLICATION,
    ['sw_id' => SiteWideIdentifier::pattern(SchoenstattLinkIdentifier::ENTITY_PUBLICATION)]
);

// ---------------------------------------------------------------------------
// Batch 16, ported 2026-09-08: the last two per-row publication actions, both of them
// "make a new row from this one" and both behind a two-element confirmation form. Same
// controller, one method each — see App\Controller\PublicationDuplicateController.
//
// Both reserve their verb in App\Sion\ReservedVerbs, so the `publication` show route
// below cannot swallow them whatever the declaration order; declared above it anyway,
// because that is where the reader expects the more specific route and because the
// other verbs on this identifier (`edit`, `delete`) are.
//
// `publication-create-new-edition` **wrote on a plain GET** on laminas until this port.
// The laminas action confirms now too (same day), so the rollback path carries the fix.
$ported(
    'publication-copy-to-main-corpus',
    '/{sw_id}/copy-to-main-corpus',
    [PublicationDuplicateController::class, 'copyToMainCorpus'],
    RouteAccess::guardedBy('route/publication-copy-to-main-corpus'),
    $textDomain('Books'),
    ['sw_id' => SiteWideIdentifier::pattern(SchoenstattLinkIdentifier::ENTITY_PUBLICATION)]
);
$ported(
    'publication-create-new-edition',
    '/{sw_id}/create-new-edition',
    [PublicationDuplicateController::class, 'createNewEdition'],
    RouteAccess::guardedBy('route/publication-create-new-edition'),
    $textDomain('Books'),
    ['sw_id' => SiteWideIdentifier::pattern(SchoenstattLinkIdentifier::ENTITY_PUBLICATION)]
);

// `text` is the entity whose delete was broken in a way no status code showed: its
// `delete_action_redirect_route` was `text-delete` — this very route, which needs an `sw_id`
// — so redirectAfterDelete() asked the router to assemble it with no parameters and got
// `Missing parameter "sw_id"`. Every exit threw, the successful one included, *after* the
// row was deleted. Corrected to `texts` in the same change as this port.
$delete(
    'text-delete',
    '/{sw_id}/delete',
    'text',
    'sw_id',
    'Books',
    SchoenstattLinkIdentifier::ENTITY_TEXT,
    ['sw_id' => SiteWideIdentifier::pattern(SchoenstattLinkIdentifier::ENTITY_TEXT)]
);

$delete(
    'composition-delete',
    '/{sw_id}/delete',
    'composition',
    'sw_id',
    'Books',
    SchoenstattLinkIdentifier::ENTITY_COMPOSITION,
    ['sw_id' => SiteWideIdentifier::pattern(SchoenstattLinkIdentifier::ENTITY_COMPOSITION)]
);

// The three with a numeric id, each the target of a delete button or modal on the edit form
// ported in batch 7. All three are guarded `sch_general_moderator`, a narrower permission
// than the `sch_moderator` that reached the edit page — which is why those templates gate
// the button on the delete route's own resource rather than on their own.
//
// The constraints are laminas': `[0-9]{1,5}` for all three. Note `assignments/assignment/edit`
// declares `{1,6}`, one digit wider than its laminas twin — a pre-existing drift that is
// harmless (a 6-digit id reaches the controller and is answered "not found" instead of 404)
// and deliberately not copied here.
$delete('persons/person/delete', '/persons/{person_id}/delete', 'person', 'person_id', 'Schoenstatt', null, [
    'person_id' => '[0-9]{1,5}',
]);

$delete(
    'assignments/assignment/delete',
    '/assignments/{assignment_id}/delete',
    'assignment',
    'assignment_id',
    'Schoenstatt',
    null,
    ['assignment_id' => '[0-9]{1,5}']
);

$delete('roles/role/delete', '/roles/{role_id}/delete', 'role', 'role_id', 'Schoenstatt', null, [
    'role_id' => '[0-9]{1,5}',
]);

// ---------------------------------------------------------------------------
// Batch 5, ported 2026-08-12: the reading surface. The four entity show pages
// plus the comment route three of them need.
// ---------------------------------------------------------------------------
//
// **All four share one path shape**, `/{sw_id}[/{slug}]`, and are told apart by the
// identifier regex alone — `SL1…A` is an association, `SL2…L` a publication, `SL4…T` a
// text, `SL5…C` a composition. That is the laminas router's arrangement too, and it is
// why App\Sion\SiteWideIdentifier::pattern() exists: a constraint written by hand here
// would eventually disagree with Schoenstatt\Validator\SchoenstattLinkIdentifier, and
// the failure would be one entity type falling through to `legacy` while three do not.
//
// The `slug` is optional. It is decoration as far as these four controllers go: every
// one of them resolves the row from `sw_id` and ignores the slug, exactly as the laminas
// actions do, so a stale slug still reaches the right page.
//
// **It is not decoration as far as routing goes, and this file got that wrong.** Until
// 2026-08-13 the constraint was the laminas route's own `[a-z0-9-]{1,200}` and the
// paragraph here read: "`edit` is declared above this block — and the slug constraint
// would refuse `edit` anyway since the laminas pattern excludes nothing of the sort."
// The two halves of that sentence contradict each other and the second is false —
// `[a-z0-9-]{1,200}` matches `edit` — so the *only* thing protecting `association-edit`
// was its declaration order. Everything else of that shape was swallowed: measured on
// production's front controller, `/en/SL500001C/edit` answered 200 with the composition
// **show page** from route `composition.locale`, and the nine laminas routes below were
// unreachable with their guards never running.
//
//     composition-edit    composition-delete   text-edit             text-delete
//     publication-edit    publication-delete   publication-upload-cover
//     publication-create-new-edition           publication-copy-to-main-corpus
//
// `association-delete` looked like a tenth and was not: its own laminas constraint asked
// for four digits where an identifier has six, so it answered the association show page
// on *both* front controllers. Repaired 2026-08-14, which makes it an ordinary member of
// the list above rather than an exception to it. See App\Sion\ReservedVerbs.
//
// App\Sion\ReservedVerbs is the fix and carries the full account, including why laminas
// resolves the same ambiguity the other way (its PriorityList yields the last-registered
// route first; Symfony's UrlMatcher takes the first). Declaration order still matters
// and is still relied on — `association-edit` is still above this block — but it is no
// longer the only thing standing between a moderator and the edit form.
$slug = ['slug' => ReservedVerbs::slugPattern()];

// The literature browse surface. Declared **above** the entity show routes because
// `/literature/search` and `/literature/{inLanguage}` are two-segment paths that the
// publication-old redirect below must not swallow, and because `/literature` itself is
// a literal that nothing else claims. Order within the three is what matters: `search`
// is a literal and has to beat `{inLanguage}`, and `{inLanguage}` is constrained to two
// letters so `/literature/150-preguntas-sobre-schoenstatt` (ported earlier, and declared
// above this block) and `/literature/{publication_id}` both survive.
$ported(
    'publications',
    '/literature',
    [LiteratureController::class, 'home'],
    RouteAccess::guardedBy('route/publications'),
    $textDomain('Books')
);
$ported(
    'publications/search',
    '/literature/search',
    [LiteratureController::class, 'search'],
    RouteAccess::guardedBy('route/publications/search'),
    //no NAV_ROUTE: laminas marks nothing active in the navbar on the search page, and
    //declaring one lit up "Literature" where the original leaves it plain
    $textDomain('Books')
);
// Batch 16, 2026-09-08: the two moderator listings. Literals, six and thirteen letters
// long, so `{inLanguage}`'s two-letter constraint already keeps them apart — declared
// before it all the same, with `search`, so that the three literals under /literature
// read together. `publications/export` is an HTML table of the whole corpus, not a
// file: docs/laminas-exit.md said "spreadsheet" until this port, and that was inferred from
// the name. See App\Controller\PublicationReportsController.
$ported(
    'publications/export',
    '/literature/export',
    [PublicationReportsController::class, 'export'],
    RouteAccess::guardedBy('route/publications/export'),
    $textDomain('Books')
);
$ported(
    'publications/prime-authors',
    '/literature/prime-authors',
    [PublicationReportsController::class, 'primeAuthors'],
    RouteAccess::guardedBy('route/publications/prime-authors'),
    $textDomain('Books')
);
$ported(
    'publications/index',
    '/literature/{inLanguage}',
    [LiteratureController::class, 'index'],
    RouteAccess::guardedBy('route/publications/index'),
    $textDomain('Books') + [SiteChrome::NAV_ROUTE => 'publications'],
    //the laminas constraint exactly: two letters, which is what keeps `/literature/1`
    //and `/literature/150-preguntas-sobre-schoenstatt` out of this route
    ['inLanguage' => '[a-z]{2}']
);

// The three pre-2020 redirects, all 301s. `/literature/{publication_id}` is declared
// after the two-letter language route above and takes only digits, so the two cannot
// collide; the association pair sits under the old `/associations` prefix, below the
// `associations` index itself.
$ported(
    'publications/publication-old',
    '/literature/{publication_id}',
    [SendToNewUrlController::class, 'publicationById'],
    RouteAccess::guardedBy('route/publications/publication-old'),
    $textDomain('Books'),
    ['publication_id' => '[0-9]{1,5}']
);
$ported(
    'associations/association',
    '/associations/{sw_id}',
    [SendToNewUrlController::class, 'associationBySwId'],
    RouteAccess::guardedBy('route/associations/association'),
    $textDomain('Schoenstatt'),
    //`SL1[0-9]{4,5}A` is the laminas constraint, which is *looser* than the association
    //identifier regex — five or six digits rather than five. Reproduced as written: a
    //six-digit id that no longer resolves ends at the not-found redirect, which is what
    //it does today.
    ['sw_id' => 'SL1[0-9]{4,5}A']
);
$ported(
    'associations/old-association',
    '/associations/{association_id}',
    [SendToNewUrlController::class, 'associationById'],
    RouteAccess::guardedBy('route/associations/old-association'),
    $textDomain('Schoenstatt'),
    ['association_id' => '[0-9]{1,5}']
);

// The page behind every shrine on the map. Guarded `['guest', 'sch_basic', 'sch_user',
// 'user']`, i.e. public — but the controller carries a second rule the ACL cannot see:
// an anonymous visitor may see shrines and wayside shrines and is redirected to
// `welcome` for every other kind. See App\Controller\AssociationController.
$ported(
    'association',
    '/{sw_id}/{slug}',
    AssociationController::class,
    RouteAccess::guardedBy('route/association'),
    $textDomain('Schoenstatt') + ['slug' => null],
    ['sw_id' => SiteWideIdentifier::pattern(SchoenstattLinkIdentifier::ENTITY_ASSOCIATION)] + $slug
);

// An individual song. Public, and the first of the three pages that render a comment
// list and a CommentForm — the thing docs/laminas-exit.md recorded this route as blocked on.
$ported(
    'composition',
    '/{sw_id}/{slug}',
    CompositionController::class,
    RouteAccess::guardedBy('route/composition'),
    //**No SiteChrome::NAV_ROUTE.** Lighting up "Music" for a song reads as obviously
    //right and is wrong: the laminas navigation marks nothing active on any of these
    //show pages, and adding it put a stray ` class="active"` into the navbar that the
    //baseline diff caught on exactly 15 bytes.
    $textDomain('Books') + ['slug' => null],
    ['sw_id' => SiteWideIdentifier::pattern(SchoenstattLinkIdentifier::ENTITY_COMPOSITION)] + $slug
);

// A document from the Kentenich corpus, and **the batch's restricted show page**:
// `route/text` is guarded `texts_user`, so this is where all three access outcomes are
// exercised on a page that also has a body worth comparing.
$ported(
    'text',
    '/{sw_id}/{slug}',
    TextController::class,
    RouteAccess::guardedBy('route/text'),
    $textDomain('Books') + ['slug' => null],
    ['sw_id' => SiteWideIdentifier::pattern(SchoenstattLinkIdentifier::ENTITY_TEXT)] + $slug
);

// The bibliographic page. Public at the route level, and the only entity in this batch
// whose *rows* are individually gated — `publication` declares
// `acl_resource_id_field => resourceId` plus `acl_show_permission => show`, so
// App\Sion\EntityShow decides per publication whether this visitor may see it.
$ported(
    'publication',
    '/{sw_id}/{slug}',
    PublicationController::class,
    RouteAccess::guardedBy('route/publication'),
    //no NAV_ROUTE, for the reason given on `composition` above
    $textDomain('Books') + ['slug' => null],
    ['sw_id' => SiteWideIdentifier::pattern(SchoenstattLinkIdentifier::ENTITY_PUBLICATION)] + $slug
);

// Leaving a comment. **POST only**, and that is a reproduction rather than a narrowing:
// a GET reaches a view whose template does not exist and is a 500 today, measured. A GET
// does not become a 405 either — the catch-all below matches it, so it bridges to laminas
// and stays exactly as broken as it was. See App\Controller\CommentCreateController.
$commentIdentifiers = [
    'entity'    => '[a-zA-Z_-]{1,25}',
    'entity_id' => '[0-9]{1,5}',
    'kind'      => '(comment|review|rating)',
];
//**One RouteAccess instance shared by both twins**, not two equal ones.
//test/Integration/SymfonyRouteAuthorizationTest asserts object identity, and it is right
//to: these are one page reached two ways, and two declarations are two things to keep in
//step. $ported() does this for every other route by construction; this pair is declared by
//hand only because it needs a POST method constraint, so the sharing has to be explicit.
$commentAccess   = RouteAccess::guardedBy('route/comments/create');
$commentDefaults = [
    '_controller'                           => CommentCreateController::class,
    RouteAccess::ATTRIBUTE                  => $commentAccess,
    LaminasExtension::TEXT_DOMAIN_ATTRIBUTE => 'SionModel',
    //the laminas route's own default, and what makes the `kind` segment optional
    'kind'                                  => 'comment',
];
$routes->add('comments/create', new Route(
    '/comments/create/{entity}/{entity_id}/{kind}',
    $commentDefaults,
    $commentIdentifiers,
    [],
    '',
    [],
    ['POST']
));
$routes->add('comments/create.locale', new Route(
    '/{_locale}/comments/create/{entity}/{entity_id}/{kind}',
    $commentDefaults,
    $commentIdentifiers + ['_locale' => $locales],
    [],
    '',
    [],
    ['POST']
));

// ---------------------------------------------------------------------------
// Batch 6, ported 2026-08-13: the contact/search surface. The pages the movement's
// own members use to find each other, plus the Kentenich text search.
// ---------------------------------------------------------------------------
//
// Every route here is a GET whose input is the query string, which is what let them
// move ahead of the create/edit forms: the static-adapter obstacle docs/laminas-exit.md
// recorded belonged to `CreateRoleForm`, `EditUserForm`, `DeleteUserForm` and
// `EditPhraseForm` through a `NoRecordExists` validator, and none of these four forms
// has one. (Removed 2026-08-14; the four take the adapter as a constructor argument.)
// No CSRF token either — a GET form carries none on laminas.
//
// Three different guard shapes on purpose, which is most of why these belong in one
// batch: `assignments/search` admits sch_basic and sch_user (most signed-in members),
// `persons` admits sch_moderator, and `texts` admits texts_user — a role granted to
// Schoenstatt fathers by email match and to nobody else.

// The destination of the navbar search box on every page of the site, for anyone
// holding sch_basic or sch_user — see App\View\SiteChrome::searchBox(). Until this
// moved, every search a signed-in member ran from an already-ported page went
// through LegacyBridge back into laminas, which makes it the highest-traffic route
// in the batch by a wide margin.
//
// **Literal paths, and that is load-bearing.** `/assignments/{assignment_id}` is
// *not* ported (see App\Controller\AssignmentSearchController for why it is not a
// page at all), so it has to keep falling through to `legacy`. A segment route here
// would swallow both of these and 404 them.
$ported(
    'assignments/search',
    '/assignments/search',
    [AssignmentSearchController::class, 'search'],
    RouteAccess::guardedBy('route/assignments/search'),
    $textDomain('Schoenstatt')
);
$ported(
    'assignments/advanced-search',
    '/assignments/advanced-search',
    [AssignmentSearchController::class, 'advancedSearch'],
    RouteAccess::guardedBy('route/assignments/advanced-search'),
    $textDomain('Schoenstatt')
);

// The movement's leadership. Route name `schoenstatt`, path /movement — they disagree
// on the laminas side too, and the *name* is what has to be kept: it is how the layout
// recognises the current navigation item, and this route **is** in the navigation
// config, unlike most of this batch.
$ported(
    'schoenstatt',
    '/movement',
    MovementController::class,
    RouteAccess::guardedBy('route/schoenstatt'),
    $textDomain('Schoenstatt')
);

// Finding a person, and one person's page. Both guarded `sch_moderator`.
//
// `persons` and `persons/search` are two laminas routes over one action, so one
// controller serves both and reads which it is from the request — the unprefixed-form
// redirect has to point back at the URL the visitor asked for.
//
// **The port repairs these two pages rather than reproducing them.** The laminas
// rendering is an "Add person" link and nothing else, for every query: the template
// reads `$this->entities` where the controller passes `persons`, and never renders the
// form at all. See App\Controller\PersonsController.
$ported(
    'persons',
    '/persons',
    PersonsController::class,
    RouteAccess::guardedBy('route/persons'),
    $textDomain('Schoenstatt')
);
$ported(
    'persons/search',
    '/persons/search',
    PersonsController::class,
    RouteAccess::guardedBy('route/persons/search'),
    $textDomain('Schoenstatt')
);
// Declared **after** `/persons/search`, which a numeric constraint could not swallow
// anyway, but the ordering is this file's convention. `/persons/create` and
// `/persons/{id}/edit` keep falling through to `legacy` because the id is digits only
// and this route has exactly two segments.
$ported(
    'persons/person',
    '/persons/{person_id}',
    PersonController::class,
    RouteAccess::guardedBy('route/persons/person'),
    $textDomain('Schoenstatt'),
    //the laminas constraint exactly
    ['person_id' => '[0-9]{1,5}']
);

// The Kentenich corpus search. Guarded `texts_user`, the sharpest guard in this batch.
//
// The path is `/texts` and the laminas route's action is `searchAction` — its
// `indexAction` is a different query no route reaches. `/texts/create` and
// `/texts/import` keep falling through to `legacy`, since this is a literal path.
$ported(
    'texts',
    '/texts',
    TextsController::class,
    RouteAccess::guardedBy('route/texts'),
    $textDomain('Books')
);

// ---------------------------------------------------------------------------
// The v3 API, added 2026-08-09: the read/write surface automated agents use to
// augment and update the shrine database.
// ---------------------------------------------------------------------------
//
// v1 and v2 stay exactly as they are — the map consumers and the mobile apps use
// them and neither can write. They could not be extended: both implement only
// getList()/get() over AbstractRestfulController, so every write verb inherits a 405;
// neither authenticates (authenticateApiKey() exists with every call site commented
// out) so neither has an identity to attribute a change to; and their schema.org
// projection does not map back onto the columns an edit writes. See
// App\Controller\Api\AssociationsV3Controller.
//
// **No locale twins.** $ported() is not used here on purpose: these endpoints emit no
// localized text, an agent has no Accept-Language preference worth honouring, and a
// second URL for every endpoint is a second thing for an agent author to get wrong.
// /api/v1 and /api/v2 answer under a prefix only because SlmLocale strips it.
//
// **Declared open, gated by bearer token.** This is the /_health and maintenance-key
// pattern rather than an oversight: there is no laminas twin, so there is no guard
// entry for RouteAccess::guardedBy() to name, and BjyAuthorize's identity comes from
// the laminas session, which a token-authenticated agent does not have. The real gate
// is App\Api\BotIdentity — a valid JWT whose account holds `sch_api_bot`, a role
// nothing else on this site names. tools/acl-table.php lists these as open, which is
// accurate: the ACL genuinely does not protect them, and something else does.
// A borrower's own checkouts, reached only by the link in an overdue notice.
//
// openToEveryone is accurate rather than a loophole: the reader is not signed in and
// has no account, so the ACL has nothing to say. The gate is the `?t=` token, which
// resolves to exactly one person at one library — see Books\Model\BorrowerTokenTable
// for why an account was the wrong answer here (there is no user-to-person link in
// this database, and every account inherits the is_default lib_user role).
//
// Note the URL carries no person id and must never carry one. The moment a request
// parameter can name a person, the token stops being a grant over one person's books.
// GET renders, POST renews and redirects back to GET; both need the token.
$borrowerBooks = RouteAccess::openToEveryone(
    'shadows no laminas route, and its reader has no session for BjyAuthorize to read an identity '
    . 'from. The gate is the emailed token resolved by Books\Model\BorrowerTokenTable, which '
    . 'authorises one person at one library and nothing else'
);
$routes->add('library/my-books', new Route('/library/my-books', [
    '_controller'                           => BorrowerCheckoutsController::class,
    RouteAccess::ATTRIBUTE                  => $borrowerBooks,
    LaminasExtension::TEXT_DOMAIN_ATTRIBUTE => 'Books',
], [], [], '', [], ['GET', 'POST']));

$apiV3 = RouteAccess::openToEveryone(
    'shadows no laminas route, so there is no guard entry to consult, and BjyAuthorize reads its '
    . 'identity from a session an agent does not have. The gate is App\Api\BotIdentity: a bearer JWT '
    . 'whose account holds the sch_api_bot role. /api/v3/schema is genuinely public — it describes '
    . 'the field contract and exposes no data'
);

$routes->add('api-v3/schema', new Route('/api/v3/schema', [
    '_controller'          => [ApiSchemaController::class, 'index'],
    RouteAccess::ATTRIBUTE => $apiV3,
], [], [], '', [], ['GET']));

$routes->add('api-v3/schema-entity', new Route('/api/v3/schema/{entity}', [
    '_controller'          => [ApiSchemaController::class, 'entity'],
    RouteAccess::ATTRIBUTE => $apiV3,
], ['entity' => '[a-z][a-z-]*'], [], '', [], ['GET']));

$routes->add('api-v3/associations', new Route('/api/v3/associations', [
    '_controller'          => [AssociationsV3Controller::class, 'index'],
    RouteAccess::ATTRIBUTE => $apiV3,
], [], [], '', [], ['GET']));

// Method-separated rather than one route dispatching internally, so that a PUT or a
// DELETE gets Symfony's 405 with a correct Allow header instead of reaching a
// controller that has to invent one.
$associationIdentifier = ['sw_id' => trim(SchoenstattLinkIdentifier::ENTITY_REGEXS[
    SchoenstattLinkIdentifier::ENTITY_ASSOCIATION
], '/^$')];

$routes->add('api-v3/association', new Route('/api/v3/associations/{sw_id}', [
    '_controller'          => [AssociationsV3Controller::class, 'show'],
    RouteAccess::ATTRIBUTE => $apiV3,
], $associationIdentifier, [], '', [], ['GET']));

$routes->add('api-v3/association-patch', new Route('/api/v3/associations/{sw_id}', [
    '_controller'          => [AssociationsV3Controller::class, 'patch'],
    RouteAccess::ATTRIBUTE => $apiV3,
], $associationIdentifier, [], '', [], ['PATCH']));

// The translation phrases. Same pattern, different role: `sch_api_translator` rather
// than `sch_api_bot`, because a translation agent rewrites every string the site
// renders in four languages and a shrine agent rewrites the shrine database, and
// neither is a reason to be able to do the other. Before this endpoint existed
// App\Api\BotIdentity named one role in a constant, so the question could not be
// asked — see database/db6.8.sql.
$apiV3Phrases = RouteAccess::openToEveryone(
    'shadows no laminas route — the translation GUI at route/jtranslate is a different surface with a '
    . 'different audience — and BjyAuthorize reads its identity from a session an agent does not have. '
    . 'The gate is App\Api\BotIdentity: a bearer JWT whose account holds the sch_api_translator role'
);

$phraseIdentifier = ['phrase_id' => '[0-9]+'];

$routes->add('api-v3/phrases', new Route('/api/v3/phrases', [
    '_controller'          => [PhrasesV3Controller::class, 'index'],
    RouteAccess::ATTRIBUTE => $apiV3Phrases,
], [], [], '', [], ['GET']));

// The batch write, on the collection. A PATCH of many resources is not a PATCH of one,
// so it carries its own body shape and honours no If-Match; see the controller.
$routes->add('api-v3/phrases-patch', new Route('/api/v3/phrases', [
    '_controller'          => [PhrasesV3Controller::class, 'patchCollection'],
    RouteAccess::ATTRIBUTE => $apiV3Phrases,
], [], [], '', [], ['PATCH']));

$routes->add('api-v3/phrase', new Route('/api/v3/phrases/{phrase_id}', [
    '_controller'          => [PhrasesV3Controller::class, 'show'],
    RouteAccess::ATTRIBUTE => $apiV3Phrases,
], $phraseIdentifier, [], '', [], ['GET']));

$routes->add('api-v3/phrase-patch', new Route('/api/v3/phrases/{phrase_id}', [
    '_controller'          => [PhrasesV3Controller::class, 'patch'],
    RouteAccess::ATTRIBUTE => $apiV3Phrases,
], $phraseIdentifier, [], '', [], ['PATCH']));

// The append-only history of what writing to a phrase has destroyed. A subresource and
// not a field of the phrase: it is the answer to a question almost nobody is asking, and
// a usually-empty list on every item of every page is a field callers learn to skip.
// Same role as the rest of the resource — a translator who may overwrite a translation
// is exactly who needs to see what an overwrite cost.
//
// Above `api-v3/phrase`, which it cannot actually shadow (`{phrase_id}` is constrained
// to digits and this path has a second segment), but the ordering is the file's
// convention and the next more-specific phrase route will need it.
$routes->add('api-v3/phrase-history', new Route('/api/v3/phrases/{phrase_id}/history', [
    '_controller'          => [PhrasesV3Controller::class, 'history'],
    RouteAccess::ATTRIBUTE => $apiV3Phrases,
], $phraseIdentifier, [], '', [], ['GET']));

// Taking a phrase off the translator's worklist, and putting it back. Subresources with
// their own verb rather than keys on the PATCH body, and that is the same decision the
// `_retract` key records from the other direction: a destructive-looking operation should
// not be reachable by a serializer emitting a default value into a body that was about
// something else. `POST /…/retire` cannot be a typo in a write.
//
// They are *not* variations on `_retract`, and the naming is deliberate about it: a
// retraction deletes a translation, a retirement moves a phrase off a worklist and destroys
// nothing. See the controller; the schema publishes the distinction under `retirement`.
$routes->add('api-v3/phrase-retire', new Route('/api/v3/phrases/{phrase_id}/retire', [
    '_controller'          => [PhrasesV3Controller::class, 'retire'],
    RouteAccess::ATTRIBUTE => $apiV3Phrases,
], $phraseIdentifier, [], '', [], ['POST']));

$routes->add('api-v3/phrase-unretire', new Route('/api/v3/phrases/{phrase_id}/unretire', [
    '_controller'          => [PhrasesV3Controller::class, 'unretire'],
    RouteAccess::ATTRIBUTE => $apiV3Phrases,
], $phraseIdentifier, [], '', [], ['POST']));

// Any other verb on a v3 path. Below the real routes so it only ever catches what
// they refused, and above `legacy` so a PUT gets a 405 with an Allow header rather
// than laminas' 302 to the sign-in page.
$routes->add('api-v3/associations-method', new Route('/api/v3/associations', [
    '_controller'                       => MethodNotAllowedController::class,
    MethodNotAllowedController::ALLOWED => ['GET'],
    RouteAccess::ATTRIBUTE              => $apiV3,
]));
$routes->add('api-v3/association-method', new Route('/api/v3/associations/{sw_id}', [
    '_controller'                       => MethodNotAllowedController::class,
    MethodNotAllowedController::ALLOWED => ['GET', 'PATCH'],
    RouteAccess::ATTRIBUTE              => $apiV3,
], $associationIdentifier));
$routes->add('api-v3/schema-method', new Route('/api/v3/schema', [
    '_controller'                       => MethodNotAllowedController::class,
    MethodNotAllowedController::ALLOWED => ['GET'],
    RouteAccess::ATTRIBUTE              => $apiV3,
]));
$routes->add('api-v3/schema-entity-method', new Route('/api/v3/schema/{entity}', [
    '_controller'                       => MethodNotAllowedController::class,
    MethodNotAllowedController::ALLOWED => ['GET'],
    RouteAccess::ATTRIBUTE              => $apiV3,
], ['entity' => '[a-z][a-z-]*']));
$routes->add('api-v3/phrases-method', new Route('/api/v3/phrases', [
    '_controller'                       => MethodNotAllowedController::class,
    MethodNotAllowedController::ALLOWED => ['GET', 'PATCH'],
    RouteAccess::ATTRIBUTE              => $apiV3Phrases,
]));
$routes->add('api-v3/phrase-method', new Route('/api/v3/phrases/{phrase_id}', [
    '_controller'                       => MethodNotAllowedController::class,
    MethodNotAllowedController::ALLOWED => ['GET', 'PATCH'],
    RouteAccess::ATTRIBUTE              => $apiV3Phrases,
], $phraseIdentifier));
$routes->add('api-v3/phrase-history-method', new Route('/api/v3/phrases/{phrase_id}/history', [
    '_controller'                       => MethodNotAllowedController::class,
    MethodNotAllowedController::ALLOWED => ['GET'],
    RouteAccess::ATTRIBUTE              => $apiV3Phrases,
], $phraseIdentifier));
// A GET of a retirement path is the shape a caller lands on when it expects retirement to be
// a *state* it can read. It is not — `meta.retiredOn` on the phrase is — so the 405 names the
// verb that works rather than 404ing as if the path were wrong.
$routes->add('api-v3/phrase-retire-method', new Route('/api/v3/phrases/{phrase_id}/retire', [
    '_controller'                       => MethodNotAllowedController::class,
    MethodNotAllowedController::ALLOWED => ['POST'],
    RouteAccess::ATTRIBUTE              => $apiV3Phrases,
], $phraseIdentifier));
$routes->add('api-v3/phrase-unretire-method', new Route('/api/v3/phrases/{phrase_id}/unretire', [
    '_controller'                       => MethodNotAllowedController::class,
    MethodNotAllowedController::ALLOWED => ['POST'],
    RouteAccess::ATTRIBUTE              => $apiV3Phrases,
], $phraseIdentifier));

// ---------------------------------------------------------------------------
// Batch 11b — the library circulation surface, ported 2026-08-18.
//
// Twenty-three routes across five laminas controllers. Three more were **retired
// rather than ported** after an audit, all three broken or empty in production:
// `/borrowers` answered 500 (an `index` action that does not exist, rendering a
// template that does not exist), `/library-imports/{id}/cancel` answered 404 (no
// `cancelAction` anywhere in the chain), and `/libraries/{id}/batch-operations`
// rendered a blank page (its .phtml was the four characters `<?php`). See
// docs/laminas-exit.md for the probe that measured each one.
//
// Every route here is library-scoped, and the per-library ACL is the real
// authorization: the route guards all name `lib_user`, which is `is_default = 1`
// and therefore means nothing more than "signed in". `App\Books\LibraryPage` holds
// the row lookup and the check; a `guardedBy()` on the route alone would be a
// route that admits everybody.
// ---------------------------------------------------------------------------

$ported(
    'libraries/library/collections',
    '/libraries/{library_id}/collections',
    LibraryCollectionsController::class,
    RouteAccess::guardedBy('route/libraries/library/collections'),
    $textDomain('Books'),
    ['library_id' => '[0-9]{1,5}']
);

// The four read-only library pages behind one controller — see App\Controller\
// LibraryPageController for why they share, and for the one authorization difference
// this batch introduces (`data-problems` gains the per-library check its siblings make).
$libraryPage = static function (
    string $page,
    string $suffix,
    ?LocalePrefix $localePrefix = null
) use (
    $ported,
    $textDomain
): void {
    $ported(
        'libraries/library/' . $page,
        '/libraries/{library_id}/' . $suffix,
        LibraryPageController::class,
        RouteAccess::guardedBy('route/libraries/library/' . $page),
        $textDomain('Books') + [LibraryPageController::PAGE => $page],
        ['library_id' => '[0-9]{1,5}'],
        $localePrefix
    );
};
$libraryPage('label-management', 'label-management');
$libraryPage('book-list', 'book-list');
//The one of the four that is not a page. Its three siblings take SlmLocale's hop; this
//one is fetched by the library admin page's JavaScript, and a 302 in front of an XHR is
//a redirect the caller has to follow for no benefit.
$libraryPage('book-list-json', 'book-list-json', LocalePrefix::servedHere(
    'fetched by the library admin page\'s JavaScript and answered as JSON, so the locale segment '
    . 'names nothing about the response and the hop is a round trip the caller pays for nothing'
));
$libraryPage('data-problems', 'data-problems');

// The two bulk operations that are a form, a table call and a redirect. No method
// constraint, matching the laminas routes: both render on GET and write on POST.
$libraryForm = static function (string $page) use ($ported, $textDomain): void {
    $ported(
        'libraries/library/' . $page,
        '/libraries/{library_id}/' . $page,
        LibraryFormController::class,
        RouteAccess::guardedBy('route/libraries/library/' . $page),
        $textDomain('Books') + [LibraryFormController::FORM => $page],
        ['library_id' => '[0-9]{1,5}']
    );
};
// The lending form. Its permission is `checkout`, not `administrate`, and four of the
// six libraries grant it to `guest` on purpose — docs/libraries.md.
$ported(
    'libraries/library/checkout',
    '/libraries/{library_id}/checkout',
    LibraryCheckoutController::class,
    RouteAccess::guardedBy('route/libraries/library/checkout'),
    $textDomain('Books'),
    ['library_id' => '[0-9]{1,5}']
);

$libraryForm('checkin');
$ported(
    'libraries/library/mass-checkout',
    '/libraries/{library_id}/mass-checkout',
    LibraryMassCheckoutController::class,
    RouteAccess::guardedBy('route/libraries/library/mass-checkout'),
    $textDomain('Books'),
    ['library_id' => '[0-9]{1,5}']
);
$libraryForm('inactivate-books');

// The whole spreadsheet-import surface, five routes. The last of them,
// `library-imports/library-import/edit`, is the one batch 11b left behind: it was not an
// edit form but the import engine, three hundred lines inside a laminas controller that
// created, updated and inactivated books in bulk. The engine is now
// App\Books\Import\LibraryImporter and the page is
// App\Controller\LibraryImportConfigureController.
//
// `library-imports/library/template` is new and has no laminas twin to fall back to — it
// is a generated .xlsx, and there is no .phtml rendering of a spreadsheet. Its laminas
// route entry exists so `laminas_path()` can assemble a link to it and for nothing else.
$ported(
    'library-imports/library',
    '/library-imports/library/{library_id}',
    LibraryImportsController::class,
    RouteAccess::guardedBy('route/library-imports/library'),
    $textDomain('Books'),
    ['library_id' => '[0-9]{1,5}']
);
$ported(
    'library-imports/library/create',
    '/library-imports/library/{library_id}/create',
    LibraryImportsController::class,
    RouteAccess::guardedBy('route/library-imports/library/create'),
    $textDomain('Books') + [LibraryImportsController::CREATE => true],
    ['library_id' => '[0-9]{1,5}']
);
$ported(
    'library-imports/library/template',
    '/library-imports/library/{library_id}/template',
    LibraryImportsController::class,
    RouteAccess::guardedBy('route/library-imports/library/template'),
    $textDomain('Books') + [LibraryImportsController::TEMPLATE => true],
    ['library_id' => '[0-9]{1,5}']
);
$ported(
    'library-imports/library-import',
    '/library-imports/{import_id}',
    LibraryImportsController::class,
    RouteAccess::guardedBy('route/library-imports/library-import'),
    $textDomain('Books') + [LibraryImportsController::DETAIL => true],
    ['import_id' => '[0-9]{1,5}']
);
// Configure, preview and run. The only route in the application whose POST can retire
// thousands of books, which is why App\Books\Import\RunImportForm carries a digest of
// the counts the operator was shown as well as a CSRF token.
$ported(
    'library-imports/library-import/edit',
    '/library-imports/{import_id}/edit',
    LibraryImportConfigureController::class,
    RouteAccess::guardedBy('route/library-imports/library-import/edit'),
    $textDomain('Books'),
    ['import_id' => '[0-9]{1,5}']
);

// The overdue-notice preview. Its guard admits everyone on purpose — the action itself
// requires `administrate` **or** a key in X-Api-Key, which a route guard cannot express.
$ported(
    'libraries/library/send-book-notices',
    '/libraries/{library_id}/send-book-notices',
    LibraryNoticesController::class,
    RouteAccess::guardedBy('route/libraries/library/send-book-notices'),
    $textDomain('Books'),
    ['library_id' => '[0-9]{1,5}']
);

// The sort diagnostic and the sweep it links to. **`refresh-sort` is the one route in
// this batch whose contract changes**: the laminas action rewrote every book's sort_text
// on a bare GET, with no token and no per-library check, behind a guard every account
// holds. Here GET renders a confirmation and POST does the work. See
// App\Books\RefreshSortForm.
$ported(
    'libraries/library/sort-debugging',
    '/libraries/{library_id}/sort-debugging',
    LibrarySortController::class,
    RouteAccess::guardedBy('route/libraries/library/sort-debugging'),
    $textDomain('Books'),
    ['library_id' => '[0-9]{1,5}']
);
$ported(
    'libraries/library/refresh-sort',
    '/libraries/{library_id}/refresh-sort',
    LibrarySortController::class,
    RouteAccess::guardedBy('route/libraries/library/refresh-sort'),
    $textDomain('Books') + [LibrarySortController::REFRESH => true],
    ['library_id' => '[0-9]{1,5}']
);

// Deleting a library, and everything in it. **The first route here with no laminas page
// behind it at all** — not "not ported yet", never written: `libraries/library/delete` has
// been in module.config.php since 2020 with no guard entry and with the `library` entity's
// `enable_delete_action` commented out, so both gates refused it independently. There is
// therefore nothing for tools/port-baseline.php to compare, and the laminas route is kept
// only so `laminas_path()` and the new guard entry can name it.
//
// It is also the only route on this surface guarded by `lib_administrator` rather than
// `lib_user`. App\Controller\LibraryDeleteController has the authorization reasoning —
// including why the per-row check it makes on top distinguishes nobody today — and
// App\Books\LibraryDelete has the cascade, which is the part that matters: SionModel's
// generic delete would leave 16,383 books pointing at a library that no longer exists.
//
// Declared above `libraries/library` for the same reason the edit and delete batches are
// declared above the show block. Belt and braces here rather than the only defence, since
// `library_id` is constrained to digits and `/libraries/4/delete` has a segment the bare
// route cannot absorb — but on a destructive route that is the right way round.
$ported(
    'libraries/library/delete',
    '/libraries/{library_id}/delete',
    LibraryDeleteController::class,
    RouteAccess::guardedBy('route/libraries/library/delete'),
    $textDomain('Books'),
    ['library_id' => '[0-9]{1,5}']
);

// The library's own page and its admin menu — one controller, because `adminAction()`
// calls `showAction()` and renders its output underneath the menu.
$ported(
    'libraries/library',
    '/libraries/{library_id}',
    LibraryController::class,
    RouteAccess::guardedBy('route/libraries/library'),
    //Lights up **Literature**, because Application\Module::onBootstrap() hangs one
    //navigation page per library underneath it and App\View\SiteChrome can only see the
    //static config. Measured against the laminas rendering: of the twenty-three pages in
    //this batch, this is the only one that marks a navbar item at all — the admin menu,
    //the checkout lists and the imports mark none.
    $textDomain('Books') + [SiteChrome::NAV_ROUTE => 'publications'],
    ['library_id' => '[0-9]{1,5}']
);
// One copy of one book. Its guard admits `guest`; the row-level gate is the `book`
// spec's aclResourceIdField, inside App\Sion\EntityShow.
$ported(
    'books/book',
    '/books/{book_id}',
    BookController::class,
    RouteAccess::guardedBy('route/books/book'),
    $textDomain('Books'),
    ['book_id' => '[0-9]{1,6}']
);

// One person's loans. The route guard is `lib_user` and therefore admits everyone
// signed in; the real gate is the per-checkout filter inside the controller.
$ported(
    'borrowers/borrower',
    '/borrowers/{person_id}',
    BorrowerController::class,
    RouteAccess::guardedBy('route/borrowers/borrower'),
    $textDomain('Books'),
    ['person_id' => '[0-9]{1,5}']
);

// The three checkout views. One controller; `subset` is the only difference, and it
// chooses the heading, the columns and which rows the query returns.
$checkouts = static function (string $name, string $path, string $subset) use ($ported, $textDomain): void {
    $ported(
        $name,
        $path,
        CheckoutsController::class,
        RouteAccess::guardedBy('route/' . $name),
        $textDomain('Books') + [CheckoutsController::SUBSET => $subset],
        ['library_id' => '[0-9]{1,5}']
    );
};
$checkouts('checkouts/library', '/checkouts/library/{library_id}', 'all');
$checkouts('checkouts/library/current', '/checkouts/library/{library_id}/current', 'current');
$checkouts('checkouts/library/overdue', '/checkouts/library/{library_id}/overdue', 'overdue');

$ported(
    'libraries/library/admin',
    '/libraries/{library_id}/admin',
    LibraryController::class,
    RouteAccess::guardedBy('route/libraries/library/admin'),
    $textDomain('Books') + [LibraryController::ADMIN => true],
    ['library_id' => '[0-9]{1,5}']
);

// ---------------------------------------------------------------------------
// Batch 12, ported 2026-08-21: the JUser user-administration surface — seven routes,
// five controllers, one shared `JUser\Page\UserAdmin`.
// ---------------------------------------------------------------------------
//
// Every one of them is `JUser\Controller\UsersController`'s own action rather than a
// shared SionModel one, which is why none of them came with batch 7 (edit), batch 8
// (delete) or batch 9 (create) and why there are five controllers here instead of a
// route default on one. `config/symfony/routes.php` said so in batch 9's preamble:
// "`juser/create` and `juser/create-role` do not use `createAction()` at all … so they
// belong with the `juser/*` bloc, not here."
//
// **All seven are guarded `administrator`, and that is the whole protection.** Unusual on
// this site: `lib_user`, `pub_user`, `sch_user` and `bib_user` are `is_default = 1`, so a
// guard naming one of them means "signed in" and the real check has to live in the page
// (see App\Books\LibraryPage). `administrator` is not a default role — 2 of 292 accounts
// hold it — so `route/juser…` really does gate these pages and no controller here carries
// a second check. Said out loud because the *absence* of one is what a reader coming from
// the library surface will notice.
//
// The four `zfcuser/*` sign-in routes are **batch 13**, declared at the end of this file.
//
// **Order.** The two literal create paths come first, and — unlike batch 9, where order
// genuinely did not matter — one of the two really does need to. `/users/roles/create` is
// three segments and so is `/users/{user_id}/api-tokens`; the `[0-9]{1,5}` constraint is
// what keeps `roles` out of `user_id`, so the constraint and not the order is doing the
// work. Declaring the literals first anyway, because a constraint is a thing someone can
// widen and a reader should not have to check one to know what `/users/roles/create` does.
// The eleven routes of the JUser surface are **declared by the module**, not here, since
// 2026-08-21. `module/JUser/config/symfony-routes.php` returns a closure that calls back
// into whatever a host uses to register a route, and the adapter below is this
// application's answer: `$ported()` plus the two things a module cannot know about us —
// the ACL resource its guard entry lives under, and the `JUser` text domain its templates
// translate in.
//
// **Reading the migration status of this file top to bottom still works**, which is the
// property worth protecting: the fragment is included *here*, in the position these routes
// used to occupy, so the order is unchanged and so is everything above and below.
//
// `RouteAccess::guardedBy('route/' . $name)` reconstructs exactly what the eleven explicit
// declarations said, because that is how BjyAuthorize\Guard\Route keys its entries — one
// guard entry per route name, and the same one both front controllers read. Which is why
// `$audience` is **not** consulted: this application's guard entries predate the port and
// are the authority; JUser declares the audience for a host that has none to reconstruct
// from. tools/acl-table.php is the oracle either way, and a `route/juser…` entry that
// stops matching shows up there rather than as a page that quietly works for more people.
//
// Two facts about these routes that used to be written out here and now live in the
// fragment, where they travel with the code that depends on them: the `[0-9]{1,5}`
// constraint is what keeps `roles` out of `user_id` on `/users/roles/create`, and
// `/users/{user_id}/delete` carries **no method constraint**, because adding one would turn
// a mistaken GET into a 405 where today it renders the confirmation.
//
// The sign-in half is where being wrong is not recoverable from a browser, and the rollback
// is unchanged by this: `JUser\Controller\LoginController` still exists and the laminas
// routes are still declared, so removing this include puts laminas back in charge of
// `/user/login` with no new code. The administration half has no such twin — its laminas
// controller was deleted when it was ported — so rolling *that* back is a deploy.
(require __DIR__ . '/../../module/JUser/config/symfony-routes.php')(
    static function (
        string $name,
        string $path,
        string|array $controller,
        RouteAudience $audience,
        array $defaults = [],
        array $requirements = []
    ) use (
        $ported,
        $textDomain
    ): void {
        $ported(
            $name,
            $path,
            $controller,
            RouteAccess::guardedBy('route/' . $name),
            $textDomain('JUser') + $defaults,
            $requirements
        );
    }
);

// **There is no `sign-in-no-cookies` route here, and there is no longer one in laminas
// either** — it was deleted on 2026-08-21, the day after this batch shipped.
//
// It had **no guard entry**, so BjyAuthorize's default deny made the URL unreachable from
// the day it was written; `docs/acl-baseline.json` listed it under "routes with no guard entry
// (nobody can reach these)" as a real endpoint for as long as that table existed. Batch 13
// wrote a Symfony declaration for it and withdrew it rather than commit a route that denies
// everyone, which `tools/acl-table.php` reports as a standing warning. The question that
// left open — should the page be public? — was answered by deleting the route instead:
// nothing links to it, no visitor has ever reached it, and the page it served is still
// served.
//
// The page is `module/JUser/templates/sign-in-no-cookies.html.twig`, rendered by
// `JUser\Page\CookieExplainer` for the two gated routes above. That is the only way it has
// ever been reached: `Application\View\GdprStrategy::onRoute()` swapped the *route match*
// for it at priority -5000, i.e. after the guard had already approved `zfcuser/login`, so
// the swapped-in page rendered without being authorized at all. That swap needs no route —
// it builds a RouteMatch by hand — which is why deleting the route cost the rollback path
// nothing.

// ---------------------------------------------------------------------------
// Batch 14, ported 2026-09-08: the translation-administration surface — three routes,
// three controllers, one `JTranslate\Page\PhraseAdmin`.
// ---------------------------------------------------------------------------
//
// **Declared by the module**, like JUser's eleven: `module/JTranslate/config/symfony-routes.php`
// returns a closure that calls back into `$ported()` here, so this file still reads as the
// migration status top to bottom while the paths, the controllers and the audience live with
// the code that serves them. What this application adds on the way through is what only it
// knows: the ACL resource each route's guard entry lives under, and the `JTranslate` text
// domain its templates translate in.
//
// The route names are the laminas ones (`jtranslate`, `jtranslate/phrase/edit`,
// `jtranslate/phrase/delete`) because three things already name them and none of them is
// this file: `App\Schoenstatt\AdminIndex` links to the listing by route name,
// `config/autoload/acl.global.php` keys the guard entries on them, and
// `docs/acl-baseline.json` is a committed snapshot of exactly those keys. Porting changed
// none of it, and the acl-table diff says so precisely: three rows move into the
// Symfony-served table, each **checking the same resource** its laminas guard named, with
// the same three effective roles (`translator`, `sch_general_moderator`, `sch_administrator`
// by inheritance). No rule, role or resource changed. That shape — new shadow rows, nothing
// else — is the check to repeat.
//
// **The laminas routes stay declared and their controller does not.** `JTranslateController`
// and its three `.phtml` were deleted with the switch, so rolling this back is a deploy and
// not a config change — the same trade batch 12 made for the user-administration half, and
// for the same reason: two copies of a page drift, and this one is small enough that the
// capsule plus `tools/port-baseline.php` cover it. What the routes are still for is
// `laminas_path()` and the guards; nothing dispatches them.
//
// `phrase_id` is `[0-9]+` here against the laminas route's `[0-9]{1,5}`, which is the one
// place the fragment deliberately does not reproduce what it replaces — see its docblock.
// The short version: ids are at 14,434 and climbing, and at 100,000 the old constraint stops
// matching a phrase whose pencil link the listing still renders.
//
// Ordering: all three paths are under `/admin/translations`, and the ported `/admin` above is
// a literal with no trailing-slash variant, so neither can reach the other. They sit here,
// next to the JUser fragment, because both are module-declared and reading them together is
// worth more than grouping by path.
(require __DIR__ . '/../../module/JTranslate/config/symfony-routes.php')(
    static function (
        string $name,
        string $path,
        string|array $controller,
        JTranslateAudience $audience,
        array $defaults = [],
        array $requirements = []
    ) use (
        $ported,
        $textDomain
    ): void {
        $ported(
            $name,
            $path,
            $controller,
            RouteAccess::guardedBy('route/' . $name),
            $textDomain('JTranslate') + $defaults,
            $requirements
        );
    }
);

// The pre-April-2020 identifier redirect, declared **last before the catch-all** because it
// is deliberately broad: `/{sw_id}[/{slug}]` for the eight-character old identifier format.
// Everything more specific — every ported show route, which requires six digits — has
// already been declared above, and its constraint (five digits, `[APLC]`) is disjoint from
// theirs by length, so it can share the `/{sw_id}/{slug}` shape without swallowing them.
// It shadows the laminas `redirect-pre-april-2020-sl-id` route and carries its guard.
// See App\Controller\PreApril2020RedirectController; Phase A of the laminas-mvc removal.
$ported(
    'redirect-pre-april-2020-sl-id',
    '/{sw_id}/{slug}',
    PreApril2020RedirectController::class,
    RouteAccess::guardedBy('route/redirect-pre-april-2020-sl-id'),
    //A text domain even though it renders nothing translatable — a bare 301 — because the
    //sibling redirects declare one and PortedRouteTranslationTest requires it of every HTML
    //route. `Application`, the module the laminas route lives in.
    $textDomain('Application') + ['slug' => null],
    ['sw_id' => trim(SchoenstattLinkIdentifier::GENERAL_OLD_REGEX, '/^$')]
);

// The JSON refusal for any /api/... path no real route above matched — Symfony-served since
// it was ported off LegacyBridge (Phase B prep of the laminas-mvc removal, 2026-09-08). It
// shadows the laminas `api-route-not-found` route and, like it, is declared last so it never
// shadows /api/v3.
//
// Four explicit routes, not $ported(): the bare `/api` and `/api/{rest}`, each with a
// locale-prefixed twin. **Declared, not redirected.** The laminas route inherits SlmLocale's
// 302 hop on the unprefixed form; these answer directly instead, the same choice the /api/v3
// endpoints make — an agent has no Accept-Language preference worth a redirect, and every
// answer here is a machine envelope. (Trying to reproduce the hop through $ported() fataled:
// its locale-redirect listener cannot rebuild a target for a `{rest}` catch-all whose value
// carries slashes.) The prefixed twins exist because crawlers indexed `/{locale}/api/v1/…`
// and those must still 410; the controller's retired-path regex accepts the optional locale
// segment for exactly them. Because they answer their own unprefixed path, all four are
// declared in LocalePrefixDeclarationTest::NO_REDIRECT.
//
// Open: no laminas guard to name (the laminas entry is guest+user, i.e. everyone). Every
// answer is the controller's own JSON envelope, so no HTML denial style is needed.
$apiRefusal = RouteAccess::openToEveryone(
    'the JSON refusal for unmatched /api paths; the laminas route it shadows admits everyone '
    . '(guest, user) and every response is a machine envelope, not a page'
);
$apiRefusalPaths = [
    'api-not-found'             => '/api',
    'api-not-found/rest'        => '/api/{rest}',
    'api-not-found.locale'      => '/{_locale}/api',
    'api-not-found/rest.locale' => '/{_locale}/api/{rest}',
];
// Distinct loop-variable names on purpose: config/symfony/routes.php is loaded with
// `require` — including by test/Integration/ReservedVerbsTest, which does it *inside a
// method* — so any top-level $name/$path here would leak into and clobber the caller's
// variables of those names. `$apiRefusalName`/`$apiRefusalPath` cannot collide.
foreach ($apiRefusalPaths as $apiRefusalName => $apiRefusalPath) {
    $routes->add($apiRefusalName, new Route(
        $apiRefusalPath,
        ['_controller' => ApiRouteNotFoundController::class, RouteAccess::ATTRIBUTE => $apiRefusal, 'rest' => ''],
        ['rest' => '.*', '_locale' => $locales]
    ));
}

// The catch-all, and last for that reason. `.*` rather than `.+` so that "/"
// matches too, with an empty `path`. Any path no route above matched is a 404, rendered
// by App\Controller\NotFoundController — the direct replacement for App\Http\LegacyBridge,
// which held this slot until Phase B step 2 (2026-09-08). There are no unported routes
// left for a bridge to reach, so the laminas round-trip is gone.
//
// It declares no RouteAccess and must not: a 404 is open to everyone, and
// App\Authorization\RouteGuard skips it (App\Http\SymfonyRoute::isPorted returns false
// for the catch-all), as does tools/acl-table.php's ported-route table.
$routes->add('not-found', new Route(
    '/{path}',
    ['_controller' => NotFoundController::class, 'path' => ''],
    ['path' => '.*']
));

return $routes;

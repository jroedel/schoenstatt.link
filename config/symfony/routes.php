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
use App\Controller\AdminController;
use App\Controller\Api\ApiSchemaController;
use App\Controller\Api\AssociationsV3Controller;
use App\Controller\Api\MethodNotAllowedController;
use App\Controller\Api\PhrasesV3Controller;
use App\Controller\AssociationEditController;
use App\Controller\AssociationsController;
use App\Controller\CacheStatusController;
use App\Controller\ClearPersistentCacheController;
use App\Controller\ContentPageController;
use App\Controller\DataProblemsController;
use App\Controller\DictionaryController;
use App\Controller\HealthController;
use App\Controller\LibrariesController;
use App\Controller\MusicController;
use App\Controller\OneFiftyPreguntasController;
use App\Controller\PhpInfoController;
use App\Controller\RolesController;
use App\Controller\ShrinesController;
use App\Controller\ShrinesGeoJsonController;
use App\Controller\TimelineController;
use App\Controller\ViewChangesController;
use App\Controller\WaysideShrinesController;
use App\Http\LegacyBridge;
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
 */
$ported = static function (
    string $name,
    string $path,
    string|array $controller,
    RouteAccess $access,
    array $extra = [],
    array $requirements = []
) use (
    $routes,
    $locales
): void {
    $defaults = ['_controller' => $controller, RouteAccess::ATTRIBUTE => $access] + $extra;
    $routes->add($name, new Route($path, $defaults, $requirements));
    $routes->add(
        $name . '.locale',
        new Route('/{_locale}' . $path, $defaults, $requirements + ['_locale' => $locales])
    );
};

// SionModel's maintenance endpoints, ported 2026-08-05. Both are machine
// endpoints gated by a maintenance key the controller checks itself, so they need
// nothing the laminas MVC listeners provide — no session, no ACL guard, no view
// layer. See docs/strangler.md.
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
$ported('sm-cache-status', '/sm/cache-status', CacheStatusController::class, $maintenance);
$ported(
    'sm-clear-persistent-cache',
    '/sm/clear-persistent-cache',
    ClearPersistentCacheController::class,
    $maintenance
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
// Only `/admin` itself. Its laminas children — /admin/import-father,
// /admin/maintenance, /admin/literature-maintenance, /admin/translations — are
// separate routes with their own guards, and a literal path with no trailing-slash
// variant is what leaves every one of them falling through to `legacy`.
$ported(
    'admin',
    '/admin',
    AdminController::class,
    RouteAccess::guardedBy('route/admin'),
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

// The shrine GeoJSON endpoints, ported 2026-08-07 — the first ported routes that
// answer JSON to a machine caller rather than HTML to a browser. Both versions are
// served by one controller because the two laminas actions are byte-identical and the
// two live responses were measured equal to the byte.
//
// Declared open rather than checked, and this is the maintenance-endpoint argument
// rather than the `shrines` one. The guards they shadow
// (`route/api-v1/shrines-json`, `route/api-v2/shrines-json`) both carry `null` in
// their roles, so consulting them could only answer yes — and unlike the shrine
// *index*, these endpoints render no permission-gated markup, so nothing else on the
// page has already paid for the ACL. Checking anyway would put a session start and
// the role/resource queries behind it on an endpoint the mobile apps poll.
// tools/acl-table.php cross-checks the claim and warns if either guard stops being
// public.
//
// Declaring them open is also what removes the contradictory cache headers the
// laminas response sends — no session means no session cache limiter. See the
// controller's docblock; ShrinesGeoJsonSmokeTest asserts it.
$shrineGeoJson = RouteAccess::openToEveryone(
    'the laminas guard it shadows is public (a null role admits everyone), and unlike the shrine index '
    . 'this endpoint renders no permission-gated markup, so nothing has already built the ACL. Checking '
    . 'would start a session and run the role/resource queries — some through Books\Model\LibraryTable — '
    . 'on an endpoint the mobile apps poll, for a foregone answer'
);
$ported(
    'api-v1/shrines-json',
    '/api/v1/associations/shrines.json',
    ShrinesGeoJsonController::class,
    $shrineGeoJson
);
$ported(
    'api-v2/shrines-json',
    '/api/v2/associations/shrines.json',
    ShrinesGeoJsonController::class,
    $shrineGeoJson
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
// (2 effective roles of 43). The *read-only* route only: its sibling
// `sion-model/auto-fix-data-problems` renders the same .phtml with a CSRF confirm form
// and stays on laminas, so that template keeps serving it. No method constraint, as the
// laminas route has none.
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
// docs/strangler.md) is still unreproduced.
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
// docs/strangler.md called the form routes the largest single thing left, blocked on
// two things: no form layer, and JUser's unreproduced static table adapter. The first
// was true and App\Form\BootstrapFormRenderer answers it. The second turns out not to
// apply here — only CreateRoleForm, EditUserForm, DeleteUserForm and EditPhraseForm
// read that adapter, through a NoRecordExists validator, and AssociationForm touches
// neither. So this form could move while the user forms still cannot.
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

// The catch-all, and last for that reason. `.*` rather than `.+` so that "/"
// matches too, with an empty `path`.
//
// It declares no RouteAccess and must not: this is not a ported route but the door
// back into laminas-mvc, which runs BjyAuthorize\Guard\Route itself for whatever it
// matches. App\Authorization\RouteGuard skips it on exactly that ground
// (App\Http\SymfonyRoute::isPorted), and tools/acl-table.php excludes it from the
// ported-route table for the same reason.
$routes->add('legacy', new Route(
    '/{path}',
    ['_controller' => LegacyBridge::class, 'path' => ''],
    ['path' => '.*']
));

return $routes;

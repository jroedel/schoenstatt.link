<?php

declare(strict_types=1);

namespace App\Laminas;

use App\Acl\AclProvider;
use App\Acl\IsAllowed;
use Closure;
use Books\View\Helper\BooksJsonLd;
use Books\View\Helper\Coins;
use Books\View\Helper\FileSize;
use Books\View\Helper\FormatField;
use Books\View\Helper\FormatPublicationUrlObject;
use Books\View\Helper\Markdown;
use Books\View\Helper\FormatPublication;
use JTranslate\Model\CountriesInfo;
use JTranslate\View\Helper\CountryName;
use JTranslate\View\Helper\Flag;
use JTranslate\View\Helper\LanguageName;
use App\View\Helper\DateFormat;
use App\View\Helper\Translate;
use App\View\Label;
use Laminas\Translator\TranslatorInterface;
use Schoenstatt\Service\AssociationKindsService;
use Schoenstatt\View\Helper\FormatAssociation;
use Schoenstatt\View\Helper\FormatEntity;
use Schoenstatt\View\Helper\FormatPerson;
use SionModel\I18n\View\Helper\DatePrecisionFormat;
use SionModel\I18n\View\Helper\DayFormat;
use SionModel\Service\EntitiesService;
use SionModel\View\Helper\Address;
use SionModel\View\Helper\DiffForHumans;
use SionModel\View\Helper\EditPencil;
use SionModel\View\Helper\EditPencilNew;
use SionModel\View\Helper\Email;
use SionModel\View\Helper\FormatUrlObject;
use SionModel\View\Helper\Telephone;
use SionModel\View\Helper\Tooltip;

/**
 * The laminas view helpers a Symfony-served route may reuse — and, by being the
 * only way to reach one, the ones it may not.
 *
 * The migration exists to remove laminas-view, so reusing any of it needs a
 * reason. The reason is the one App\Laminas\ServiceBridge already gives for reusing
 * laminas services: rewriting every dependency at the moment its route moves turns
 * one migration into many. Each helper exposed below was measured to resolve and
 * run with **no MvcEvent** — the four formatters reach the renderer for nothing but
 * its escapers, and IsAllowed and ZfcUserDisplayName reach past it entirely, to the
 * ACL and the authentication service. The libphonenumber formatting behind
 * `telephone` and the translated country-name table behind `flag` are real logic
 * that would otherwise be duplicated and drift.
 *
 * The list is short because most of laminas-view cannot work here. Anything that
 * assembles a URL — `formatEntity`, `formatAssociation`, `formatPerson`,
 * `editPencil`, the whole `navigation` family, `localeUrl`, `routeName`,
 * `libraryInfo` — reaches `$this->view->url()`, whose helper wants a RouteMatch off
 * the MvcEvent, and fails with "Call to a member function getRouteMatch() on null"
 * from deep inside laminas-view, on a page that is otherwise rendering fine. Those
 * are reimplemented on this side instead, against App\Laminas\RouteUrl. Exposing
 * this as a typed method per helper rather than a `get(string $name)` is what makes
 * that a compile-time fact rather than a rule someone has to remember.
 *
 * Nothing is resolved from a plugin manager any more. Every helper below is constructed
 * here, which is what allowed laminas-view to be removed: the manager existed to hand each
 * helper a renderer it could reach its collaborators through, and none of them reaches one.
 */
final class ViewHelpers
{
    private ?IsAllowed $isAllowed = null;

    /*
     * Every helper this class exposes, constructed here. Each is a plain class — no
     * `Laminas\View\Helper\AbstractHelper` base, nothing asked of a renderer — and each
     * takes as constructor arguments the collaborators it used to reach through
     * `$this->view`. That is what emptied the plugin manager and let laminas-view go.
     */
    private ?Email $email = null;
    private ?LanguageName $languageName = null;
    private ?Markdown $markdown = null;
    private ?FileSize $fileSize = null;
    private ?DatePrecisionFormat $datePrecisionFormat = null;
    private ?Telephone $telephone = null;
    private ?FormatUrlObject $formatUrlObject = null;
    private ?DayFormat $dayFormat = null;
    private ?Tooltip $tooltip = null;
    private ?FormatPublicationUrlObject $formatPublicationUrlObject = null;
    private ?Coins $coins = null;
    private ?BooksJsonLd $booksJsonLd = null;
    private ?FormatField $formatField = null;
    private ?Address $address = null;
    private ?DiffForHumans $diffForHumans = null;
    private ?TranslatorInterface $translator = null;
    private ?Translate $translateHelper = null;
    private ?Flag $flag = null;
    private ?CountryName $countryName = null;
    private ?EditPencil $editPencil = null;
    private ?EditPencilNew $editPencilNew = null;
    private ?FormatPerson $formatPerson = null;
    private ?FormatAssociation $formatAssociation = null;
    private ?FormatEntity $formatEntity = null;
    private ?FormatPublication $formatPublication = null;
    private ?Label $label = null;
    private ?DateFormat $dateFormat = null;
    private ?LocaleUrlSubstitute $localeUrl = null;

    /**
     * @param Closure(): RouteUrl $urls handed to App\Laminas\LocaleUrlSubstitute below.
     *        A closure rather than the object because RouteUrl reads the request's base
     *        URL when it is built, and this class is constructed before the request has
     *        necessarily been handled.
     */
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Closure $urls
    ) {
    }

    public function flag(): Flag
    {
        //memoized: the constructor translates the whole ISO country table, and a shrine
        //listing renders a flag per row
        return $this->flag ??= new Flag($this->countriesInfo());
    }

    public function email(): Email
    {
        return $this->email ??= new Email();
    }

    public function telephone(): Telephone
    {
        //memoized: the constructor builds a libphonenumber util and an offline geocoder,
        //and a person page formats a number per contact row
        return $this->telephone ??= new Telephone($this->translator());
    }

    public function formatUrlObject(): FormatUrlObject
    {
        return $this->formatUrlObject ??= new FormatUrlObject();
    }

    /**
     * The Intl date formatter. Reaches ext/intl and `\Locale::getDefault()` and nothing
     * else — no MvcEvent — which is why it is safe to resolve on a Symfony-served page.
     * Ours since 2026-09; ext/intl still does the work of knowing each locale's date
     * patterns, which is exactly the kind of thing not to reimplement.
     */
    public function dateFormat(): DateFormat
    {
        return $this->dateFormat ??= new DateFormat();
    }

    /**
     * The six helpers `books/publications/publication-info` needs, added when the
     * publication show page was ported.
     *
     * Grouped here because the argument for all six is one argument, made once: each was
     * measured to resolve and run with **no MvcEvent** (none of them reaches
     * `$this->view->url()`, which is the single thing that makes a helper unavailable on
     * this side), and each is real logic rather than markup worth retyping —
     *
     *   formatField                a label/value row with three display options
     *   languageName               ext/intl's translated language names
     *   markdown                   the CommonMark filter, configured
     *   coins                      a COinS/OpenURL span for Zotero
     *   booksJsonLd                the schema.org projection of a publication
     *   formatPublicationUrlObject FormatUrlObject with the Borrow/Purchase/Download
     *                              labels turned into buttons
     *
     * `formatField` reaches the ACL through the renderer for its
     * `displayOnlyWithPermission` option, which works here for the same reason
     * `isAllowed()` below does.
     */
    public function formatField(): FormatField
    {
        //the label goes through the shared `translate` **helper**, not the translator: it is
        //the object that carries the domain useTextDomain() sets, and the helper passes none
        return $this->formatField ??= new FormatField(
            $this->translateHelper()->__invoke(...),
            $this->isAllowed()->__invoke(...),
            $this->dateFormat()->__invoke(...)
        );
    }

    public function languageName(): LanguageName
    {
        return $this->languageName ??= new LanguageName();
    }

    public function markdown(): Markdown
    {
        //memoized because the constructor builds a ParsedownExtra, and a content page
        //runs this filter once per block
        return $this->markdown ??= new Markdown();
    }

    public function coins(): Coins
    {
        return $this->coins ??= new Coins();
    }

    public function booksJsonLd(): BooksJsonLd
    {
        if (null !== $this->booksJsonLd) {
            return $this->booksJsonLd;
        }

        return $this->booksJsonLd = new BooksJsonLd($this->localeUrl()->__invoke(...));
    }

    public function formatPublicationUrlObject(): FormatPublicationUrlObject
    {
        return $this->formatPublicationUrlObject ??= new FormatPublicationUrlObject($this->translator());
    }

    /**
     * Byte counts as `1.4 MB`, for the Drive file panel on a publication page. Pure
     * arithmetic and a units table — no renderer, no MvcEvent — and it answers the
     * literal string `n/a` for anything under one byte, which is behaviour a template
     * would otherwise have to know about.
     */
    public function fileSize(): FileSize
    {
        return $this->fileSize ??= new FileSize();
    }

    /**
     * SionModel's relative-time formatter — `<abbr title="6/13/65, 12:00 AM">57 years
     * ago</abbr>`.
     *
     * On the allowlist rather than reimplemented because it is logic rather than markup.
     * It used to wrap Carbon; since 2026-09-21 it wraps `SionModel\I18n\RelativeTime`,
     * which carries the nine strings per locale that `diffForHumans()` actually reached —
     * seven units plus the two framings — for the five locales this site serves. The
     * "60-odd patterns per locale" this note used to cite was Carbon's whole locale file,
     * most of it calendar formats and month names nothing here asked for.
     *
     * It reaches the renderer only for `dateFormat` — the `title` attribute — so it
     * runs with no MvcEvent for the same reason dateFormat() above does. Measured on a
     * ported comment list.
     *
     * Memoized because the helper is cheap to keep and reached once per comment on a page;
     * it no longer *has* to be. The first-invocation latch it used to carry existed only to
     * set Carbon's global locale once, and `RelativeTime` takes its locale as an argument.
     */
    public function diffForHumans(): DiffForHumans
    {
        return $this->diffForHumans ??= new DiffForHumans($this->dateFormat()->__invoke(...));
    }

    /**
     * The four helpers `schoenstatt/associations/show` needs, added when the association
     * show page was ported. Same argument as the publication six above: each runs with no
     * MvcEvent, and each is logic rather than markup.
     *
     *   address              a postal address assembled from six nullable columns
     *   countryName          ext/intl country names, optionally with a flag
     *   datePrecisionFormat  a date rendered to the precision it was recorded at — the
     *                        difference between "1914" and "October 18, 1914", which is
     *                        the whole point of storing a precision alongside the date
     *   tooltip              a `data-toggle="tooltip"` span
     */
    public function address(): Address
    {
        if (null !== $this->address) {
            return $this->address;
        }

        //the place-line patterns, per country, that SionModel's config carries
        /** @var array<string, mixed> $config */
        $config = $this->laminas->get('SionModel\\Config');

        return $this->address = new Address($config, $this->countryName()->__invoke(...));
    }

    public function countryName(): CountryName
    {
        return $this->countryName ??= new CountryName($this->countriesInfo());
    }

    private function countriesInfo(): CountriesInfo
    {
        /** @var CountriesInfo $countries */
        $countries = $this->laminas->get(CountriesInfo::class);

        return $countries;
    }

    public function datePrecisionFormat(): DatePrecisionFormat
    {
        return $this->datePrecisionFormat ??= new DatePrecisionFormat();
    }

    /**
     * SionModel's `dayFormat` — a month-and-day with no year, for a person's nameday.
     * On the allowlist because it needs nothing but `escapeHtml` and `translate`, both
     * of which a plain PhpRenderer answers; there is no `url()` anywhere in it.
     *
     * Worth knowing before reading its output: it translates its month names in the
     * **`Patres`** text domain, not the page's and not `default`, so a site with no
     * Patres catalog renders them in English in every locale. That is the original's
     * behaviour and this changes none of it.
     */
    public function dayFormat(): DayFormat
    {
        return $this->dayFormat ??= new DayFormat($this->translator());
    }

    public function tooltip(): Tooltip
    {
        return $this->tooltip ??= new Tooltip();
    }

    /**
     * The entity-markup cluster, ported together on 2026-09 and constructed here because
     * nothing else can: each of them used to reach its collaborators through the renderer
     * laminas-view injected into every AbstractHelper, and each takes them as constructor
     * closures now. This class is where those closures come from — `url` and `isAllowed`
     * off the plugin manager, `translate` off the shared helper that carries the request's
     * text domain, the rest off the accessors above.
     *
     * **Nothing in this application calls them today.** App\Laminas\EntityFormatter and the
     * macros in templates/schoenstatt/_entity-format.html.twig reproduce every one of these
     * against RouteUrl, because the originals could not run without an MvcEvent. They are
     * wired anyway: the port has to be constructible to be verifiable, and a second host
     * (patres) still renders through them.
     */
    public function editPencil(): EditPencil
    {
        return $this->editPencil ??= new EditPencil(
            $this->entitiesService(),
            $this->isAllowed()->__invoke(...),
            $this->url()
        );
    }

    public function editPencilNew(): EditPencilNew
    {
        return $this->editPencilNew ??= new EditPencilNew(
            $this->isAllowed()->__invoke(...),
            $this->url()
        );
    }

    public function formatPerson(): FormatPerson
    {
        //`translate` is the shared view helper, not the translator: the person's title is
        //looked up with no text domain, so only the object useTextDomain() set answers in
        //the right one
        return $this->formatPerson ??= new FormatPerson(
            $this->flag()->__invoke(...),
            $this->translateHelper()->__invoke(...),
            $this->url(),
            $this->label()->render(...),
            $this->editPencil()->__invoke(...)
        );
    }

    public function formatAssociation(): FormatAssociation
    {
        return $this->formatAssociation ??= new FormatAssociation(
            $this->associationKindLabels(),
            $this->flag()->__invoke(...),
            $this->translateHelper()->__invoke(...),
            $this->isAllowed()->__invoke(...),
            $this->url(),
            $this->label()->render(...),
            $this->editPencil()->__invoke(...)
        );
    }

    /**
     * `formatEntity` is **Schoenstatt's** subclass, not SionModel's: the Schoenstatt module
     * registered the same helper name and won the config merge, so that is what the name has
     * always resolved to here. It takes `person`, `association` and `role` itself and defers
     * the rest to its parent.
     */
    public function formatEntity(): FormatEntity
    {
        return $this->formatEntity ??= new FormatEntity(
            $this->entitiesService(),
            $this->associationKindLabels(),
            $this->routePermissionCheckingEnabled(),
            $this->flag()->__invoke(...),
            $this->dateFormat()->__invoke(...),
            $this->translateHelper()->__invoke(...),
            $this->url(),
            $this->editPencil()->__invoke(...),
            $this->editPencilNew()->__invoke(...),
            $this->isAllowed()->__invoke(...),
            //the `format_view_helper` deferral, which used to be a helper name looked up on
            //the renderer at runtime. One entry: publication -> formatPublication
            ['formatPublication' => $this->formatPublication()->__invoke(...)],
            $this->formatPerson()->__invoke(...),
            $this->formatAssociation()->__invoke(...),
            $this->label()->render(...)
        );
    }

    public function formatPublication(): FormatPublication
    {
        return $this->formatPublication ??= new FormatPublication(
            $this->entitiesService(),
            $this->routePermissionCheckingEnabled(),
            $this->flag()->__invoke(...),
            $this->dateFormat()->__invoke(...),
            $this->translateHelper()->__invoke(...),
            $this->url(),
            $this->editPencil()->__invoke(...),
            $this->editPencilNew()->__invoke(...),
            $this->isAllowed()->__invoke(...),
            [],
            $this->label()->render(...)
        );
    }

    /**
     * Route assembly as a closure, for the entity-markup cluster.
     *
     * `Laminas\View\Helper\Url` until laminas-view went, then the laminas router directly,
     * and now {@see RouteUrl::path()} — the one seam every other link on the site already
     * comes through, so these helpers cannot drift from the rest of the application's URLs.
     * The signature is what the cluster has always passed: a route name and its parameters,
     * never "the current route", which is the only thing that needed a RouteMatch.
     *
     * @return Closure(string, array<string, mixed>): string
     */
    private function url(): Closure
    {
        $urls = $this->urls;

        return static fn (string $route, array $params = []): string
            => ($urls)()->path($route, $params);
    }

    /** The Bootstrap label markup, translating through the same page-aware helper. */
    private function label(): Label
    {
        return $this->label ??= new Label($this->translateHelper()->__invoke(...));
    }

    private function entitiesService(): EntitiesService
    {
        /** @var EntitiesService $entities */
        $entities = $this->laminas->get(EntitiesService::class);

        return $entities;
    }

    /** The association kinds, already translated, keyed by kind. */
    private function associationKindLabels(): array
    {
        /** @var AssociationKindsService $kinds */
        $kinds = $this->laminas->get(AssociationKindsService::class);

        return $kinds->getValueOptions();
    }

    /**
     * `sion_model.route_permission_checking_enabled`, which is `true` here: a link is
     * suppressed when the viewer may not reach its route.
     */
    private function routePermissionCheckingEnabled(): bool
    {
        /** @var array<string, mixed> $config */
        $config = $this->laminas->get('SionModel\\Config');

        return (bool) ($config['route_permission_checking_enabled'] ?? false);
    }

    /**
     * The authorization check every Twig `is_allowed()` call and every ported controller's
     * `isAllowed()` wrapper reaches. Since the ACL cutover this is `App\Acl\IsAllowed` over
     * `App\Acl\Authorizer`, not `BjyAuthorize\View\Helper\IsAllowed` — the call signature
     * is identical and test/Integration/AclParityTest pins the decision. Memoized so the
     * request assembles the model and resolves the identity once.
     */
    public function isAllowed(): IsAllowed
    {
        if (null !== $this->isAllowed) {
            return $this->isAllowed;
        }

        //the container's, not a second one: the ported controllers and App\Sion\* ask it
        //for the same service, and two AclProviders in a request assemble the ACL twice
        $isAllowed = $this->laminas->get(IsAllowed::class);

        return $this->isAllowed = $isAllowed instanceof IsAllowed
            ? $isAllowed
            : new IsAllowed(new AclProvider($this->laminas));
    }

    /**
     * Point the shared `translate` **view helper** at a text domain, which is what
     * JTranslate's dispatch listener does for a laminas request and what nothing does
     * for a Symfony-served one.
     *
     * This is not the same translator App\Twig\LaminasExtension::translate() uses. That
     * one is asked directly, with the domain passed per call. This is the helper that
     * *other helpers* reach through `$this->view->translate(...)` —
     * `Books\View\Helper\FormatField` is the case that forced it — and they pass no
     * domain, so whatever is set here is what they get.
     *
     * The failure it fixes is invisible in English: an unset domain means the lookup
     * lands in `default`, misses, and returns the source string, which *is* the English
     * text. `/es/SL202186L` rendered its whole bibliographic panel in English against a
     * laminas page that renders it in Spanish.
     */
    public function useTextDomain(string $domain): void
    {
        $this->translateHelper()->setTranslatorTextDomain($domain);
    }

    /**
     * The shared `translate` view helper, memoized because the domain set on it has to be
     * the domain the helpers holding it read back.
     *
     * A host whose plugin manager answers with something else gets one that translates
     * nothing, which is what a total catalog miss does anyway.
     */
    private function translateHelper(): Translate
    {
        if (null !== $this->translateHelper) {
            return $this->translateHelper;
        }

        return $this->translateHelper = (new Translate())->setTranslator($this->translator());
    }

    /**
     * The one translator, for the ported helpers that used to reach `$this->view->translate()`.
     *
     * Resolved lazily and memoized: a page that formats no phone number and no month name
     * never builds it, and the ones that do build it once. Null is not expected here — the
     * container always has a translator — but the helpers accept null so that a host
     * without one renders source text instead of failing.
     */
    private function translator(): ?TranslatorInterface
    {
        if (null !== $this->translator) {
            return $this->translator;
        }
        if (! $this->laminas->has(TranslatorInterface::class)) {
            return null;
        }
        $translator = $this->laminas->get(TranslatorInterface::class);

        return $this->translator = $translator instanceof TranslatorInterface ? $translator : null;
    }

    /**
     * The `localeUrl` helper, reached only by Books\View\Helper\BooksJsonLd.
     *
     * laminas's own `localeUrl` factory needed an MvcEvent, so a substitute was registered
     * over it in the plugin manager; with the manager gone it is simply constructed here.
     * That also retires the `setAllowOverride()` dance the registration needed — the
     * manager was a *shared* service and this class is not, so a second ViewHelpers built
     * against the same ServiceBridge (which two integration tests do) met a substitute that
     * was already registered and `setService()` threw.
     */
    private function localeUrl(): LocaleUrlSubstitute
    {
        return $this->localeUrl ??= new LocaleUrlSubstitute($this->urls);
    }
}

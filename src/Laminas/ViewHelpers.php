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
use JTranslate\View\Helper\CountryName;
use JTranslate\View\Helper\Flag;
use JTranslate\View\Helper\LanguageName;
use App\View\Helper\DateFormat;
use App\View\Helper\Translate;
use Laminas\View\HelperPluginManager;
use SionModel\I18n\View\Helper\DatePrecisionFormat;
use SionModel\I18n\View\Helper\DayFormat;
use SionModel\View\Helper\Address;
use SionModel\View\Helper\DiffForHumans;
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
 * Priming note: HelperPluginManager injects a renderer into its helpers only once a
 * renderer has claimed it, and without one `$this->view` is null and even `flag` fatals
 * on escapeHtmlAttr(). App\Laminas\ViewHelperManagerFactory attaches a bare PhpRenderer
 * for that reason; it resolves and renders nothing.
 */
final class ViewHelpers
{
    private ?HelperPluginManager $helpers = null;

    private ?IsAllowed $isAllowed = null;

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
        /** @var Flag $helper */
        $helper = $this->helpers()->get('flag');

        return $helper;
    }

    public function email(): Email
    {
        /** @var Email $helper */
        $helper = $this->helpers()->get('email');

        return $helper;
    }

    public function telephone(): Telephone
    {
        /** @var Telephone $helper */
        $helper = $this->helpers()->get('telephone');

        return $helper;
    }

    public function formatUrlObject(): FormatUrlObject
    {
        /** @var FormatUrlObject $helper */
        $helper = $this->helpers()->get('formatUrlObject');

        return $helper;
    }

    /**
     * The Intl date formatter. Reaches ext/intl and `\Locale::getDefault()` and nothing
     * else — no MvcEvent — which is why it is safe to resolve on a Symfony-served page.
     * Ours since 2026-09; ext/intl still does the work of knowing each locale's date
     * patterns, which is exactly the kind of thing not to reimplement.
     */
    public function dateFormat(): DateFormat
    {
        /** @var DateFormat $helper */
        $helper = $this->helpers()->get('dateFormat');

        return $helper;
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
        /** @var FormatField $helper */
        $helper = $this->helpers()->get('formatField');

        return $helper;
    }

    public function languageName(): LanguageName
    {
        /** @var LanguageName $helper */
        $helper = $this->helpers()->get('languageName');

        return $helper;
    }

    public function markdown(): Markdown
    {
        /** @var Markdown $helper */
        $helper = $this->helpers()->get('markdown');

        return $helper;
    }

    public function coins(): Coins
    {
        /** @var Coins $helper */
        $helper = $this->helpers()->get('coins');

        return $helper;
    }

    public function booksJsonLd(): BooksJsonLd
    {
        /** @var BooksJsonLd $helper */
        $helper = $this->helpers()->get('booksJsonLd');

        return $helper;
    }

    public function formatPublicationUrlObject(): FormatPublicationUrlObject
    {
        /** @var FormatPublicationUrlObject $helper */
        $helper = $this->helpers()->get('formatPublicationUrlObject');

        return $helper;
    }

    /**
     * Byte counts as `1.4 MB`, for the Drive file panel on a publication page. Pure
     * arithmetic and a units table — no renderer, no MvcEvent — and it answers the
     * literal string `n/a` for anything under one byte, which is behaviour a template
     * would otherwise have to know about.
     */
    public function fileSize(): FileSize
    {
        /** @var FileSize $helper */
        $helper = $this->helpers()->get('fileSize');

        return $helper;
    }

    /**
     * SionModel's relative-time formatter — `<abbr title="6/13/65, 12:00 AM">57 years
     * ago</abbr>`.
     *
     * On the allowlist rather than reimplemented because what it wraps is a Carbon
     * locale table: `Carbon::setLocale(\Locale::getDefault())` on first use, then
     * `diffForHumans()`, which is 60-odd translated relative-time patterns per locale.
     * Reproducing that is not "keep the markup identical", it is shipping a second
     * translation catalogue.
     *
     * It reaches the renderer only for `dateFormat` — the `title` attribute — so it
     * runs with no MvcEvent for the same reason dateFormat() above does. Measured on a
     * ported comment list.
     *
     * The first-invocation latch inside the helper is why this returns the *shared*
     * instance out of the plugin manager rather than a new one: `Carbon::setLocale()`
     * is global state, and a second instance would set it a second time on a page that
     * has already formatted a date.
     */
    public function diffForHumans(): DiffForHumans
    {
        /** @var DiffForHumans $helper */
        $helper = $this->helpers()->get('diffForHumans');

        return $helper;
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
        /** @var Address $helper */
        $helper = $this->helpers()->get('address');

        return $helper;
    }

    public function countryName(): CountryName
    {
        /** @var CountryName $helper */
        $helper = $this->helpers()->get('countryName');

        return $helper;
    }

    public function datePrecisionFormat(): DatePrecisionFormat
    {
        /** @var DatePrecisionFormat $helper */
        $helper = $this->helpers()->get('datePrecisionFormat');

        return $helper;
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
        /** @var DayFormat $helper */
        $helper = $this->helpers()->get('dayFormat');

        return $helper;
    }

    public function tooltip(): Tooltip
    {
        /** @var Tooltip $helper */
        $helper = $this->helpers()->get('tooltip');

        return $helper;
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
        return $this->isAllowed ??= new IsAllowed(new AclProvider($this->laminas));
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
        $translate = $this->helpers()->get('translate');
        if ($translate instanceof Translate) {
            $translate->setTranslatorTextDomain($domain);
        }
    }

    private function helpers(): HelperPluginManager
    {
        if (null !== $this->helpers) {
            return $this->helpers;
        }

        /** @var HelperPluginManager $helpers */
        $helpers = $this->laminas->get('ViewHelperManager');

        //`localeUrl` is on the refused list because its factory needs an MvcEvent, and a
        //helper this class does not expose is normally simply unreachable. It is not:
        //Books\View\Helper\BooksJsonLd reaches it internally, and that is a helper this
        //class *does* expose. So a working implementation is registered in its place
        //rather than the caller being reimplemented — see App\Laminas\LocaleUrlSubstitute
        //for how that was discovered and what it costs.
        //`setAllowOverride(true)` because the plugin manager is a *shared* service and
        //this class is not: a second ViewHelpers built against the same ServiceBridge —
        //which is what test/Integration/ShrineTemplateTest and EntityFormatterTest do —
        //finds the substitute already registered and `setService()` throws
        //ContainerModificationsNotAllowedException. Registering is idempotent this way,
        //and the flag is put back so nothing else acquires the licence.
        $helpers->setAllowOverride(true);
        $helpers->setService('localeUrl', new LocaleUrlSubstitute($this->urls));
        $helpers->setAllowOverride(false);

        return $this->helpers = $helpers;
    }
}

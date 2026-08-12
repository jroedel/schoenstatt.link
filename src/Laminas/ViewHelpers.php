<?php

declare(strict_types=1);

namespace App\Laminas;

use BjyAuthorize\View\Helper\IsAllowed;
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
use JTranslate\View\Helper\NowMessenger as NowMessengerHelper;
use JUser\View\Helper\ZfcUserDisplayName;
use Laminas\I18n\View\Helper\DateFormat;
use Laminas\Mvc\Plugin\FlashMessenger\View\Helper\FlashMessenger;
use Laminas\View\HelperPluginManager;
use SionModel\I18n\View\Helper\DatePrecisionFormat;
use SionModel\View\Helper\Address;
use SionModel\View\Helper\DiffForHumans;
use SionModel\View\Helper\Email;
use SionModel\View\Helper\FormatUrlObject;
use SionModel\View\Helper\Telephone;
use SionModel\View\Helper\Tooltip;
use TwbBundle\View\Helper\TwbBundleLabel;

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
 * renderer has claimed it, which PhpRenderer does in its own constructor. So
 * `ViewRenderer` must be pulled out of the container before any helper is used, or
 * `$this->view` is null and even `flag` fatals on escapeHtmlAttr(). That single
 * `get()` is the whole of laminas-view instantiated here: no MvcEvent, no view
 * model, no layout, no rendering.
 */
final class ViewHelpers
{
    private ?HelperPluginManager $helpers = null;

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
     * Session-backed, which is the point: a laminas action that sets a flash and
     * redirects to a ported page would otherwise lose it silently.
     */
    public function flashMessenger(): FlashMessenger
    {
        /** @var FlashMessenger $helper */
        $helper = $this->helpers()->get('flashMessenger');

        return $helper;
    }

    /**
     * TwbBundle's Bootstrap label — `<span class="label-info label">…</span>`.
     *
     * On the allowlist for the same reason `flag` is: it is real logic (a translator
     * domain, and escaping of both the text and the class attribute, which is why the
     * space in the class arrives as `&#x20;`) and it was measured to run with no
     * MvcEvent. Reproducing it would mean copying that escaping by hand and getting the
     * entity right, on markup that appears beside a role on the changes page.
     */
    /**
     * Laminas' Intl date formatter. Reaches ext/intl and \Locale::getDefault() and
     * nothing else — no MvcEvent — and reproducing it would mean re-deriving the
     * IntlDateFormatter pattern per locale, which is exactly the kind of thing to reuse
     * rather than copy.
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

    public function tooltip(): Tooltip
    {
        /** @var Tooltip $helper */
        $helper = $this->helpers()->get('tooltip');

        return $helper;
    }

    /**
     * JTranslate's within-request messenger — the counterpart of `flashMessenger` for
     * messages meant for the page being rendered rather than the next one.
     *
     * templates/layout.html.twig used to say this could not be reproduced "because
     * nothing on a ported route can populate it". That stopped being true with the
     * literature search page, which adds "No results found." and the 300-result cap
     * notice while rendering. Populating it is a matter of reaching the *same* plugin
     * instance: `NowMessengerFactory` pulls `nowMessenger` out of the shared
     * ControllerPluginManager and hands it to the helper, so a controller that pushes
     * into that plugin is pushing into what this renders.
     */
    public function nowMessenger(): NowMessengerHelper
    {
        /** @var NowMessengerHelper $helper */
        $helper = $this->helpers()->get('nowMessenger');

        return $helper;
    }

    public function label(): TwbBundleLabel
    {
        /** @var TwbBundleLabel $helper */
        $helper = $this->helpers()->get('label');

        return $helper;
    }

    public function isAllowed(): IsAllowed
    {
        /** @var IsAllowed $helper */
        $helper = $this->helpers()->get('isAllowed');

        return $helper;
    }

    public function displayName(): ZfcUserDisplayName
    {
        /** @var ZfcUserDisplayName $helper */
        $helper = $this->helpers()->get('zfcUserDisplayName');

        return $helper;
    }

    private function helpers(): HelperPluginManager
    {
        if (null !== $this->helpers) {
            return $this->helpers;
        }

        //ordering, not decoration: PhpRenderer::__construct() calls
        //HelperPluginManager::setRenderer($this), and without that every helper's
        //$this->view is null
        $this->laminas->get('ViewRenderer');

        /** @var HelperPluginManager $helpers */
        $helpers = $this->laminas->get('ViewHelperManager');

        //`localeUrl` is on the refused list because its factory needs an MvcEvent, and a
        //helper this class does not expose is normally simply unreachable. It is not:
        //Books\View\Helper\BooksJsonLd reaches it internally, and that is a helper this
        //class *does* expose. So a working implementation is registered in its place
        //rather than the caller being reimplemented — see App\Laminas\LocaleUrlSubstitute
        //for how that was discovered and what it costs.
        $helpers->setService('localeUrl', new LocaleUrlSubstitute($this->urls));

        return $this->helpers = $helpers;
    }
}

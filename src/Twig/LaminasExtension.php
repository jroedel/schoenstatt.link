<?php

declare(strict_types=1);

namespace App\Twig;

use App\Laminas\EntityFormatter;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Laminas\ViewHelpers;
use DateTime;
use DateTimeInterface;
use Laminas\I18n\Translator\TranslatorInterface;
use IntlDateFormatter;
use Symfony\Component\HttpFoundation\RequestStack;
use SionModel\Entity\Entity;
use SionModel\Text\Text;
use SionModel\Service\EntitiesService;
use Throwable;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function is_scalar;
use function is_string;
use function sprintf;

/**
 * The bridge a Twig template crosses to reach laminas: URLs, translation,
 * authorization, and the handful of formatters worth reusing rather than
 * rewriting.
 *
 * Escaping is the thing to understand before adding a function here. Twig
 * autoescapes; laminas .phtml did not, and its helpers escape their own output
 * instead. So a function returning **plain text** (`translate`) is declared
 * ordinary and Twig escapes it — which is strictly safer than the `<?= $this->
 * translate(...) ?>` it replaces. A function returning **markup** is declared
 * `is_safe: html`, and that declaration is a claim about the helper behind it:
 * every one of them was read and escapes its inputs (Flag escapeHtmlAttr's both
 * the class and the country name; Email escapeHtml's the address; Telephone
 * escapeHtml's the number; FormatUrlObject escapeHtml's the label). Marking them
 * safe is what prevents the double escaping `|raw` at call sites would invite —
 * and it keeps `|raw` out of the templates, where a reviewer cannot tell whether
 * it is load-bearing. Two caveats travel with the claim, both pre-existing on the
 * laminas side and neither introduced here: FormatUrlObject interpolates the URL
 * itself unescaped, and Telephone interpolates the geocoder's tooltip unescaped.
 *
 * What is *not* here is as important. Anything that assembles a URL through
 * laminas-view — `formatEntity`, `formatAssociation`, `formatPerson`,
 * `editPencil`, `navigation`, `localeUrl`, `libraryInfo`, `routeName` — needs an
 * MvcEvent and cannot be called at all; App\Laminas\ViewHelpers refuses them by
 * name. `edit_pencil` below is the pattern for replacing one: reimplement the
 * decision against App\Laminas\RouteUrl, keep the markup identical.
 */
final class LaminasExtension extends AbstractExtension
{
    /** Route default naming the module text domain a ported page's strings live in. */
    public const TEXT_DOMAIN_ATTRIBUTE = '_text_domain';

    /** laminas' own default, and the domain consulted after the page's own. */
    public const DEFAULT_TEXT_DOMAIN = 'default';

    /** @var array<string, Entity>|null */
    private ?array $entities = null;
    private ?EntityFormatter $entityFormatter = null;

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly ViewHelpers $helpers,
        private readonly RouteUrl $urls,
        private readonly RequestStack $requests
    ) {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        $html = ['is_safe' => ['html']];

        return [
            new TwigFunction('laminas_path', $this->path(...)),
            new TwigFunction('translate', $this->translate(...)),
            new TwigFunction('is_allowed', $this->isAllowed(...)),
            new TwigFunction('flag', $this->flag(...), $html),
            new TwigFunction('email_link', $this->emailLink(...), $html),
            new TwigFunction('telephone_link', $this->telephoneLink(...), $html),
            new TwigFunction('url_object_link', $this->urlObjectLink(...), $html),
            new TwigFunction('edit_pencil', $this->editPencil(...), $html),
            new TwigFunction('format_entity', $this->formatEntity(...), $html),
            new TwigFunction('short_date', $this->shortDate(...)),
            new TwigFunction('diff_for_humans', $this->diffForHumans(...), $html),
            new TwigFunction('format_field', $this->formatField(...), $html),
            new TwigFunction('language_name', $this->languageName(...)),
            new TwigFunction('markdown', $this->markdown(...), $html),
            new TwigFunction('coins', $this->coins(...), $html),
            new TwigFunction('books_json_ld', $this->booksJsonLd(...), $html),
            new TwigFunction('publication_url_object', $this->publicationUrlObject(...), $html),
            new TwigFunction('file_size', $this->fileSize(...)),
            new TwigFunction('address', $this->address(...), $html),
            new TwigFunction('country_name', $this->countryName(...), $html),
            new TwigFunction('date_precision', $this->datePrecision(...)),
            new TwigFunction('long_date', $this->longDate(...)),
            new TwigFunction('tooltip', $this->tooltip(...), $html),
            new TwigFunction('truncate', $this->truncate(...)),
            new TwigFunction('formats_entity_generally', $this->formatsEntityGenerally(...)),
            new TwigFunction('flash_messages', $this->flashMessages(...), $html),
            new TwigFunction('now_messages', $this->nowMessages(...), $html),
            new TwigFunction('label', $this->label(...), $html),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    public function path(string $routeName, array $params = [], array $options = []): string
    {
        return $this->urls->path($routeName, $params, $options);
    }

    /**
     * A translated phrase, in the page's text domain and then in `default`.
     *
     * **Why two domains.** laminas has no cross-domain fallback —
     * `Translator::translate()` falls back by *locale* only — but it does assign a
     * domain per rendering context: JTranslate's dispatch listener sets the `translate`
     * helper's domain to the controller's module namespace, and the navigation helper's
     * to `Application`. So on one laminas page different strings are looked up in
     * different domains, and the phrases really are scattered that way. Measured in
     * es_ES:
     *
     *     'Shrines'          only in `default`      ("Santuarios")
     *     'Wayside shrines'  only in `Schoenstatt`  ("Hermitas")
     *     'Fr.'              only in the module domains ("P.")
     *
     * A single default domain therefore cannot reproduce the page: whichever one is
     * chosen, half the strings come out in English. Trying the page's domain and then
     * `default` yields the union, which is identical to laminas' output whenever a
     * phrase lives in one domain or agrees across several — the case for every phrase
     * on every ported page, asserted across all five locales by
     * test/Integration/PortedRouteTranslationTest and by the both-front-controllers
     * diff in docs/strangler.md.
     *
     * This is a *superset* of what laminas does internally rather than a mirror of it,
     * and that is the one deliberate divergence in the Twig layer. It cannot lose a
     * translation laminas finds; it could in principle find one laminas misses, which
     * would show up as the ported page being *more* translated than the original.
     *
     * An explicit `$domain` skips all of this and is what the layout passes for the
     * strings it knows the domain of.
     */
    public function translate(string $message, ?string $domain = null): string
    {
        /** @var TranslatorInterface $translator */
        $translator = $this->laminas->get('MvcTranslator');

        if (null !== $domain) {
            return $translator->translate($message, $domain);
        }

        $pageDomain = $this->textDomain();
        if (null !== $pageDomain) {
            $translated = $translator->translate($message, $pageDomain);
            if ($translated !== $message) {
                return $translated;
            }
        }

        return $translator->translate($message, self::DEFAULT_TEXT_DOMAIN);
    }

    /**
     * The text domain of the page being rendered, declared by its route.
     *
     * Named after the module whose laminas controller the route shadows, because that
     * is the domain JTranslate's dispatch listener would have set — for
     * Schoenstatt\Controller\SchoenstattController it is `Schoenstatt`.
     */
    private function textDomain(): ?string
    {
        $request = $this->requests->getMainRequest();
        if (null === $request) {
            return null;
        }
        $domain = $request->attributes->get(self::TEXT_DOMAIN_ATTRIBUTE);

        return is_string($domain) && '' !== $domain ? $domain : null;
    }

    public function isAllowed(string $resource, ?string $privilege = null): bool
    {
        return (bool) $this->helpers->isAllowed()->__invoke($resource, $privilege);
    }

    public function flag(?string $countryCode): string
    {
        return null === $countryCode ? '' : (string) $this->helpers->flag()->__invoke($countryCode);
    }

    public function emailLink(?string $email): string
    {
        return null === $email ? '' : (string) $this->helpers->email()->__invoke($email);
    }

    public function telephoneLink(?string $telephone): string
    {
        return (string) $this->helpers->telephone()->__invoke($telephone);
    }

    /** @param array{url?: string, label?: string}|null $url */
    public function urlObjectLink(?array $url, bool $openInNewTab = true): string
    {
        return null === $url ? '' : (string) $this->helpers->formatUrlObject()->__invoke($url, $openInNewTab);
    }

    /**
     * SionModel\View\Helper\EditPencil, reimplemented — the original reaches
     * `$this->view->url()` and so cannot run here. Same guards, same markup, same
     * route derived from the same entity spec.
     */
    public function editPencil(string $entityType, int|string|null $id): string
    {
        $spec = $this->entities()[$entityType] ?? null;
        if (null === $spec || null === $id || '' === $id || 0 === $id) {
            return '';
        }
        $route = $spec->editRoute;
        $key   = $spec->editRouteKey;
        if (! is_string($route) || '' === $route || ! is_string($key) || '' === $key) {
            return '';
        }

        try {
            $allowed = $this->isAllowed('route/' . $route);
        } catch (Throwable) {
            //what the original does, in its own words: "if there is an exception,
            //we'll assume there's no route permissions configured"
            $allowed = true;
        }
        if (! $allowed) {
            return '';
        }

        return $this->pencilMarkup($route, [$key => $id]);
    }

    /**
     * SionModel\View\Helper\EditPencilNew, reimplemented — the `editRoute` + params
     * form of the same pencil, which is what an entity spec using `defaultRouteParams`
     * reaches instead of the `editRouteKeyField` branch above (text, composition).
     *
     * Its guard is its own: no params or no route renders nothing, and the route
     * permission failure is swallowed the same way, with the same "assume no route
     * permissions are configured" comment behind it.
     *
     * @param array<string, mixed> $params
     */
    public function editPencilForRoute(string $route, array $params): string
    {
        if ('' === $route || [] === $params) {
            return '';
        }

        try {
            $allowed = $this->isAllowed('route/' . $route);
        } catch (Throwable) {
            $allowed = true;
        }

        return $allowed ? $this->pencilMarkup($route, $params) : '';
    }

    /**
     * The pencil markup, in one place because two helpers emit it.
     *
     * The stray space before `>` is the originals' empty $otherAttributes slot — it only
     * ever holds target="_blank" — reproduced so a page rendered both ways is
     * byte-identical and a real difference cannot hide in the whitespace.
     *
     * @param array<string, mixed> $params
     */
    private function pencilMarkup(string $route, array $params): string
    {
        return sprintf(
            ' <a href="%s" ><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>',
            $this->urls->path($route, $params)
        );
    }

    /**
     * SionModel's `formatEntity`, general path. Declared `is_safe: html` because it
     * returns markup and escapes its own inputs, exactly as the helper it replaces does
     * — see App\Laminas\EntityFormatter, which is where the reasoning lives.
     *
     * The *dispatch* around it is not here but in
     * templates/schoenstatt/_entity-format.html.twig, because two of the four types
     * laminas special-cases are already reproduced as macros in that file and a Twig
     * function cannot call a macro.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function formatEntity(string $entityType, array $data, array $options = []): string
    {
        return $this->entityFormatter()->format($entityType, $data, $options);
    }

    /**
     * A date with no time, in the current locale's short form — `dateFormat($d,
     * IntlDateFormatter::SHORT, IntlDateFormatter::NONE)`, which is the only way the
     * changes table formats one. Plain text, so Twig escapes it.
     */
    public function shortDate(mixed $date): string
    {
        if (null === $date) {
            return '';
        }

        return (string) $this->helpers->dateFormat()->__invoke(
            $date,
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE
        );
    }

    /**
     * A relative time — `<abbr title="6/13/65, 12:00 AM">57 years ago</abbr>`.
     *
     * Markup, and `is_safe: html` on the same terms as the rest: the helper builds the
     * `title` through `dateFormat` and the body through Carbon, both of which produce
     * formatted dates rather than anything a visitor typed. No user input reaches it.
     *
     * **Null and non-DateTime return the empty string rather than throwing**, which is
     * the one deliberate difference from the helper. `SionModel\View\Helper\DiffForHumans`
     * throws `InvalidArgumentException` on anything that is not a `DateTime`, and every
     * .phtml that calls it guards the call first — `if ($object['createdOn'] instanceof
     * \DateTime)` in comments-list.phtml, `if (isset($this->entity['createdOn']))` in
     * texts/show.phtml. Those guards exist because the columns really are nullable.
     * Reproducing the guard at each of a dozen Twig call sites is how one gets forgotten,
     * and a forgotten one is a 500 on a page whose only defect is a missing timestamp.
     */
    public function diffForHumans(mixed $date): string
    {
        if (! $date instanceof DateTimeInterface) {
            return '';
        }

        //the helper's signature is \DateTime, and DateTimeImmutable is not one — the
        //laminas rows hydrate as \DateTime, but nothing in the type system says so
        if (! $date instanceof DateTime) {
            $date = DateTime::createFromInterface($date);
        }

        return (string) $this->helpers->diffForHumans()->__invoke($date);
    }

    /**
     * A labelled field row — `<p><strong>ISBN</strong>: 978-…</p>` — or nothing when the
     * value is absent and the caller passed `displayOnlyIfNotNull`.
     *
     * Markup, and the helper escapes the value itself. It also reads the ACL for
     * `displayOnlyWithPermission`, which is how `publication-info` hides the admin-tags
     * row from a visitor who may not edit the publication: that check has to stay inside
     * the helper, or a template that forgets it leaks a moderator's tags onto a public
     * page.
     *
     * @param array<string, mixed> $options
     */
    public function formatField(string $label, mixed $value, array $options = []): string
    {
        return (string) $this->helpers->formatField()->__invoke($label, $value, $options);
    }

    /**
     * A language code as its name in the current locale — `de` → "German", "Alemán".
     * Plain text, so Twig escapes it.
     */
    public function languageName(mixed $language, ?string $inLanguage = null): string
    {
        if (! is_string($language) || '' === $language) {
            return '';
        }

        return $this->helpers->languageName()->__invoke($language, $inLanguage);
    }

    /**
     * A Markdown column rendered to HTML through the application's configured CommonMark
     * filter — the same one the laminas templates call, so a moderator's notes render
     * identically on both front controllers.
     *
     * Markup by definition: the whole point is to emit the tags the filter produced.
     * What keeps that safe is the filter's own configuration, not this binding.
     */
    public function markdown(mixed $value): string
    {
        if (! is_string($value) || '' === $value) {
            return '';
        }

        return (string) $this->helpers->markdown()->__invoke($value);
    }

    /**
     * The COinS span — an OpenURL context object in a `title` attribute, which is how
     * Zotero and friends pick a citation off the page. Invisible markup, and dropping it
     * would silently break every reference manager pointed at this site.
     *
     * @param array<string, mixed> $object
     */
    public function coins(string $entityType, array $object): string
    {
        return (string) $this->helpers->coins()->__invoke($entityType, $object);
    }

    /**
     * The schema.org `<script type="application/ld+json">` block for a publication.
     *
     * Emitted by the helper complete with its script tag, which is why this is markup
     * rather than something the template wraps: the helper decides whether there is
     * anything to emit at all.
     *
     * @param array<string, mixed> $object
     */
    public function booksJsonLd(string $entityType, array $object): string
    {
        return (string) $this->helpers->booksJsonLd()->__invoke($entityType, $object);
    }

    /**
     * One of a publication's external links, as a button when its label is Borrow,
     * Purchase or Download and as a plain link otherwise.
     *
     * Carries the same caveat FormatUrlObject does and which this class's own docblock
     * records: the helper interpolates the URL itself unescaped. Pre-existing on the
     * laminas side, unchanged here, and worth a fix in both at once rather than a
     * divergence in one.
     *
     * @param array<string, mixed>|string $url
     */
    public function publicationUrlObject(array|string $url, bool $openInNewTab = true): string
    {
        return (string) $this->helpers->formatPublicationUrlObject()->__invoke($url, $openInNewTab);
    }

    /**
     * A postal address block. Markup — the helper emits `<p>` and `<br>` between the
     * lines it finds — and it escapes each column itself.
     *
     * @param array<string, mixed>|null $data
     */
    public function address(?array $data): string
    {
        if (null === $data) {
            return '';
        }

        return (string) $this->helpers->address()->__invoke($data);
    }

    /**
     * A country code as its name in the current locale, optionally preceded by the flag.
     * Markup when `$addFlag` is true, which is why it is declared safe; the helper
     * escapes the name either way.
     */
    public function countryName(
        mixed $code,
        bool $addFlag = false,
        string $commonOrOfficial = 'common'
    ): string {
        if (! is_string($code) || '' === $code) {
            return '';
        }

        return (string) $this->helpers->countryName()->__invoke($code, $addFlag, $commonOrOfficial);
    }

    /**
     * A date rendered only as precisely as it was recorded — `1914`, `October 1914` or
     * `October 18, 1914` — which is what the `foundationDatePrecision` column is for.
     * Plain text, so Twig escapes it.
     *
     * The day format defaults to **LONG** rather than the helper's own MEDIUM, because
     * the one caller — the association page's foundation date — passes LONG explicitly
     * and passing an IntlDateFormatter constant through Twig means `constant()`, which
     * is worse than a documented default.
     */
    public function datePrecision(
        mixed $date,
        mixed $precision = null,
        int $dayFormat = IntlDateFormatter::LONG
    ): string {
        if (! $date instanceof DateTimeInterface) {
            return '';
        }

        return (string) $this->helpers->datePrecisionFormat()->__invoke($date, $precision, $dayFormat);
    }

    /**
     * A Bootstrap tooltip span. Markup, and the helper escapes both the visible text and
     * the tooltip unless a caller opts out — no caller here does.
     */
    public function tooltip(
        string $text,
        string $tooltipText,
        bool $escape = true,
        string $placement = 'bottom'
    ): string {
        return (string) $this->helpers->tooltip()->__invoke($text, $tooltipText, $escape, $placement);
    }

    /**
     * A date with no time in the current locale's *long* form — `October 18, 1914`.
     * The `short_date` above is the changes table's; this is the association page's
     * suppression date, and the two really are different formats on the laminas side.
     */
    public function longDate(mixed $date): string
    {
        if (! $date instanceof DateTimeInterface) {
            return '';
        }

        return (string) $this->helpers->dateFormat()->__invoke(
            $date,
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE
        );
    }

    /** A byte count as `1.4 MB`, or the literal `n/a`. Plain text, so Twig escapes it. */
    public function fileSize(mixed $bytes): string
    {
        return (string) $this->helpers->fileSize()->__invoke($bytes);
    }

    /**
     * The within-request messages, in the four namespaces and the order the laminas
     * layout renders them: error, warning, info, success. Empty on every page that
     * added none, which is all of them but the literature search.
     *
     * Markup, and the helper escapes each message itself — `autoEscape` is on.
     */
    public function nowMessages(): string
    {
        //`JTranslate\View\Helper\NowMessenger::__invoke()` builds and returns the markup
        //string, but carries the copied docblock of the flash messenger it was adapted
        //from — `@return FlashMessenger|PluginNowMessenger`. Held as mixed so the cast is
        //the code's claim rather than the annotation's.
        /** @var mixed $markup */
        $markup = $this->helpers->nowMessenger()->__invoke();

        return is_string($markup) ? $markup : '';
    }

    /**
     * TwbBundle's Bootstrap label. Markup, and it escapes both the text and the class
     * attribute itself — which is why the space inside `class="label-info label"`
     * arrives as `&#x20;` and why this is `is_safe: html` rather than escaped again.
     */
    public function label(string $text, string $class = ''): string
    {
        //TwbBundleLabel::__invoke() returns the helper itself when called with no
        //arguments, which is the fluent form nothing here uses; with a $text it always
        //returns the rendered markup. Narrowed rather than cast, so that the fluent
        //return can never be stringified into "the object" on a page.
        $markup = $this->helpers->label()->__invoke($text, $class);

        return is_string($markup) ? $markup : '';
    }

    /**
     * SionModel\Text\Text::truncate, reused rather than reimplemented: it is static,
     * needs no view, and test/Unit/TextTest already covers its edge cases. The changes
     * table truncates old and new values to 150 characters.
     */
    public function truncate(mixed $text, int $length = 100): string
    {
        if (! is_string($text)) {
            return null === $text ? '' : (string) (is_scalar($text) ? $text : '');
        }

        return (string) Text::truncate($text, $length);
    }

    /**
     * Whether the general path may format this type at all — what the dispatcher macro
     * asks before falling through to `format_entity`. Not `is_safe`: it answers a
     * boolean.
     */
    public function formatsEntityGenerally(string $entityType): bool
    {
        return $this->entityFormatter()->handles($entityType);
    }

    private function entityFormatter(): EntityFormatter
    {
        return $this->entityFormatter ??= new EntityFormatter(
            $this->laminas,
            $this->helpers,
            $this->urls,
            //the pencil and the translator come from here so the markup and the
            //translator lookup exist once; see EntityFormatter's constructor docblock
            $this->editPencil(...),
            $this->editPencilForRoute(...),
            $this->translate(...)
        );
    }

    /**
     * The four flash namespaces the layout renders, in its order and with its
     * formats. Session-backed, so these are messages a *laminas* action set before
     * redirecting here; nothing on a ported route can add one yet.
     */
    public function flashMessages(): string
    {
        $flash = $this->helpers->flashMessenger();
        $flash->setMessageOpenFormat(
            '<div%s>
         <button type="button" class="close" data-dismiss="alert" aria-hidden="true">
             &times;
         </button>
         '
        )
            ->setMessageSeparatorString('</br>')
            ->setMessageCloseString('</div>');

        return (string) $flash->render('error', ['alert', 'alert-dismissable', 'alert-danger'])
            . (string) $flash->render('info', ['alert', 'alert-dismissable', 'alert-info'])
            . (string) $flash->render('default', ['alert', 'alert-dismissable', 'alert-warning'])
            . (string) $flash->render('success', ['alert', 'alert-dismissable', 'alert-success']);
    }

    /** @return array<string, Entity> */
    private function entities(): array
    {
        if (null !== $this->entities) {
            return $this->entities;
        }

        /** @var EntitiesService $service */
        $service = $this->laminas->get(EntitiesService::class);

        return $this->entities = $service->getEntities();
    }
}

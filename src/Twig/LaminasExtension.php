<?php

declare(strict_types=1);

namespace App\Twig;

use App\Laminas\EntityFormatter;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Laminas\ViewHelpers;
use Laminas\I18n\Translator\TranslatorInterface;
use SionModel\Entity\Entity;
use SionModel\Service\EntitiesService;
use Throwable;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

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
    /** @var array<string, Entity>|null */
    private ?array $entities = null;
    private ?EntityFormatter $entityFormatter = null;

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly ViewHelpers $helpers,
        private readonly RouteUrl $urls
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
            new TwigFunction('formats_entity_generally', $this->formatsEntityGenerally(...)),
            new TwigFunction('flash_messages', $this->flashMessages(...), $html),
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

    public function translate(string $message, string $domain = 'default'): string
    {
        /** @var TranslatorInterface $translator */
        $translator = $this->laminas->get('MvcTranslator');

        return $translator->translate($message, $domain);
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
     * reaches instead of the `editRouteKeyField` branch above (blog-post, text).
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

<?php

declare(strict_types=1);

namespace App\Laminas;

use Closure;
use DateTimeInterface;
use IntlDateFormatter;
use LogicException;
use SionModel\Entity\Entity;
use SionModel\Service\EntitiesService;
use Throwable;

use function count;
use function htmlspecialchars;
use function is_array;
use function is_bool;
use function is_int;
use function is_scalar;
use function is_string;
use function sprintf;
use function strlen;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * SionModel\View\Helper\FormatEntity's general path, reimplemented against
 * App\Laminas\RouteUrl.
 *
 * The original cannot be called from a Symfony-served route: it ends in
 * `$this->view->url()`, which wants a RouteMatch off an MvcEvent that does not exist.
 * It is also the most-reused of the five unavailable helpers, so reproducing it is
 * what unblocks the remaining admin pages rather than just the one this was written
 * for.
 *
 * ## What "the general path" means, and what dispatches around it
 *
 * `formatEntity` resolves to **Schoenstatt\View\Helper\FormatEntity**, not SionModel's
 * — the Schoenstatt module registers the same helper name and wins the merge. That
 * subclass switches on the entity type first and only then defers:
 *
 *     person       -> formatPerson       (Schoenstatt\View\Helper\FormatPerson)
 *     association  -> formatAssociation  (Schoenstatt\View\Helper\FormatAssociation)
 *     role         -> inline markup, with the `label` helper
 *     everything else -> SionModel\View\Helper\FormatEntity::__invoke()
 *
 * and SionModel's own `__invoke()` defers once more for any spec carrying
 * `formatViewHelper`, which in this application is only `publication` ->
 * formatPublication.
 *
 * This class is the "everything else" branch **plus** the two special cases that need
 * no Twig macro: `role` (formatRole) and `publication` (formatPublication). `person` and
 * `association` stay out of it, because they were already reproduced as macros for the
 * shrine tables and a Twig function cannot call a macro — the dispatch to those lives in
 * that file's `entity()` macro, and this class refuses them so the two lists cannot
 * silently disagree.
 *
 * Adding role and publication is what made /sm/view-changes portable: its entity column
 * takes whatever type the change row names, and `sch_changes` holds 18,243 publication
 * and 1,317 role rows.
 *
 * ## Route permission checking is ON in this application
 *
 * `sion_model.route_permission_checking_enabled` is `true` in
 * config/autoload/sionmodel.global.php, so `isActionAllowed()` really runs — a link is
 * suppressed when the viewer may not reach its route, and again when the entity's own
 * `aclResourceIdField` denies them. Both are reproduced. Getting this wrong in the
 * permissive direction would show every viewer a link to a page they cannot open;
 * getting it wrong in the restrictive direction would silently drop links for
 * moderators. It is not a detail that can be left out on the grounds that the pages
 * are already behind a guard.
 *
 * Escaping is `htmlspecialchars` with the flags Laminas\Escaper\Escaper uses for
 * `escapeHtml`, because that is what the original's `$this->view->escapeHtml()` is.
 */
final class EntityFormatter
{
    /**
     * Types whose laminas formatting is a specialized helper this class does not
     * reproduce. Formatting them by the general rules would silently lose the flag,
     * the label markup and the name composition each of them applies.
     */
    private const SPECIALIZED = [
        //Schoenstatt\View\Helper\FormatEntity's own switch. These two need a URL built
        //from more than a single key, and both were already reproduced as Twig macros
        //for the shrine tables — so the dispatch to them lives in Twig, where a macro
        //can be called, and this class refuses them.
        'person'      => 'the person() macro in templates/schoenstatt/_entity-format.html.twig',
        'association' => 'the association() macro in templates/schoenstatt/_entity-format.html.twig',
    ];

    /** @var array<string, Entity>|null */
    private ?array $entities = null;

    /**
     * The edit pencil and the translator arrive as closures rather than as a
     * LaminasExtension, and that is what keeps the dependency one-directional: the
     * extension owns this class (it is what exposes `format_entity` to Twig), so this
     * class must not reach back for it. Two functions is also a smaller contract than a
     * Twig extension, and it is exactly what is shared — the pencil markup exists once,
     * in LaminasExtension::editPencil(), rather than being written a second time here.
     *
     * @param Closure(string, int|string|null): string    $pencilRenderer      the editRouteKeyField form
     * @param Closure(string, array<string, mixed>): string $routePencilRenderer the editRoute+params form
     * @param Closure(string): string                     $translator
     */
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly ViewHelpers $helpers,
        private readonly RouteUrl $urls,
        private readonly Closure $pencilRenderer,
        private readonly Closure $routePencilRenderer,
        private readonly Closure $translator
    ) {
    }

    /** True when this class may format the type at all — the Twig dispatcher asks first. */
    public function handles(string $entityType): bool
    {
        return ! isset(self::SPECIALIZED[$entityType]);
    }

    /**
     * @param array<string, mixed> $data the entity row, as SionTable returns it
     * @param array{displayFlag?: bool, displayAsLink?: bool, displayEditPencil?: bool,
     *              failSilently?: bool, displayInactiveLabel?: bool} $options
     */
    public function format(string $entityType, array $data, array $options = []): string
    {
        if (isset(self::SPECIALIZED[$entityType])) {
            throw new LogicException(sprintf(
                'Entity type "%s" is formatted by a specialized laminas helper; use %s instead of the '
                . 'general path. See App\Laminas\EntityFormatter.',
                $entityType,
                self::SPECIALIZED[$entityType]
            ));
        }

        $isDeleted = (bool) ($data['isDeleted'] ?? false);

        //Dispatched before the general path, and **the two are gated differently** —
        //which is not a subtlety worth smoothing over, because it is visible on the page.
        //
        //Schoenstatt\View\Helper\FormatEntity switches on the entity type at the very top
        //of __invoke(), before anything looks at isDeleted. So a *deleted* role still gets
        //the role branch, and that branch's pencil defaults to on. SionModel's own
        //__invoke() is where `! $isDeleted` guards the formatViewHelper deferral, so a
        //deleted publication falls through to the general path instead.
        //
        //Measured, not reasoned: getting this backwards left exactly one row of 500
        //different from the laminas rendering — a deleted role that had lost its edit
        //pencil.
        if ('role' === $entityType) {
            return $this->formatRole($data, $options);
        }
        if ('publication' === $entityType && ! $isDeleted) {
            return $this->formatPublication($data, $options);
        }

        $spec = $this->entities()[$entityType] ?? null;
        //the original throws unless failSilently, which defaults *on* at every call
        //site in this application
        if (null === $spec) {
            if ($options['failSilently'] ?? true) {
                return '';
            }

            throw new LogicException('Unknown entity type passed: ' . $entityType);
        }

        $options = $this->defaults($options, $isDeleted);

        $keyField  = self::specString($spec, 'entityKeyField');
        $nameField = self::specString($spec, 'nameField');
        if (null === $keyField || ! isset($data[$keyField])) {
            return $this->failed($options, 'Id field not set for entity ' . $entityType);
        }
        if (null === $nameField || ! isset($data[$nameField])) {
            return $this->failed($options, 'Name field not set for entity ' . $entityType);
        }

        $markup = '';
        $markup .= $this->flag($spec, $data, $options['displayFlag']);
        $name    = $this->name($spec, $data[$nameField]);

        $markup .= $options['displayAsLink']
            ? $this->wrapAsLink($entityType, $spec, $data, $this->escape($name))
            : $this->escape($name);

        if ($options['displayEditPencil']) {
            $markup .= $this->editPencil($entityType, $spec, $data);
        }
        if ($options['displayInactiveLabel']) {
            $markup .= $this->inactiveLabel($data);
        }

        return $markup;
    }

    /**
     * Schoenstatt\View\Helper\FormatEntity's `role` branch.
     *
     * Two option names differ from every other branch and are **not** a slip to tidy up:
     * that switch reads `editPencil` and `showLabel`, while SionModel's general path
     * reads `displayEditPencil`. So `changes-table.phtml`, which passes
     * `displayEditPencil`, does *not* turn the pencil off for a role — it falls back to
     * the default `true`. Reproduced as written; renaming the keys here would change what
     * the page shows.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    private function formatRole(array $data, array $options): string
    {
        $markup = $this->escape((string) ($data['formattedRoleTitle'] ?? ''));

        if ((bool) ($options['editPencil'] ?? true)) {
            $id = $data['roleId'] ?? null;
            if (is_int($id) || is_string($id)) {
                $markup .= $this->pencil('role', $id);
            }
        }

        if ((bool) ($options['showLabel'] ?? true)) {
            if ((bool) ($data['isMainRole'] ?? false)) {
                $markup .= '&nbsp;' . $this->label('Main role', 'label-primary');
            }
            if ((bool) ($data['isMainContact'] ?? false)) {
                $markup .= '&nbsp;' . $this->label('Main contact', 'label-info');
            }
            if (isset($data['isActive']) && ! $data['isActive']) {
                $markup .= '&nbsp;' . $this->label('Inactive', 'label-warning');
            }
        }

        return $markup;
    }

    /**
     * Books\View\Helper\FormatPublication, `display => title` only.
     *
     * That is the default and the only mode reachable from a page this side serves:
     * every other mode (`authors`, `translators`, `edition`, `disambiguatingTitle`) is
     * chosen by an explicit `display` option, and the two callers here —
     * changes-table.phtml and data-problems.phtml — pass none. Rather than reproduce four
     * unreachable branches, an explicit `display` raises, so the first page that needs
     * one finds out at the call site instead of silently getting the title.
     *
     * The five defaults that follow from `display => title`: link on, edit pencil on
     * (unless the caller says otherwise), hand-checked/data-source/merged icons on,
     * language and resource labels off.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    private function formatPublication(array $data, array $options): string
    {
        $display = $options['display'] ?? 'title';
        if ('title' !== $display) {
            throw new LogicException(sprintf(
                'Books\View\Helper\FormatPublication\'s "%s" display mode is not reproduced; only '
                . '"title" is. See App\Laminas\EntityFormatter::formatPublication().',
                is_string($display) ? $display : 'non-string'
            ));
        }

        //the original's own guard, and it comes before everything: too little to show
        if (! isset($data['publicationId'], $data['title'])) {
            return '';
        }

        $title  = (string) $data['title'];
        $markup = '';

        //link when both halves of the URL are present; the original checks each
        if (isset($data['identifier'], $data['slug'])) {
            $markup .= sprintf(
                '<a href="%s">%s</a>',
                $this->urls->path('publication', ['sw_id' => $data['identifier'], 'slug' => $data['slug']]),
                $this->escape($title)
            );
        } else {
            $markup .= $this->escape($title);
        }

        //`isset($data['identifier'])` only — the permission check lives inside the pencil
        if ((bool) ($options['displayEditPencil'] ?? true) && isset($data['identifier'])) {
            $id = $data['identifier'];
            if (is_int($id) || is_string($id)) {
                $markup .= $this->pencil('publication', $id);
            }
        }

        //three status icons, each a plain truthiness/isset test in the original
        if (! empty($data['isRevisedWithBookInHand'])) {
            $markup .= $this->icon('fa-check-circle-o fa-3 text-success', 'Information has been hand checked');
        }
        if (isset($data['dataSource'])) {
            $markup .= $this->icon('fa-database', 'This row comes from an external data source');
        }
        if (isset($data['mergedIntoPublicationId'])) {
            $markup .= $this->icon('fa-sign-in', 'This row has been merged into the main corpus');
        }

        return $markup;
    }

    /** The status-icon markup FormatPublication emits, with its translated tooltip. */
    private function icon(string $classes, string $tooltip): string
    {
        return sprintf(
            '&nbsp;<span class="fa %s" title="%s"></span>',
            $classes,
            $this->translate($tooltip)
        );
    }

    /**
     * TwbBundle's label helper, which escapes both the text and the class attribute —
     * hence the `&#x20;` between the two class names in its output.
     *
     * `render()` rather than `__invoke()`: the latter returns **the helper itself** when
     * handed an empty message, so its return type is `string|TwbBundleAlert` and casting
     * it would hide that. Every call here passes a literal, so render() is both the
     * honest entry point and the correctly typed one.
     */
    private function label(string $text, string $class): string
    {
        return $this->helpers->label()->render($text, $class);
    }

    /**
     * The original's defaults, in its order — and its override for a deleted row.
     *
     * Note `$options['displayEditPencil'] - false;` in the original: a stray `-`
     * where a `=` was meant, so the pencil is *not* actually turned off for a deleted
     * entity. Reproduced as written, because a port is not the place to change what
     * the page shows; the two callers that care pass `displayEditPencil` explicitly
     * anyway (`changes-table.phtml` sends `! isDeleted`).
     *
     * @param array<string, mixed> $options
     * @return array{displayFlag: bool, displayAsLink: bool, displayEditPencil: bool,
     *               failSilently: bool, displayInactiveLabel: bool}
     */
    private function defaults(array $options, bool $isDeleted): array
    {
        $resolved = [
            'displayFlag'          => (bool) ($options['displayFlag'] ?? true),
            'displayAsLink'        => (bool) ($options['displayAsLink'] ?? true),
            'displayEditPencil'    => (bool) ($options['displayEditPencil'] ?? true),
            'failSilently'         => (bool) ($options['failSilently'] ?? true),
            'displayInactiveLabel' => (bool) ($options['displayInactiveLabel'] ?? false),
        ];
        if ($isDeleted) {
            $resolved['displayAsLink'] = false;
            $resolved['displayFlag']   = false;
            //displayEditPencil deliberately untouched: see the docblock
        }

        return $resolved;
    }

    /** @param array{failSilently: bool} $options */
    private function failed(array $options, string $message): string
    {
        if ($options['failSilently']) {
            return '';
        }

        throw new LogicException($message);
    }

    /** @param array<string, mixed> $data */
    private function flag(Entity $spec, array $data, bool $display): string
    {
        $field = self::specString($spec, 'countryField');
        if (! $display || null === $field || ! isset($data[$field])) {
            return '';
        }
        $country = $data[$field];
        //the original's own guard: a two-character code, nothing else
        if (! is_string($country) || 2 !== strlen($country)) {
            return '';
        }

        return $this->helpers->flag()->__invoke($country) . '&nbsp;';
    }

    /**
     * A DateTime name field is formatted as a medium date with no time, which is what
     * `$this->view->dateFormat($v, MEDIUM, NONE)` does; IntlDateFormatter is what that
     * helper wraps. Otherwise the raw value, translated when the spec says the name is
     * translatable.
     */
    private function name(Entity $spec, mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            $formatter = new IntlDateFormatter(null, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);

            return (string) $formatter->format($value);
        }

        $name = is_scalar($value) ? (string) $value : '';

        return $spec->nameFieldIsTranslatable ? $this->translate($name) : $name;
    }

    /**
     * FormatEntity::wrapAsLink(), minus the two branches no spec in this application
     * uses.
     *
     * `showRouteParams` and `defaultRouteParams` are not reproduced because no entity
     * spec here sets either — every one of the 24 uses the showRouteKey /
     * showRouteKeyField pair. They are asserted absent rather than assumed:
     * test/Integration/EntityFormatterParityTest walks every spec and fails if one
     * grows a params array, which is the point at which this method needs the extra
     * branch rather than quietly dropping the link.
     *
     * @param array<string, mixed> $data
     */
    private function wrapAsLink(string $entityType, Entity $spec, array $data, string $linkText): string
    {
        $route = self::specString($spec, 'showRoute');
        if (null === $route || ! $this->isActionAllowed('show', $entityType, $spec, $data)) {
            return $linkText;
        }

        //the original's branch order: showRouteKey/showRouteKeyField first, then
        //defaultRouteParams. showRouteParams comes before both in the original and is
        //omitted here because no spec sets it — EntityFormatterTest fails if one does.
        $key   = self::specString($spec, 'showRouteKey');
        $field = self::specString($spec, 'showRouteKeyField');
        if (null !== $key && null !== $field && isset($data[$field])) {
            return sprintf('<a href="%s">%s</a>', $this->urls->path($route, [$key => $data[$field]]), $linkText);
        }

        //text and composition reach this: they carry no showRouteKey and
        //name their route parameters as a map of routeParam => entityField instead
        $params = $this->routeParams($spec, 'defaultRouteParams', $data);
        if (null !== $params) {
            return sprintf('<a href="%s">%s</a>', $this->urls->path($route, $params), $linkText);
        }

        return $linkText;
    }

    /**
     * A `routeParam => entityField` map resolved against the row, or **null** when any
     * field is missing.
     *
     * All-or-nothing is the original's rule, not a simplification: it skips a missing
     * field with a `@todo log this` and then only uses the params if
     * `count($params) === count($spec->…)`. So a partially resolvable map produces no
     * link at all rather than a URL with a hole in it.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function routeParams(Entity $spec, string $property, array $data): ?array
    {
        /** @var mixed $map */
        $map = $spec->$property;
        if (! is_array($map) || [] === $map) {
            return null;
        }

        $params = [];
        foreach ($map as $routeParam => $entityField) {
            if (! is_string($routeParam) || ! is_string($entityField) || ! isset($data[$entityField])) {
                continue;
            }
            $params[$routeParam] = $data[$entityField];
        }

        return count($params) === count($map) ? $params : null;
    }

    /**
     * The `editRouteKeyField` branch of the original's edit-pencil block, which is the
     * only one any spec here reaches — and it delegates to the same `editPencil`
     * reproduction the shrine tables use, so there is one copy of that markup.
     *
     * @param array<string, mixed> $data
     */
    private function editPencil(string $entityType, Entity $spec, array $data): string
    {
        $editRoute = self::specString($spec, 'editRoute');
        if (null === $editRoute) {
            return '';
        }

        //the original's order, minus its first branch (editRouteParams, which no spec
        //sets): editRouteKeyField, then defaultRouteParams
        $field = self::specString($spec, 'editRouteKeyField');
        if (null !== $field && isset($data[$field])) {
            $id = $data[$field];

            //int|string only: a bool or float key would assemble a nonsense URL
            return is_int($id) || is_string($id) ? $this->pencil($entityType, $id) : '';
        }

        //text: no editRouteKeyField, so the pencil is built from the
        //edit route plus the default params — SionModel\View\Helper\EditPencilNew
        $params = $this->routeParams($spec, 'defaultRouteParams', $data);

        return null === $params ? '' : ($this->routePencilRenderer)($editRoute, $params);
    }

    /** @param array<string, mixed> $data */
    private function inactiveLabel(array $data): string
    {
        $active = $data['isActive'] ?? $data['active'] ?? null;
        if (! is_bool($active) || $active) {
            return '';
        }

        return ' <span class="label label-warning">' . $this->translate('Inactive') . '</span>';
    }

    /**
     * FormatEntity::isActionAllowed(), with route permission checking on — which it is
     * in this application.
     *
     * Two checks, in the original's order: the route the action would reach, and then
     * the entity's own ACL resource id. The original wraps its `plugin('isAllowed')`
     * lookup in a try/catch and allows when the plugin is missing; here the plugin is
     * always present, so what remains of that leniency is the catch around the ACL
     * call itself, matching what LaminasExtension::editPencil() already does.
     *
     * @param array<string, mixed> $data
     */
    private function isActionAllowed(string $action, string $entityType, Entity $spec, array $data): bool
    {
        $routeProperty = Entity::$actionRouteProperties[$action] ?? null;
        if (is_string($routeProperty)) {
            $route = self::specString($spec, $routeProperty);
            if (null !== $route && ! $this->isAllowed('route/' . $route)) {
                return false;
            }
        }

        $resourceField = self::specString($spec, 'aclResourceIdField');
        if (null === $resourceField || ! isset($data[$resourceField])) {
            return true;
        }
        $resource = $data[$resourceField];
        if (! is_string($resource)) {
            return true;
        }

        $permissionProperty = Entity::$isActionAllowedPermissionProperties[$action] ?? null;
        $permission         = is_string($permissionProperty) ? self::specString($spec, $permissionProperty) : null;

        return $this->isAllowed($resource, $permission);
    }

    private function isAllowed(string $resource, ?string $privilege = null): bool
    {
        try {
            return (bool) $this->helpers->isAllowed()->__invoke($resource, $privilege);
        } catch (Throwable) {
            //what the original assumes when the ACL cannot answer: no route
            //permissions are configured, so allow
            return true;
        }
    }

    private function pencil(string $entityType, int|string $id): string
    {
        return ($this->pencilRenderer)($entityType, $id);
    }

    private function translate(string $message): string
    {
        return ($this->translator)($message);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * A spec property that SionModel\Entity\Entity documents as `string` and leaves
     * **null** when the entity does not use it — 22 of the 24 specs have no
     * `countryField`, for instance. The properties are untyped, so the phpdoc is the
     * only type information there is and it is wrong; read dynamically, the value is
     * `mixed` and the null check below is the honest one. Centralized here so that
     * correction is stated once instead of fought with an inline suppression at every
     * read.
     *
     * Empty string is treated as absent, which is what the original's `! $spec->foo`
     * truthiness tests do.
     */
    private static function specString(Entity $spec, string $property): ?string
    {
        /** @var mixed $value */
        $value = $spec->$property;

        return is_string($value) && '' !== $value ? $value : null;
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

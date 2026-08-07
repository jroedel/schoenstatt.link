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
 * This class is the "everything else" branch. `person` and `association` are
 * reproduced already, as the macros in templates/schoenstatt/_entity-format.html.twig,
 * and the dispatch between them lives in that file's `entity()` macro — in Twig
 * because that is the layer that can reach a macro. `role` and `publication` are
 * **not** reproduced and this class refuses them loudly rather than formatting them by
 * the general rules, which would produce plausible but wrong markup. Neither is
 * reachable from the page this was written for: `problem_specifications` across all
 * modules names only book, collection, library, association and person, so the
 * refusal is a guard for the next port and not a live gap.
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
        //Schoenstatt\View\Helper\FormatEntity's own switch — reproduced as Twig macros
        'person'      => 'the person() macro in templates/schoenstatt/_entity-format.html.twig',
        'association' => 'the association() macro in templates/schoenstatt/_entity-format.html.twig',
        //not reproduced anywhere yet
        'role'        => 'Schoenstatt\View\Helper\FormatEntity\'s `role` branch, which needs the `label` helper',
        'publication' => 'Books\View\Helper\FormatPublication',
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

        $spec = $this->entities()[$entityType] ?? null;
        //the original throws unless failSilently, which defaults *on* at every call
        //site in this application
        if (null === $spec) {
            if ($options['failSilently'] ?? true) {
                return '';
            }

            throw new LogicException('Unknown entity type passed: ' . $entityType);
        }

        $isDeleted = (bool) ($data['isDeleted'] ?? false);
        $options   = $this->defaults($options, $isDeleted);

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

        //blog-post, text and composition reach this: they carry no showRouteKey and
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

        //blog-post and text: no editRouteKeyField, so the pencil is built from the
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

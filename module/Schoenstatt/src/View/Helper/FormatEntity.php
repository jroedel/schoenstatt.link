<?php
namespace Schoenstatt\View\Helper;

use Closure;
use SionModel\View\Escape;

/**
 * This application's `formatEntity`: SionModel's, with `person`, `association` and `role`
 * taken over before the general path sees them.
 *
 * Ported here alongside its parent, and not because it was on batch 4's list: it *extends*
 * `SionModel\View\Helper\FormatEntity`, so the moment that class stopped being a
 * `Laminas\View\Helper\AbstractHelper` this one lost `$this->view` too — a subclass is as
 * interlocked with the cluster as any caller. Its three own collaborators (`formatPerson`,
 * `formatAssociation`, `label`) are injected; the rest it inherits.
 */
class FormatEntity extends \SionModel\View\Helper\FormatEntity
{
    protected $associationTypeLabels = [];

    /**
     * @param mixed[] $associationTypeLabels
     * @param Closure(array, array): string|null $formatPerson
     * @param Closure(array, array): string|null $formatAssociation
     * @param Closure(string, string): string|null $label
     * @param array<string, Closure> $formatHelpers see the parent
     */
    public function __construct(
        $entityService,
        $associationTypeLabels,
        $routePermissionCheckingEnabled = false,
        ?Closure $flag = null,
        ?Closure $dateFormat = null,
        ?Closure $translate = null,
        ?Closure $url = null,
        ?Closure $editPencil = null,
        ?Closure $editPencilNew = null,
        ?Closure $isAllowed = null,
        array $formatHelpers = [],
        private readonly ?Closure $formatPerson = null,
        private readonly ?Closure $formatAssociation = null,
        private readonly ?Closure $label = null
    ) {
        $this->associationTypeLabels = $associationTypeLabels;
        parent::__construct(
            $entityService,
            $routePermissionCheckingEnabled,
            $flag,
            $dateFormat,
            $translate,
            $url,
            $editPencil,
            $editPencilNew,
            $isAllowed,
            $formatHelpers
        );
    }

    /**
     * @param string $entityType
     * @param array $data
     * @param array $options
     */
    public function __invoke($entityType, $data, array $options = [])
    {
        $editPencilOption = isset($options['editPencil']) ? (bool)$options['editPencil'] : true;
        $showLabelOption = isset($options['showLabel']) ? (bool)$options['showLabel'] : true;
        $finalMarkup = '';
        switch ($entityType) {
            case 'person':
                return null !== $this->formatPerson ? ($this->formatPerson)($data, $options) : '';
            case 'association':
                return null !== $this->formatAssociation ? ($this->formatAssociation)($data, $options) : '';
            case 'role':
                $finalMarkup = Escape::html((string) $data['formattedRoleTitle']);
                if ($editPencilOption) {
                    $finalMarkup .= $this->renderEditPencil('role', $data['roleId']);
                }
                if ($showLabelOption && null !== $this->label) {
                    if (isset($data['isMainRole']) && $data['isMainRole']) {
                        $finalMarkup .= '&nbsp;' . ($this->label)('Main role', 'label-primary');
                    }
                    if (isset($data['isMainContact']) && $data['isMainContact']) {
                        $finalMarkup .= '&nbsp;' . ($this->label)('Main contact', 'label-info');
                    }
                    if (isset($data['isActive']) && ! $data['isActive']) {
                        $finalMarkup .= '&nbsp;' . ($this->label)('Inactive', 'label-warning');
                    }
                }
                return $finalMarkup;
            default:
                return parent::__invoke($entityType, $data, $options);
        }
    }
}

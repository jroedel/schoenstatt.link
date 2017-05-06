<?php
namespace Schoenstatt\View\Helper;

class FormatEntity extends \SionModel\View\Helper\FormatEntity
{
    protected $associationTypeLabels = [];

    /**
     *
     * @param mixed[] $config The 'schoenstatt' config key
     */
    public function __construct($entityService, $associationTypeLabels)
    {
        $this->associationTypeLabels = $associationTypeLabels;
        parent::__construct($entityService);
    }

    /**
     * @todo Can we delete this? YES!!!
     * @param array $person
     * @param array $options
     *
     */
    public function __invoke($entityType, $data, array $options = [])
    {
    	$editPencilOption = isset($options['editPencil']) ? (bool)$options['editPencil'] : true;
        $showLabelOption = isset($options['showLabel']) ? (bool)$options['showLabel'] : true;
        $finalMarkup = '';
        switch ($entityType) {
            case 'person':
                return $this->view->formatPerson($data, $options);
                break;
            case 'association':
                return $this->view->formatAssociation($data, $options);
                break;
            case 'role':
                $finalMarkup = $this->view->escapeHtml($this->view->translate($data['roleTitle']));
                if ($editPencilOption) {
                    $finalMarkup .= $this->view->editPencil('role', $data['roleId']);
                }
                if ($showLabelOption) {
                    if ($data['isMainRole']) {
                        $finalMarkup .= '&nbsp;' . $this->view->label('Main role', 'label-primary');
                    }
                    if (!$data['isActive']) {
                        $finalMarkup .= '&nbsp;' . $this->view->label('Inactive', 'label-warning');
                    }
                }
                return $finalMarkup;
                break;
            default:
                return parent::__invoke($entityType, $data, $options);
            break;
        }
    }
}

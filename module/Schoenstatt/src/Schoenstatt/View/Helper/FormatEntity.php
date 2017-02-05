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
     * @todo Can we delete this?
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
                if (!is_null($data['associationId'])) {

                    $finalMarkup = str_repeat('&nbsp;', $data['heirarchyLevel'] -1).str_repeat('↪', $data['heirarchyLevel'] -1);
    				$finalMarkup .= '<a href="'. $this->view->url('associations/association',
				        ['association_id' => $data['associationId']]).'">';
					if ($data['isNameTranslateable']) {
					   $finalMarkup .= $this->view->translate($data['name']);
					} else {
					   $finalMarkup .= $data['name'];
                    }
                    $finalMarkup .= '</a>';

                    if ($showLabelOption && isset($this->associationTypeLabels[$data['kind']])) {
                        $finalMarkup .= '&nbsp;'.$this->view->label($this->associationTypeLabels[$data['kind']],
                            'label-info');
                    }
                    if ($editPencilOption) {
                        $finalMarkup .= $this->view->editPencil('association', $data['associationId']);
                    }
                }
                return $finalMarkup;
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

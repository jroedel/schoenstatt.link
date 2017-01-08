<?php
// Application/View/Helper/FormatEntity.php

namespace Schoenstatt\View\Helper;

use Zend\View\Helper\AbstractHelper;

class FormatEntity extends AbstractHelper
{
    public $associationLabels = [];

    /**
     *
     * @param mixed[] $config The 'schoenstatt' config key
     */
    public function __construct($config)
    {
        $associationLabels = [];
        foreach ($config['association_kinds'] as $key => $value) {
            if (is_array($value) && key_exists('label', $value)) {
                $associationLabels[$key] = $value['label'];
            }
        }
        $this->associationLabels = $associationLabels;
    }

    /**
     *
     * @param array $person
     * @param array $options
     *
     */
    public function __invoke($entityType, $data, $options = [])
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

                    if ($showLabelOption && isset($this->associationLabels[$data['kind']])) {
                        $finalMarkup .= '&nbsp;'.$this->view->label($this->associationLabels[$data['kind']],
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
                return $finalMarkup;
                break;
            default:
                throw new \InvalidArgumentException('Unsupported entity passed to FormatEntity');
            break;
        }
    }
}

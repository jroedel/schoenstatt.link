<?php
// Application/View/Helper/FormatEntity.php

namespace Schoenstatt\View\Helper;

use Zend\View\Helper\AbstractHelper;

class FormatEntity extends AbstractHelper
{

    /**
     *
     * @param array $person
     * @param array $options
     *
     */
    public function __invoke($entityType, $data, $options = [])
    {
    	$editPencilOption = isset($options['editPencil']) ? (bool)$options['editPencil'] : true;
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
                    if ($editPencilOption) {
                        $finalMarkup .= $this->view->editPencil('association', $data['associationId']);
                    }
                }
                return $finalMarkup;
                break;
            default:
                throw new \InvalidArgumentException('Unsupported entity passed to FormatEntity');
            break;
        }
    }
}

<?php
namespace Schoenstatt\View\Helper;

use Zend\Form\View\Helper\AbstractHelper;

class FormatAssociation extends AbstractHelper
{
    protected $associationTypeLabels = [];

    const DISPLAY_NAME = 'name';
    const DISPLAY_KIND = 'kind';

    /**
     * @param mixed[] $config The 'schoenstatt' config key
     */
    public function __construct($associationTypeLabels)
    {
        $this->associationTypeLabels = $associationTypeLabels;
    }

    /**
     * @param array $data
     * @param array $options
     */
    public function __invoke($data, array $options = [])
    {
        $display = isset($options['display']) ? $options['display'] : 'name';
    	$editPencilOption = isset($options['editPencil']) ? (bool)$options['editPencil'] : $display=='name';
    	$showLabelOption = isset($options['showLabel']) ? (bool)$options['showLabel'] : false;
    	$displayAsLink = isset($options['displayAsLink']) ? (bool)$options['displayAsLink'] : $display=='name';
        $finalMarkup = '';

        if (isset($data['isDeleted']) && $data['isDeleted']) {
            $text = $data['associationName'];
            $displayAsLink = false;
            $editPencilOption = false;
            $showLabelOption = false;
        } else {
            switch ($display) {
                case self::DISPLAY_NAME:
                    if ($data['formattedName']) {
                        $text = $data['formattedName'];
                    } else if ($data['isNameTranslateable']) {
                        $text = $this->view->translate($data['name'], 'Schoenstatt');
                    } else {
                        $text = $data['name'];
                    }
                    break;
                case self::DISPLAY_KIND:
                    if (key_exists($data['kind'], $this->associationTypeLabels)) {
                        $text = $this->associationTypeLabels[$data['kind']];
                    } else {
                        $text = $data['kind'];
                    }
                    $text = $this->view->translate($text);
                    break;
                default:
                    throw new \InvalidArgumentException("Invalid display parameter: $display");
                break;
            }
        }
        $text = $this->view->escapeHtml($text);

        //@todo check resources too
        $permissionToViewLink = $this->view->isAllowed('route/associations/association');

        if ($displayAsLink && $permissionToViewLink) {
            $url = $this->view->url('associations/association',
                ['association_id' => $data['associationId']]);
            $finalMarkup .= sprintf('<a href="%s">%s</a>',
                $url,
                    $text);
        } else {
            $finalMarkup .= $text;
        }
        if ($showLabelOption && isset($this->associationTypeLabels[$data['kind']])) {
            $finalMarkup .= '&nbsp;'.$this->view->label($this->associationTypeLabels[$data['kind']],
                'label-info');
        }
        if ($editPencilOption) {
            $finalMarkup .= $this->view->editPencil('association', $data['associationId']);
        }

        return $finalMarkup;
    }
}

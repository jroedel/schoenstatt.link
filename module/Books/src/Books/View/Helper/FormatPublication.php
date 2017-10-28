<?php
namespace Books\View\Helper;

use SionModel\View\Helper\FormatEntity;

class FormatPublication extends FormatEntity
{
    const DISPLAY_TITLE = 'title';
    const DISPLAY_AUTHORS = 'authors';
    const DISPLAY_EDITION = 'edition';

    /**
     *
     * @param array $data
     * @param array $options
     *
     * available options:
     *  'display' => $this::FRIENDLY_FIRST_FIRST, (see class consts)
     *  'flag' => true,
     *  'link' => true,
     *  'displayEditPencil' => false,
     */
    public function __invoke($entityType, $data, array $options = [])
    {
    	//if there's not enough info we won't do anything
    	if (!isset($data['publicationId']) || !isset($data['title'])) {
    		return '';
    	}

    	//set defaults
    	if (isset($options['display'])) {
    	    if ($options['display'] === self::DISPLAY_AUTHORS ||
	            $options['display'] === self::DISPLAY_TITLE ||
	            $options['display'] === self::DISPLAY_EDITION)
    	    {
    	        $displayOption = $options['display'];
    	    } else {
    	        $displayOption = self::DISPLAY_TITLE;
    	    }
    	} else {
    	    $displayOption = $this::DISPLAY_TITLE;
    	}
    	$linkOption        = isset($options['link']) ? (bool)$options['link'] : $displayOption == self::DISPLAY_TITLE;
    	$editPencilOption  = isset($options['displayEditPencil']) ? (bool)$options['displayEditPencil'] : $displayOption == self::DISPLAY_TITLE;
    	$showLanguageLabel = isset($options['displayLanguageLabel']) ? (bool)$options['displayLanguageLabel'] : false;
    	$showResourceLabel = isset($options['displayResourceLabel']) ? (bool)$options['displayResourceLabel'] : false;
    	$showHandChecked   = isset($options['displayHandChecked']) ? (bool)$options['displayHandChecked'] : $displayOption == self::DISPLAY_TITLE;

    	$finalMarkup = '';
    	$escapeMainText = true;
    	switch ($displayOption) {
    	    case self::DISPLAY_AUTHORS: //@todo make it work when we have real authors
    	        $mainText = $data['authors'];
    	       break;
    	    case self::DISPLAY_TITLE:
    	        $mainText = $data['title'];
    	        break;
    	    case self::DISPLAY_EDITION:
    	        $mainText = '';
    	        $escapeMainText = false;
    	        if (!is_null($data['bookEdition'])) {
    	            $mainText .= sprintf("<strong>%s</strong>", $this->view->escapeHtml($data['bookEdition']));
    	        }
    	        if (!is_null($data['copyrightYear'])) {
    	            if (strlen($mainText) > 0) {
    	                $mainText .= ', ';
    	            }
    	            $mainText .= $this->view->escapeHtml($data['copyrightYear']);
    	        }
    	        if (!is_null($data['publishingPlace'])) {
    	            if (strlen($mainText) > 0) {
    	                $mainText .= ' ';
    	            }
    	            $mainText .= $this->view->escapeHtml($data['publishingPlace']);
    	        }
    	}

    	if ($linkOption) {
//         	$linkFormat = '<a href="%s">%s</a>';
//         	$url = $this->view->url('publications/publication', array('publication_id' => $object['publicationId']));
        	if ($escapeMainText) {
        	   $mainText = $this->view->escapeHtml($mainText);
        	}
        	$finalMarkup .= $this->wrapAsLink('publication', $data, $mainText);
    	} else {
    	    if ($escapeMainText) {
    	       $finalMarkup .= $this->view->escapeHtml($mainText);
    	    } else {
    	        $finalMarkup .= $mainText;
    	    }
    	}
    	if ($showLanguageLabel) {
    	    if (!is_null($data['inLanguage'])) {
    	        $this->view->label()->setTranslatorTextDomain('Books');
    	        $finalMarkup .= ' '. $this->view->label($data['inLanguage'], 'label-info');
    	    }
    	}
    	if ($showResourceLabel) {
    	    static $resourceLabels;
    	    if (is_null($resourceLabels)) {
    	        $resourceLabels = [
    	            'publication_institute' => 'Institute',
    	            'publication_patres' => 'Patres',
    	        ];
    	    }
    	    if (array_key_exists($data['resourceId'], $resourceLabels)) {
    	        $this->view->label()->setTranslatorTextDomain('Books');
    	        $finalMarkup .= ' '. $this->view->label($resourceLabels[$data['resourceId']], 'label-info');
    	    }
    	}
//     	if ($showLabels) {
//     	    if (isset($person['labels']) && is_array($person['labels'])) {
//     	        foreach ($person['labels'] as $label) {
//     	           $finalMarkup .= ' '. $this->view->label($label, 'label-info');
//     	        }
//     	    }
//     	}
    	if ($editPencilOption) { //permissions are checked in editPencil
    		$finalMarkup .= $this->view->editPencil('publication', $data['publicationId']);
    	}
    	if ($showHandChecked && $data['isRevisedWithBookInHand']) {
    	    $finalMarkup .= sprintf('&nbsp;<span class="fa fa-check-circle-o fa-3 text-success" title="%s"></span>',
	            $this->view->translate('Information has been hand checked!'));
    	}
    	return $finalMarkup;
    }
}

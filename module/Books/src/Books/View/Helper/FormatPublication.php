<?php
namespace Books\View\Helper;

use Zend\View\Helper\AbstractHelper;

class FormatPublication extends AbstractHelper
{
    const DISPLAY_TITLE = 'title';
    const DISPLAY_AUTHORS = 'authors';
    const DISPLAY_EDITION = 'edition';

    /**
     *
     * @param array $object
     * @param array $options
     *
     * available options:
     *  'display' => $this::FRIENDLY_FIRST_FIRST, (see class consts)
     *  'flag' => true,
     *  'link' => true,
     *  'displayEditPencil' => false,
     */
    public function __invoke($entity, $object, $options = [])
    {
    	//if there's not enough info we won't do anything
    	if (!isset($object['publicationId']) || !isset($object['title'])) {
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
    	$linkOption = isset($options['link']) ? (bool)$options['link'] : $displayOption == self::DISPLAY_TITLE;
    	$editPencilOption = isset($options['displayEditPencil']) ? (bool)$options['displayEditPencil'] : $displayOption == self::DISPLAY_TITLE;
    	$showLanguageLabel = isset($options['displayLanguageLabel']) ? (bool)$options['displayLanguageLabel'] : false;

    	$finalMarkup = '';
    	$escapeMainText = true;
    	switch ($displayOption) {
    	    case self::DISPLAY_AUTHORS: //@todo make it work when we have real authors
    	        $mainText = $object['authors'];
    	       break;
    	    case self::DISPLAY_TITLE:
    	        $mainText = $object['title'];
    	        break;
    	    case self::DISPLAY_EDITION:
    	        $mainText = '';
    	        $escapeMainText = false;
    	        if (!is_null($object['bookEdition'])) {
    	            $mainText .= sprintf("<strong>%s</strong>", $this->view->escapeHtml($object['bookEdition']));
    	        }
    	        if (!is_null($object['copyrightYear'])) {
    	            if (strlen($mainText) > 0) {
    	                $mainText .= ', ';
    	            }
    	            $mainText .= $this->view->escapeHtml($object['copyrightYear']);
    	        }
    	        if (!is_null($object['publishingPlace'])) {
    	            if (strlen($mainText) > 0) {
    	                $mainText .= ' ';
    	            }
    	            $mainText .= $this->view->escapeHtml($object['publishingPlace']);
    	        }
    	}

    	if ($linkOption) {
        	$linkFormat = '<a href="%s">%s</a>';
        	$url = $this->view->url('publications/publication', array('publication_id' => $object['publicationId']));
        	if ($escapeMainText) {
        	   $finalMarkup .= sprintf($linkFormat, $url, $this->view->escapeHtml($mainText));
        	} else {
        	    $finalMarkup .= sprintf($linkFormat, $url, $mainText);
        	}
    	} else {
    	    if ($escapeMainText) {
    	       $finalMarkup .= $this->view->escapeHtml($mainText);
    	    } else {
    	        $finalMarkup .= $mainText;
    	    }
    	}
    	if ($showLanguageLabel) {
    	    if (!is_null($object['inLanguage'])) {
    	        $finalMarkup .= ' '. $this->view->label($object['inLanguage'], 'label-info');
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
    		$finalMarkup .= $this->view->editPencil('publication', $object['publicationId']);
    	}
    	return $finalMarkup;
    }
}

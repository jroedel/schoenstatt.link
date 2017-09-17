<?php
namespace Books\View\Helper;

use Zend\View\Helper\AbstractHelper;

class FormatField extends AbstractHelper
{
    const DISPLAY_TITLE = 'title';
    const DISPLAY_AUTHORS = 'authors';

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
    public function __invoke($label, $value, $options = [])
    {
//     	$linkOption = isset($options['link']) ? (bool)$options['link'] : $displayOption == self::DISPLAY_TITLE;
//     	$editPencilOption = isset($options['displayEditPencil']) ? (bool)$options['displayEditPencil'] : $displayOption == self::DISPLAY_TITLE;
//     	$showLanguageLabel = isset($options['displayLanguageLabel']) ? (bool)$options['displayLanguageLabel'] : false;

    	$displayOnlyIfNotNull = isset($options['displayOnlyIfNotNull']) ? (bool)$options['displayOnlyIfNotNull'] :
    	   false;
    	$wrapMarkup = isset($options['wrapMarkup']) ? $options['wrapMarkup'] :
    	   '<p>%s</p>';
    	$labelMarkup = isset($options['labelMarkup']) ? $options['labelMarkup'] :
    	   '<strong>%s</strong>: ';
    	$translateLabel = isset($options['translateLabel']) ? (bool)$options['translateLabel'] :
    	   true;

    	if ($displayOnlyIfNotNull && (is_null($value) || empty($value))) {
    	    return '';
    	}

    	if (isset($options['displayOnlyWithPermission']) && !is_null($options['displayOnlyWithPermission']) &&
    	    !$this->view->isAllowed($options['displayOnlyWithPermission'])
        ) {
    	    return '';
    	}

    	$finalMarkup = '';

    	if (!is_null($label)) {
    	    if ($translateLabel) {
    	        $label = $this->view->escapeHtml($this->view->translate($label));
    	    }
    	    $finalMarkup .= sprintf($labelMarkup, $this->view->escapeHtml($label));
    	}
//     	switch ($displayOption) {
//     	    case 'authors': //@todo make it work when we have real authors
//     	        $mainText = $object['authors'];
//     	       break;
//     	    case 'title':
//     	        $mainText = $object['title'];
//     	        break;
//     	}

    	if ($value instanceof \DateTime) {
            //@todo make format configurable
    	    $finalMarkup .= $this->view->dateFormat($value, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
    	} elseif (is_array($value)) {
    	    $finalMarkup .= implode(', ', $value);
    	} else {
    	   $finalMarkup .= $this->view->escapeHtml($value);
    	}

//     	if ($showLabels) {
//     	    if (isset($person['labels']) && is_array($person['labels'])) {
//     	        foreach ($person['labels'] as $label) {
//     	           $finalMarkup .= ' '. $this->view->label($label, 'label-info');
//     	        }
//     	    }
//     	}
//     	if ($editPencilOption) { //permissions are checked in editPencil
//     		$finalMarkup .= $this->view->editPencil('publication', $object['publicationId']);
//     	}

    	if (!is_null($wrapMarkup)) {
    	    $finalMarkup = sprintf($wrapMarkup, $finalMarkup);
    	}
    	return $finalMarkup;
    }
}

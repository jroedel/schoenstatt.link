<?php
// Application/View/Helper/FormatPersonName.php

namespace Schoenstatt\View\Helper;

use Zend\View\Helper\AbstractHelper;

class FormatPerson extends AbstractHelper
{
    const FULL_LAST_FIRST = 'full last first';
    const FULL_FIRST_FIRST = 'full first first';

    /**
     *
     * @param array $person
     * @param array $options
     *
     * available options:
     *  'display' => $this::FRIENDLY_FIRST_FIRST, (see class consts)
     *  'flag' => true,
     *  'link' => true,
     *  'editPencil' => false,
     */
    public function __invoke($person, $options = [])
    {
    	//if there's not enough info we won't do anything
    	if (!$person['personId'] || $person['personId']=='' || (!isset($person['firstName']) && !isset($person['lastName']) &&
    	   !is_null($person['firstName']) && !is_null($person['lastName']))) {
    		return '';
    	}

    	//set defaults
    	if (isset($options['display'])) {
    	    if ($options['display'] === $this::FULL_LAST_FIRST ||
    	        $options['display'] === $this::FULL_FIRST_FIRST)
    	    {
    	        $displayOption = $options['display'];
    	    } else {
                $displayOption = $this::FULL_LAST_FIRST;
    	    }
    	} else {
    	    $displayOption = $this::FULL_LAST_FIRST;
    	}
    	$countryOption = isset($options['flag']) ? (bool)$options['flag'] : true;
    	$linkOption = isset($options['link']) ? (bool)$options['link'] : true;
    	$editPencilOption = isset($options['editPencil']) ? (bool)$options['editPencil'] : true;
    	$showLabels = isset($options['showLabels']) ? (bool)$options['showLabels'] : false;

    	$finalMarkup = '';
    	if ($countryOption && $person['country']) {
    		$finalMarkup .= $this->view->flag($person['country'])."&nbsp;";
    	}

    	switch ($displayOption) {
    	    case $this::FULL_FIRST_FIRST:
        	    $nameText = $person['fullName'];
        	    break;
    	    default: //case $this::FULL_LAST_FIRST:
        	    $nameText = $person['lastName'].', '.$person['firstName'];
        	    break;
    	}

    	if ($linkOption) {
        	$linkFormat = '<a href="%s">%s</a>';
        	$url = $this->view->url('fathers/father', array('person_id' => $person['personId']));
        	$finalMarkup .= sprintf($linkFormat, $url, $this->view->escapeHtml($nameText));
    	} else {
    	    $finalMarkup .= $this->view->escapeHtml($nameText);
    	}
    	if ($person['leaveDate'] && $person['leaveDate'] instanceof \DateTime) {
    	    $finalMarkup .= ' (&times;' . $person['leaveDate']->format('Y') . ')';
    	}
    	if ($person['deathDate'] && $person['deathDate'] instanceof \DateTime) {
    		$finalMarkup .= ' (✝' . $person['deathDate']->format('Y') . ')';
    	}
    	if ($showLabels) {
    	    if (isset($person['labels']) && is_array($person['labels'])) {
    	        foreach ($person['labels'] as $label) {
    	           $finalMarkup .= ' '. $this->view->label($label, 'label-info');
    	        }
    	    }
    	}
    	if ($editPencilOption) { //permissions are checked in editPencil
    		$finalMarkup .= $this->view->editPencil('person', $person['personId']);
    	}
    	return $finalMarkup;
    }
}

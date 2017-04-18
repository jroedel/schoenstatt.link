<?php
namespace Bible\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Patres\Model\PatresTable;

class FormatBibleVerse extends AbstractHelper
{
    const FULL_LAST_FIRST = 'full last first';
    const FULL_FIRST_FIRST = 'full first first';
    const FRIENDLY_LAST_FIRST = 'friendly last first';
    const FRIENDLY_FIRST_FIRST = 'friendly last first';

    protected $statusLabels = [
        PatresTable::STATUS_INTERN => 'Intern',
        PatresTable::STATUS_EXTERN => 'Extern',
        PatresTable::STATUS_INTERN_EXEMPT => 'Exempt-Intern',
        PatresTable::STATUS_EXTERN_EXEMPT => 'Exempt-Extern',
        PatresTable::STATUS_COLLABORATOR => 'Collaborator',
        PatresTable::STATUS_ASSOCIATED => 'Associated',
    ];

    /**
     *
     * @param array $person
     * @param array $options
     *
     * available options:
     *  'display' => $this::FRIENDLY_FIRST_FIRST, (see class consts)
     *  'flag' => true,
     *  'link' => true,
     *  'displayEditPencil' => false,
     */
    public function __invoke($person, $options = [])
    {
    	//if there's not enough info we won't do anything
    	if (!isset($person['personId']) || !isset($person['firstName']) || !isset($person['lastName']) ||
    	        !$person['personId'] || $person['personId']=='' || (!isset($person['firstName']) && !isset($person['lastName']) &&
    	   !is_null($person['firstName']) && !is_null($person['lastName']))) {
    		return '';
    	}

    	//set defaults
    	if (isset($options['display'])) {
    	    if ($options['display'] === $this::FRIENDLY_FIRST_FIRST ||
    	        $options['display'] === $this::FULL_FIRST_FIRST ||
    	        $options['display'] === $this::FRIENDLY_LAST_FIRST ||
    	        $options['display'] === $this::FRIENDLY_FIRST_FIRST)
    	    {
    	        $displayOption = $options['display'];
    	    } else {
                $displayOption = $this::FRIENDLY_FIRST_FIRST;
    	    }
    	} else {
    	    $displayOption = $this::FRIENDLY_FIRST_FIRST;
    	}
    	$countryOption = isset($options['flag']) ? (bool)$options['flag'] : true;
    	$linkOption = isset($options['link']) ? (bool)$options['link'] : true;
    	$editPencilOption = isset($options['displayEditPencil']) ? (bool)$options['displayEditPencil'] : true;
    	$useContactInfoEditPencil = isset($options['useContactInfoEditPencil']) ? (bool)$options['useContactInfoEditPencil'] : false;
    	$showStatus = isset($options['showStatus']) ? (bool)$options['showStatus'] : false;
    	$showLabels = isset($options['showLabels']) ? (bool)$options['showLabels'] : false;

    	$finalMarkup = '';
    	if ($countryOption && $person['country']) {
    		$finalMarkup .= $this->view->flag($person['country'])."&nbsp;";
    	}

    	switch ($displayOption) {
    	    case $this::FRIENDLY_LAST_FIRST:
        	    $nameText = $person['friendlyLastName'].', '.$person['friendlyFirstName'];
        	    break;
    	    case $this::FULL_FIRST_FIRST:
        	    $nameText = $person['fullName'];
        	    break;
    	    case $this::FULL_LAST_FIRST:
        	    $nameText = $person['lastName'].', '.$person['firstName'];
        	    break;
    	    default: //case $this::FRIENDLY_FIRST_FIRST:
        	    $nameText = $person['fullFriendlyName'];
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
    	if ($showStatus) {
    	    if (!is_null($person['status']) && $person['status'] !== PatresTable::STATUS_INTERN && isset($this->statusLabels[$person['status']])) {
    	        $finalMarkup .= ' '. $this->view->label($this->statusLabels[$person['status']], 'label-info');
    	    }
    	}
    	if ($showLabels) {
    	    if (isset($person['labels']) && is_array($person['labels'])) {
    	        foreach ($person['labels'] as $label) {
    	           $finalMarkup .= ' '. $this->view->label($label, 'label-info');
    	        }
    	    }
    	}
    	if ($editPencilOption && !$useContactInfoEditPencil) { //permissions are checked in editPencil
    		$finalMarkup .= $this->view->editPencil('person', $person['personId']);
    	} elseif ($useContactInfoEditPencil) { //permissions are checked in editPencil
    	    $isAllowed = true; //if there is an exception, we'll assume there's no route permissions configured
    	    $route = 'fathers/father/edit-contact-info';
    	    try {
    	        $isAllowed = $this->view->isAllowed('route/'.$route);
    	    } catch (\Exception $e) {}
    	    if ($isAllowed) {
        	    $pattern = ' <a href="%s" target="_blank"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>';
        	    $finalMarkup .= sprintf($pattern,
    	            $this->view->url($route,
                    ['person_id' => $person['personId']]));
    	    }
    	}
    	return $finalMarkup;
    }
}

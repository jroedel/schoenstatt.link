<?php
// Application/View/Helper/FormatPersonName.php

namespace Schoenstatt\View\Helper;

use Laminas\View\Helper\AbstractHelper;

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
     *  'nameFormat' => $this::FRIENDLY_FIRST_FIRST, (see class consts)
     *  'showTitle' => true,
     *  'showFlag' => true,
     *  'link' => true,
     *  'editPencil' => false,
     */
    public function __invoke($person, $options = [])
    {
        //if there's not enough info we won't do anything
        if (! $person['personId'] || $person['personId'] == '' || (! isset($person['firstName'])
            && ! isset($person['lastName'])) ||
            (is_null($person['firstName']) && is_null($person['lastName']))
        ) {
            //@todo let the deleted name come through on the View Changes page
            return '';
        }

        //set defaults
        if (isset($options['nameFormat'])) {
            if ($options['nameFormat'] === $this::FULL_LAST_FIRST ||
                $options['nameFormat'] === $this::FULL_FIRST_FIRST) {
                $displayOption = $options['nameFormat'];
            } else {
                $displayOption = $this::FULL_LAST_FIRST;
            }
        } else {
            $displayOption = $this::FULL_LAST_FIRST;
        }
        $showTitle = isset($options['showTitle']) ? (bool)$options['showTitle'] : true;
        $countryOption = isset($options['showFlag']) ? (bool)$options['showFlag'] : true;
        $linkOption = isset($options['link']) ? (bool)$options['link'] : true;
        $editPencilOption = isset($options['displayEditPencil']) ? (bool)$options['displayEditPencil'] : true;
        $showLabels = isset($options['showLabels']) ? (bool)$options['showLabels'] : false;

        $finalMarkup = '';
        if ($countryOption && $person['country']) {
            $finalMarkup .= $this->view->flag($person['country']) . "&nbsp;";
        }

        if ($showTitle && $person['title']) {
            $person['firstName'] = $this->view->translate($person['title']) . ' ' . $person['firstName'];
        }

        $nameText = $person['firstName'];
        switch ($displayOption) {
            case $this::FULL_FIRST_FIRST:
                if ($person['lastName']) {
                    $nameText .= ', ' . $person['lastName'];
                }
                break;
            default: //case $this::FULL_LAST_FIRST:
                if ($person['lastName']) {
                    $nameText = $person['lastName'] . ', ' . $nameText;
                }
                break;
        }

        if ($linkOption) {
            $linkFormat = '<a href="%s">%s</a>';
            $url = $this->view->url('persons/person', ['person_id' => $person['personId']]);
            $finalMarkup .= sprintf($linkFormat, $url, $this->view->escapeHtml($nameText));
        } else {
            $finalMarkup .= $this->view->escapeHtml($nameText);
        }
        if ($person['deathDate'] && $person['deathDate'] instanceof \DateTime) {
            $finalMarkup .= ' (✝' . $person['deathDate']->format('Y') . ')';
        }
        if ($showLabels) {
            if (isset($person['labels']) && is_array($person['labels'])) {
                foreach ($person['labels'] as $label) {
                    $finalMarkup .= ' ' . $this->view->label($label, 'label-info');
                }
            }
        }
        if ($editPencilOption) { //permissions are checked in editPencil
            $finalMarkup .= $this->view->editPencil('person', $person['personId']);
        }
        return $finalMarkup;
    }
}

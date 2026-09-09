<?php
// Schoenstatt/View/Helper/FormatPerson.php

namespace Schoenstatt\View\Helper;

use Closure;
use SionModel\View\Escape;

/**
 * A person's name, with a flag, a link to their page, death year, labels and a pencil.
 *
 * A plain class since 2026-09. `flag`, `translate`, `url`, `label` and `editPencil` came
 * from the renderer laminas-view gave every AbstractHelper and are injected as closures
 * now; `escapeHtml` is {@see Escape::html()}.
 *
 * `translate` must be the shared `translate` **view helper**, not a raw translator: the
 * title ("Fr.", "Sr.") is looked up with no text domain, so the object answering has to be
 * the one the request's domain was set on. A translator would answer from `default`, miss,
 * and hand back the English source in all five locales.
 */
class FormatPerson
{
    const FULL_LAST_FIRST = 'full last first';
    const FULL_FIRST_FIRST = 'full first first';

    /**
     * Each collaborator is optional and degrades to the safest reading of the original:
     * no flag, untranslated title, no link, no label, no pencil.
     *
     * @param Closure(string): string|null $flag
     * @param Closure(string): string|null $translate the shared `translate` view helper
     * @param Closure(string, array): string|null $url
     * @param Closure(string, string): string|null $label
     * @param Closure(string, mixed): string|null $editPencil
     */
    public function __construct(
        private readonly ?Closure $flag = null,
        private readonly ?Closure $translate = null,
        private readonly ?Closure $url = null,
        private readonly ?Closure $label = null,
        private readonly ?Closure $editPencil = null
    ) {
    }

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
        if ($countryOption && $person['country'] && null !== $this->flag) {
            $finalMarkup .= ($this->flag)($person['country']) . "&nbsp;";
        }

        if ($showTitle && $person['title']) {
            $title = null !== $this->translate ? ($this->translate)($person['title']) : $person['title'];
            $person['firstName'] = $title . ' ' . $person['firstName'];
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

        if ($linkOption && null !== $this->url) {
            $linkFormat = '<a href="%s">%s</a>';
            $url = ($this->url)('persons/person', ['person_id' => $person['personId']]);
            $finalMarkup .= sprintf($linkFormat, $url, Escape::html((string) $nameText));
        } else {
            $finalMarkup .= Escape::html((string) $nameText);
        }
        if ($person['deathDate'] && $person['deathDate'] instanceof \DateTime) {
            $finalMarkup .= ' (✝' . $person['deathDate']->format('Y') . ')';
        }
        if ($showLabels && null !== $this->label) {
            if (isset($person['labels']) && is_array($person['labels'])) {
                foreach ($person['labels'] as $label) {
                    $finalMarkup .= ' ' . ($this->label)($label, 'label-info');
                }
            }
        }
        if ($editPencilOption && null !== $this->editPencil) { //permissions are checked in editPencil
            $finalMarkup .= ($this->editPencil)('person', $person['personId']);
        }
        return $finalMarkup;
    }
}

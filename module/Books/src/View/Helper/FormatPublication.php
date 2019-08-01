<?php
namespace Books\View\Helper;

use SionModel\View\Helper\FormatEntity;

class FormatPublication extends FormatEntity
{
    const DISPLAY_TITLE = 'title';
    const DISPLAY_DISAMBIGUATING_TITLE = 'disambiguatingTitle';
    const DISPLAY_AUTHORS = 'authors';
    const DISPLAY_EDITION = 'edition';
    const DISPLAY_TRANSLATORS = 'translators';

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
                $options['display'] === self::DISPLAY_DISAMBIGUATING_TITLE ||
                $options['display'] === self::DISPLAY_EDITION ||
                $options['display'] === self::DISPLAY_TRANSLATORS ||
                $options['display'] === self::DISPLAY_ILLUSTRATORS) {
                $displayOption = $options['display'];
            } else {
                $displayOption = self::DISPLAY_TITLE;
            }
        } else {
            $displayOption = $this::DISPLAY_TITLE;
        }
        $linkOption        = isset($options['link']) 
            ? (bool)$options['link'] 
            : $displayOption === self::DISPLAY_TITLE || $displayOption === self::DISPLAY_DISAMBIGUATING_TITLE;
        $editPencilOption  = isset($options['displayEditPencil']) 
            ? (bool)$options['displayEditPencil'] 
            : $displayOption === self::DISPLAY_TITLE || $displayOption === self::DISPLAY_DISAMBIGUATING_TITLE;
        $showLanguageLabel = isset($options['displayLanguageLabel']) ? (bool)$options['displayLanguageLabel'] : false;
        $showResourceLabel = isset($options['displayResourceLabel']) ? (bool)$options['displayResourceLabel'] : false;
        $showHandChecked   = isset($options['displayHandChecked']) 
            ? (bool)$options['displayHandChecked'] 
            : $displayOption === self::DISPLAY_TITLE || $displayOption === self::DISPLAY_DISAMBIGUATING_TITLE;

        $finalMarkup = '';
        $escapeMainText = true;
        static $editorText;
        switch ($displayOption) {
            //authors also include editors
            case self::DISPLAY_AUTHORS:
                $authors = [];
//                 foreach ($data['authorAssociations'] as $id => $object) {
//                     $authors[] = $this->view->formatEntity('association', $object);
//                 }
//                 foreach ($data['authorPersons'] as $id => $object) {
//                     $authors[] = $this->view->formatEntity('person', $object);
//                 }
                foreach ($data['authorsText'] as $text) {
                    $authors[] = $this->view->escapeHtml($text);
                }
//                 if (isset($data['editorAssociation'])) {
//                     if (!isset($editorText)) {
//                         $editorText = sprintf(' (%s)', $this->view->translate('Ed.'));
//                     }
//                     $authors[] = $this->view->formatEntity('association', $data['editorAssociation']).$editorText;
//                 }
//                 foreach ($data['editorPersons'] as $id => $object) {
//                     if (!isset($editorText)) {
//                         $editorText = sprintf(' (%s)', $this->view->translate('Ed.'));
//                     }
//                     $authors[] = $this->view->formatEntity('person', $object).$editorText;
//                 }
                foreach ($data['editorsText'] as $text) {
                    if (!isset($editorText)) {
                        $editorText = sprintf(' (%s)', $this->view->translate('Ed.'));
                    }
                    $authors[] = $this->view->escapeHtml($text).$editorText;
                }
                $escapeMainText = false;
                $mainText = implode('; ', $authors);
                break;
            case self::DISPLAY_TRANSLATORS:
                $authors = [];
//                 foreach ($data['translatorPersons'] as $id => $object) {
//                     $authors[] = $this->view->formatEntity('person', $object);
//                 }
                foreach ($data['translatorsText'] as $text) {
                    $authors[] = $this->view->escapeHtml($text);
                }
                $escapeMainText = false;
                $mainText = implode('; ', $authors);
                break;
            case self::DISPLAY_TITLE:
            case self::DISPLAY_DISAMBIGUATING_TITLE:
                $mainText = $data[$displayOption];
                break;
            case self::DISPLAY_EDITION:
                $mainText = '';
                $escapeMainText = false;
                if (isset($data['bookEdition'])) {
                    $mainText .= sprintf("<strong>%s</strong>", $this->view->escapeHtml($data['bookEdition']));
                }
                //use published date because copyright date should be the same for all editions (first edition)
                if (isset($data['datePublishedText'])) {
                    if (strlen($mainText) > 0) {
                        $mainText .= ', ';
                    }
                    $mainText .= $this->view->escapeHtml($data['datePublishedText']);
                }
                if (isset($data['publishingPlace'])) {
                    if (strlen($mainText) > 0) {
                        $mainText .= ' ';
                    }
                    $mainText .= $this->view->escapeHtml($data['publishingPlace']);
                }
        }

        if ($linkOption) {
            $linkFormat = '<a href="%s">%s</a>';
            $url = $this->view->url('publication', ['sw_id' => $data['identifier'], 'slug' => $data['slug']]);
            if ($escapeMainText) {
                $mainText = $this->view->escapeHtml($mainText);
            }
            $finalMarkup .= sprintf($linkFormat, $url, $mainText);
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
//         if ($showLabels) {
//             if (isset($person['labels']) && is_array($person['labels'])) {
//                 foreach ($person['labels'] as $label) {
//                    $finalMarkup .= ' '. $this->view->label($label, 'label-info');
//                 }
//             }
//         }
        if ($editPencilOption) { //permissions are checked in editPencil
            $finalMarkup .= $this->view->editPencil('publication', $data['identifier']);
        }
        if ($showHandChecked && $data['isRevisedWithBookInHand']) {
            $finalMarkup .= sprintf(
                '&nbsp;<span class="fa fa-check-circle-o fa-3 text-success" title="%s"></span>',
                $this->view->translate('Information has been hand checked')
            );
        }
        return $finalMarkup;
    }

    /**
    * @var array $authorValueOptions
    */
    protected $authorValueOptions;

    /**
    * Get the authorValueOptions value
    * @return array
    */
    public function getAuthorValueOptions()
    {
        return $this->authorValueOptions;
    }

    /**
    *
    * @param array $authorValueOptions
    * @return self
    */
    public function setAuthorValueOptions($authorValueOptions)
    {
        $this->authorValueOptions = $authorValueOptions;
        return $this;
    }
}

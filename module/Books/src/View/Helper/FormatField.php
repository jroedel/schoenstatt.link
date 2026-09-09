<?php
namespace Books\View\Helper;

use Closure;
use SionModel\View\Escape;

class FormatField
{
    const DISPLAY_TITLE = 'title';
    const DISPLAY_AUTHORS = 'authors';

    /**
     * @param Closure(string): string $translate the shared `translate` **view helper**, not
     *        the translator behind it: the label is looked up with no text domain, so what
     *        answers has to be the object App\Laminas\ViewHelpers::useTextDomain() sets the
     *        request's domain on. A raw translator would look in `default`, miss, and return
     *        the English source on every locale.
     * @param Closure(?string, ?string): bool $isAllowed the `isAllowed` helper, for
     *        `displayOnlyWithPermission`.
     * @param Closure(mixed, int, int): (string|false) $dateFormat the `dateFormat` helper.
     */
    public function __construct(
        private readonly Closure $translate,
        private readonly Closure $isAllowed,
        private readonly Closure $dateFormat
    ) {
    }

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
//      $linkOption = isset($options['link']) ? (bool)$options['link'] : $displayOption == self::DISPLAY_TITLE;
//      $editPencilOption = isset($options['displayEditPencil']) ? (bool)$options['displayEditPencil'] : $displayOption == self::DISPLAY_TITLE;
//      $showLanguageLabel = isset($options['displayLanguageLabel']) ? (bool)$options['displayLanguageLabel'] : false;

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

        if (isset($options['displayOnlyWithPermission']) && ! is_null($options['displayOnlyWithPermission']) &&
            ! ($this->isAllowed)($options['displayOnlyWithPermission'])
        ) {
            return '';
        }

        $finalMarkup = '';

        if (! is_null($label)) {
            if ($translateLabel) {
                $label = ($this->translate)($label);
            }
            $finalMarkup .= sprintf($labelMarkup, Escape::html((string) $label));
        }
//      switch ($displayOption) {
//          case 'authors': //@todo make it work when we have real authors
//              $mainText = $object['authors'];
//             break;
//          case 'title':
//              $mainText = $object['title'];
//              break;
//      }

        if ($value instanceof \DateTime) {
            //@todo make format configurable
            $finalMarkup .= ($this->dateFormat)($value, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
        } elseif (is_array($value)) {
            $finalMarkup .= implode(', ', $value);
        } else {
            $finalMarkup .= Escape::html((string) $value);
        }

//      if ($showLabels) {
//          if (isset($person['labels']) && is_array($person['labels'])) {
//              foreach ($person['labels'] as $label) {
//                 $finalMarkup .= ' '. $this->view->label($label, 'label-info');
//              }
//          }
//      }
//      if ($editPencilOption) { //permissions are checked in editPencil
//          $finalMarkup .= $this->view->editPencil('publication', $object['publicationId']);
//      }

        if (! is_null($wrapMarkup)) {
            $finalMarkup = sprintf($wrapMarkup, $finalMarkup);
        }
        return $finalMarkup;
    }
}

<?php
namespace Schoenstatt\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * Extended `Laminas\Form\View\Helper\AbstractHelper` until 2026-09, which is a form
 * helper and this is not one — it formats an association for display. That base class
 * reaches laminas-i18n through `AbstractTranslatorHelper`, so it was the only live thing
 * standing between this application and laminas-i18n's removal. Nothing from the form base
 * was used: the one inherited call here is `$this->view->escapeHtml()`, which comes from
 * laminas-view's own helper, and that is what it extends now.
 */
class FormatAssociation extends AbstractHelper
{
    protected $associationTypeLabels = [];

    const DISPLAY_NAME = 'name';
    const DISPLAY_INTERNAL_NAME = 'internal-name';
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
        $locale = \Locale::getDefault();
        $display = isset($options['display']) ? $options['display'] : 'name';
        $editPencilOption = isset($options['editPencil']) ? (bool)$options['editPencil'] : $display == 'name';
        $showFlagOption = isset($options['showFlag']) ? (bool)$options['showFlag'] : true;
        $showLabelOption = isset($options['showLabel']) ? (bool)$options['showLabel'] : false;
        $displayAsLink = isset($options['displayAsLink']) ? (bool)$options['displayAsLink'] : $display == 'name';
        $finalMarkup = '';

        if (isset($data['isDeleted']) && $data['isDeleted']) {
            $text = $data['associationName'];
            $displayAsLink = false;
            $editPencilOption = false;
            $showLabelOption = false;
        } else {
            switch ($display) {
                case self::DISPLAY_NAME:
                    //only show flag if we're looking at display_name
                    if ($showFlagOption && isset($data['country'])) {
                        $finalMarkup .= $this->view->flag($data['country']) . "&nbsp;";
                    }

                    $text = $data['nameByLocale'][$locale];
                    break;
                case self::DISPLAY_INTERNAL_NAME:
                    //only show flag if we're looking at display_name
                    if ($showFlagOption && isset($data['country'])) {
                        $finalMarkup .= $this->view->flag($data['country']) . "&nbsp;";
                    }

                    if ($data['internalNameByLocale'][$locale]) {
                        $text = $data['internalNameByLocale'][$locale];
                    } else {
                        $text = $data['nameByLocale'][$locale];
                    }
                    break;
                case self::DISPLAY_KIND:
                    if (key_exists($data['kind'], $this->associationTypeLabels)) {
                        //don't translate these, since they are already translated
                        $text = $this->associationTypeLabels[$data['kind']];
                    } else {
                        $text = $this->view->translate($data['kind']);
                    }
                    break;
                default:
                    throw new \InvalidArgumentException("Invalid display parameter: $display");
                break;
            }
        }
        $text = $this->view->escapeHtml($text);

        //@todo check resources too
        $permissionToViewLink = $this->view->isAllowed('route/association');
//             && $this->view->isAllowed($data['resourceId']);
        if ($displayAsLink && $permissionToViewLink) {
            $url = $this->view->url(
                'association',
                [
                    'sw_id' => $data['identifier'],
                    'slug' => $data['slugByLocale'][$locale]
                ]
            );
            $finalMarkup .= sprintf(
                '<a href="%s">%s</a>',
                $url,
                $text
            );
        } else {
            $finalMarkup .= $text;
        }
        if ($showLabelOption && isset($this->associationTypeLabels[$data['kind']])) {
            $finalMarkup .= '&nbsp;' . $this->view->label(
                $this->associationTypeLabels[$data['kind']],
                'label-info'
            );
        }
        if ($editPencilOption) {
            $finalMarkup .= $this->view->editPencil('association', $data['identifier']);
        }

        return $finalMarkup;
    }
}

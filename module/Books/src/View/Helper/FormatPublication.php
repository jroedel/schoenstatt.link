<?php
namespace Books\View\Helper;

use Closure;
use SionModel\View\Escape;
use SionModel\View\Helper\FormatEntity;

/**
 * A publication rendered by title, disambiguating title, authors, edition or translators.
 *
 * Ported alongside the batch-4 cluster for the same reason `Schoenstatt\View\Helper\
 * FormatEntity` was: it *extends* `SionModel\View\Helper\FormatEntity`, so when that class
 * stopped being a `Laminas\View\Helper\AbstractHelper` this one lost `$this->view` with it.
 * It reuses the parent's injected `translate`, `url` and `editPencil`; `label` is its own,
 * because the parent has no use for one.
 *
 * The label calls carry an explicit `Books` text domain. That is what the original meant by
 * `$this->view->label()->setTranslatorTextDomain('Books')` immediately before rendering —
 * reaching the label helper itself to set state on it, then invoking it — and it is a per
 * call argument here rather than a mode left switched on.
 */
class FormatPublication extends FormatEntity
{
    const DISPLAY_TITLE = 'title';
    const DISPLAY_DISAMBIGUATING_TITLE = 'disambiguatingTitle';
    const DISPLAY_AUTHORS = 'authors';
    const DISPLAY_EDITION = 'edition';
    const DISPLAY_TRANSLATORS = 'translators';

    /**
     * @param Closure(string, string, string): string|null $label the `label` helper, whose
     *        third argument is the text domain. Null renders no label.
     * @param array<string, Closure> $formatHelpers see the parent
     */
    public function __construct(
        $entityService,
        $routePermissionCheckingEnabled = false,
        ?Closure $flag = null,
        ?Closure $dateFormat = null,
        ?Closure $translate = null,
        ?Closure $url = null,
        ?Closure $editPencil = null,
        ?Closure $editPencilNew = null,
        ?Closure $isAllowed = null,
        array $formatHelpers = [],
        private readonly ?Closure $label = null
    ) {
        parent::__construct(
            $entityService,
            $routePermissionCheckingEnabled,
            $flag,
            $dateFormat,
            $translate,
            $url,
            $editPencil,
            $editPencilNew,
            $isAllowed,
            $formatHelpers
        );
    }

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
        if (! isset($data['publicationId']) || ! isset($data['title'])) {
            return '';
        }

        //set defaults
        if (isset($options['display'])) {
            if (
                $options['display'] === self::DISPLAY_AUTHORS ||
                $options['display'] === self::DISPLAY_TITLE ||
                $options['display'] === self::DISPLAY_DISAMBIGUATING_TITLE ||
                $options['display'] === self::DISPLAY_EDITION ||
                $options['display'] === self::DISPLAY_TRANSLATORS
            ) {
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
        $showDataSource   = isset($options['displayDataSource'])
            ? (bool)$options['displayDataSource']
            : $displayOption === self::DISPLAY_TITLE || $displayOption === self::DISPLAY_DISAMBIGUATING_TITLE;
        $showMerged   = isset($options['displayMerged'])
            ? (bool)$options['displayMerged']
            : $displayOption === self::DISPLAY_TITLE || $displayOption === self::DISPLAY_DISAMBIGUATING_TITLE;

        $finalMarkup = '';
        $escapeMainText = true;
        static $editorText;
        switch ($displayOption) {
            //authors also include editors
            case self::DISPLAY_AUTHORS:
                $authors = [];
                foreach ($data['authorsText'] as $text) {
                    $authors[] = Escape::html((string) $text);
                }
                foreach ($data['editorsText'] as $text) {
                    if (! isset($editorText)) {
                        $editorText = sprintf(' (%s)', $this->translate('Ed.'));
                    }
                    $authors[] = Escape::html((string) $text) . $editorText;
                }
                $escapeMainText = false;
                $mainText = implode('; ', $authors);
                break;
            case self::DISPLAY_TRANSLATORS:
                $authors = [];
                foreach ($data['translatorsText'] as $text) {
                    $authors[] = Escape::html((string) $text);
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
                    $mainText .= sprintf("<strong>%s</strong>", Escape::html((string) $data['bookEdition']));
                }
                //use published date because copyright date should be the same for all editions (first edition)
                if (isset($data['datePublishedText'])) {
                    if (strlen($mainText) > 0) {
                        $mainText .= ', ';
                    }
                    $mainText .= Escape::html((string) $data['datePublishedText']);
                }
                if (isset($data['publishingPlace'])) {
                    if (strlen($mainText) > 0) {
                        $mainText .= ' ';
                    }
                    $mainText .= Escape::html((string) $data['publishingPlace']);
                }
        }

        if ($linkOption && isset($data['identifier']) && isset($data['slug']) && null !== $this->url) {
            $linkFormat = '<a href="%s">%s</a>';
            $url = ($this->url)('publication', ['sw_id' => $data['identifier'], 'slug' => $data['slug']]);
            if ($escapeMainText) {
                $mainText = Escape::html((string) $mainText);
            }
            $finalMarkup .= sprintf($linkFormat, $url, $mainText);
        } else {
            if ($escapeMainText) {
                $finalMarkup .= Escape::html((string) $mainText);
            } else {
                $finalMarkup .= $mainText;
            }
        }
        if ($showLanguageLabel && null !== $this->label) {
            if (! is_null($data['inLanguage'])) {
                $finalMarkup .= ' ' . ($this->label)($data['inLanguage'], 'label-info', 'Books');
            }
        }
        if ($showResourceLabel && null !== $this->label) {
            static $resourceLabels;
            if (is_null($resourceLabels)) {
                $resourceLabels = [
                    'publication_institute' => 'Institute',
                    'publication_patres' => 'Patres',
                ];
            }
            if (array_key_exists($data['resourceId'], $resourceLabels)) {
                $finalMarkup .= ' ' . ($this->label)($resourceLabels[$data['resourceId']], 'label-info', 'Books');
            }
        }
        if ($editPencilOption && isset($data['identifier'])) { //permissions are checked in editPencil
            $finalMarkup .= $this->renderEditPencil('publication', $data['identifier']);
        }
        if ($showHandChecked && $data['isRevisedWithBookInHand']) {
            $finalMarkup .= sprintf(
                '&nbsp;<span class="fa fa-check-circle-o fa-3 text-success" title="%s"></span>',
                $this->translate('Information has been hand checked')
            );
        }
        if ($showDataSource && isset($data['dataSource'])) {
            $finalMarkup .= sprintf(
                '&nbsp;<span class="fa fa-database" title="%s"></span>',
                $this->translate('This row comes from an external data source')
            );
        }
        if ($showMerged && isset($data['mergedIntoPublicationId'])) {
            $finalMarkup .= sprintf(
                '&nbsp;<span class="fa fa-sign-in" title="%s"></span>',
                $this->translate('This row has been merged into the main corpus')
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

<?php
namespace Schoenstatt\View\Helper;

use Closure;
use SionModel\View\Escape;

/**
 * An association rendered as a flag, its name (or internal name, or kind), a link, a kind
 * label and an edit pencil.
 *
 * Extended `Laminas\Form\View\Helper\AbstractHelper` until 2026-09, which is a form helper
 * and this is not one; then laminas-view's own `AbstractHelper`; now nothing. Every
 * collaborator it used to reach through the renderer — `flag`, `translate`, `isAllowed`,
 * `url`, `label`, `editPencil` — is injected as a closure, and `escapeHtml` is
 * {@see Escape::html()}.
 *
 * `translate` has to be the shared `translate` **view helper**: the kind is looked up with
 * no text domain, so only the object carrying the request's domain answers correctly. A
 * raw translator would search `default`, miss, and return English in every locale.
 */
class FormatAssociation
{
    protected $associationTypeLabels = [];

    const DISPLAY_NAME = 'name';
    const DISPLAY_INTERNAL_NAME = 'internal-name';
    const DISPLAY_KIND = 'kind';

    /**
     * Every closure is optional and degrades to the safest reading of the original: no
     * flag, untranslated kind, no link, no label, no pencil. A null `isAllowed` allows,
     * which is the posture the original took when the plugin was missing.
     *
     * @param mixed[] $associationTypeLabels
     * @param Closure(string): string|null $flag
     * @param Closure(string): string|null $translate the shared `translate` view helper
     * @param Closure(?string, ?string): bool|null $isAllowed
     * @param Closure(string, array): string|null $url
     * @param Closure(string, string): string|null $label
     * @param Closure(string, mixed): string|null $editPencil
     */
    public function __construct(
        $associationTypeLabels,
        private readonly ?Closure $flag = null,
        private readonly ?Closure $translate = null,
        private readonly ?Closure $isAllowed = null,
        private readonly ?Closure $url = null,
        private readonly ?Closure $label = null,
        private readonly ?Closure $editPencil = null
    ) {
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
                        $finalMarkup .= $this->renderFlag($data['country']) . "&nbsp;";
                    }

                    $text = $data['nameByLocale'][$locale];
                    break;
                case self::DISPLAY_INTERNAL_NAME:
                    //only show flag if we're looking at display_name
                    if ($showFlagOption && isset($data['country'])) {
                        $finalMarkup .= $this->renderFlag($data['country']) . "&nbsp;";
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
                        $text = null !== $this->translate ? ($this->translate)($data['kind']) : $data['kind'];
                    }
                    break;
                default:
                    throw new \InvalidArgumentException("Invalid display parameter: $display");
                break;
            }
        }
        $text = Escape::html((string) $text);

        //@todo check resources too
        //a null isAllowed is the state the original reached when the plugin was missing
        $permissionToViewLink = null === $this->isAllowed || ($this->isAllowed)('route/association');
//             && ($this->isAllowed)($data['resourceId']);
        if ($displayAsLink && $permissionToViewLink && null !== $this->url) {
            $url = ($this->url)(
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
        if ($showLabelOption && isset($this->associationTypeLabels[$data['kind']]) && null !== $this->label) {
            $finalMarkup .= '&nbsp;' . ($this->label)(
                $this->associationTypeLabels[$data['kind']],
                'label-info'
            );
        }
        if ($editPencilOption && null !== $this->editPencil) {
            $finalMarkup .= ($this->editPencil)('association', $data['identifier']);
        }

        return $finalMarkup;
    }

    /** The `flag` view helper, or nothing when the host supplies none. */
    protected function renderFlag($countryCode)
    {
        return null !== $this->flag ? ($this->flag)($countryCode) : '';
    }
}

<?php
namespace Schoenstatt\Service;

use Laminas\I18n\Translator\TranslatorInterface;
use Schoenstatt\Model\AssociationKind;
use Laminas\Mvc\I18n\Translator;
use Schoenstatt\Model\SchoenstattTable;

class AssociationKindsService
{
    /**
     * Keyed array of AssociationKind specs
     * @var AssociationKind[] $associationKinds
     */
    protected $associationKinds = [];

    /**
     * @var Translator $translator
     */
    protected $translator;
    /**
     * An associative array mapping ISO language codes to locales
     * @var string[] $languageLocaleMap
     */
    protected $languageLocaleMap;

    public function __construct($associationKindSpecifications, TranslatorInterface $translator, array $config)
    {
        $this->translator = $translator;
        $this->languageLocaleMap = $config['slm_locale']['aliases'];

        foreach ($associationKindSpecifications as $kind => $spec) {
            if (! is_array($spec) || ! is_string($kind)) {
                unset($associationKindSpecifications[$kind]);
            }
            if (! isset($spec['label']) || ! is_string($spec['label'])) {
                throw new \Exception('All association kind specs must contain a label.');
            }
            if (isset($spec['name_format']) && is_string($spec['name_format'])) {
                $spec['translated_name_format'] = $this->translator->translate($spec['name_format'], SchoenstattTable::TRANSLATOR_DOMAIN);
                $spec['name_format_by_locale'] = [];
                foreach ($this->languageLocaleMap as $locale) {
                    $spec['name_format_by_locale'][$locale] =
                        $this->translator->translate($spec['name_format'], SchoenstattTable::TRANSLATOR_DOMAIN, $locale);
                }
            }
            if (! isset($spec['translated_label'])) {
                $spec['translated_label'] = $this->translator->translate($spec['label'], SchoenstattTable::TRANSLATOR_DOMAIN);
            }
            $this->associationKinds[$kind] = new AssociationKind($kind, $spec);
        }
    }

    /**
     * Get a keyed array of AssociationKind specs, keyed by the name
     * @return \Schoenstatt\Model\AssociationKind[]
     */
    public function getAssociationKinds()
    {
        return $this->associationKinds;
    }

    /**
     * Get a keyed array of association kind labels, keyed by the name
     * @return string[]
     */
    public function getValueOptions()
    {
        $valueOptions = [];
        foreach ($this->associationKinds as $kind => $spec) {
            $valueOptions[$kind] = $spec->translatedLabel;
        }
        asort($valueOptions);
        return $valueOptions;
    }
}

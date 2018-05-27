<?php
namespace Schoenstatt\Service;

use Zend\I18n\Translator\TranslatorInterface;
use Schoenstatt\Model\AssociationKind;

class AssociationKindsService
{
    /**
     * Keyed array of AssociationKind specs
     * @var AssociationKind[] $associationKinds
     */
    protected $associationKinds = [];

    protected $translator;

    public function __construct($associationKindSpecifications, TranslatorInterface $translator)
    {
        $this->translator = $translator;
        foreach ($associationKindSpecifications as $kind => $spec) {
            if (!is_array($spec) || !is_string($kind)) {
                unset($associationKindSpecifications[$kind]);
            }
            if (!key_exists('label', $spec) || !is_string($spec['label'])) {
                throw new \Exception('All association kind specs must contain a label.');
            }
            if (key_exists('name_format', $spec) &&
                is_string($spec['name_format'])
            ) {
                $spec['translated_name_format'] =
                    $this->translator->translate($spec['name_format'], 'Schoenstatt');
            }
            if (!key_exists('translated_label', $spec)) {
                $spec['translated_label'] = $this->translator->translate($spec['label'], 'Schoenstatt');
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
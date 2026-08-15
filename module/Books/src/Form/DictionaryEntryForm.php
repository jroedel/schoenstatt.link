<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Laminas\InputFilter\InputFilterProviderInterface;
use Books\Model\LibraryOptions;
use SionModel\Form\ChoiceDomain;

class DictionaryEntryForm extends SionForm implements InputFilterProviderInterface
{
    /**
     * @var LibraryOptions $libraryOptions
     */
    protected $libraryOptions;

    public function __construct()
    {
        parent::__construct('dictionary-entry');

        //@todo make a selectize element and fill it with existing keys
        $this->add([
            'name' => 'key',
            'type' => 'Text',
            'options' => [
                'label' => 'Key (German)',
            ],
            'attributes' => [
                'required' => true,
                'maxlength' => '255',
            ],
        ]);

        $this->add([
            'name' => 'locale',
            'type' => 'Select',
            'options' => [
                'label' => 'Language',
                'value_options' => [
                    'en_US' => 'English',
                    'es_ES' => 'Spanish',
                    'pt_PT' => 'Portugues (Portugal)',
                    'fr_FR' => 'French',
                    'cs_CZ' => 'Czech',
                    'hu_HU' => 'Hungarian',
                ],
            ],
            'attributes' => [
                'required' => true,
                'disabled' => false, //@todo how can I block this without disabling it?
                'maxlength' => '6',
            ],
        ]);
        $this->add([
            'name' => 'directTranslation',
            'type' => 'Text',
            'options' => [
                'label' => 'Direct Translation',
                'help-block' => 'Most probable translated text that could directly replace the German key text',
            ],
            'attributes' => [
                'required' => false,
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'entry',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Dictionary Entry',
            ],
            'attributes' => [
                'data-provide' => 'markdown',
                'data-parser' => 'CommonMark',
                'required' => true,
                'maxlength' => '1000',
            ],
        ]);
        $this->add([
            'name' => 'links',
            'type' => 'Select',
            'options' => [
                'label' => 'Links',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
            ],
            'attributes' => [
                'maxlength' => '500',
                'multiple' => true,
            ],
        ]);
        $this->add([
            'name' => 'isActive',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Active?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Submit',
                'id' => 'submit',
                'class' => 'btn-primary'
            ],
        ]);
    }

    /**
    * Get the libraryOptions value
    * @return LibraryOptions
    */
    public function getLibraryOptions()
    {
        return $this->libraryOptions;
    }

    public function getInputFilterSpecification()
    {
        return [
            'key' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 255,
                        ],
                    ],
                ],
            ],
            'locale' => [
                'required' => true,
                'validators' => ChoiceDomain::validators($this->get('locale')),
            ],
            'directTranslation' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 255,
                        ],
                    ],
                ],
            ],
            //The `@todo add a validator` that stood here is done: a multiple select's
            //own InArray is discarded by this spec entry existing at all, so the 1,352
            //entries it offers constrained nothing. ChoiceDomain wraps it in Explode,
            //which is what the element's own input specification does for a multiple.
            'links' => [
                'required' => false,
                'filters' => [
//                     ['name' => 'SionModel\Filter\TrimStringArray'],
                ],
                'validators' => ChoiceDomain::validators($this->get('links')),
            ],
            'entry' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 1000,
                        ],
                    ],
                ],
            ],
            'isActive' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
        ];
    }

//     public function getData($flag = FormInterface::VALUES_NORMALIZED)
//     {
//         $values = parent::getData($flag);

//         //check if we need to set any
//     }
}

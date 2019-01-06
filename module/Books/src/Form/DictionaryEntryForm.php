<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Zend\InputFilter\InputFilterProviderInterface;
use Books\Model\LibraryOptions;

class DictionaryEntryForm extends SionForm implements InputFilterProviderInterface
{
    /**
     * @var LibraryOptions $libraryOptions
     */
    protected $libraryOptions;

    public function __construct()
    {
        parent::__construct('dictionary-entry');
        
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
                    'cz_CZ' => 'Czech',
                    'hu_HU' => 'Hungarian',
                ],
            ],
            'attributes' => [
                'required' => false,
                'disabled' => true,
                'maxlength' => '6',
            ],
        ]);
        $this->add([
            'name' => 'directTranslation',
            'type' => 'Text',
            'options' => [
                'label' => 'Key (German)',
            ],
            'attributes' => [
                'required' => false,
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'entry',
            'type' => 'Text',
            'options' => [
                'label' => 'Dictionary Entry',
            ],
            'attributes' => [
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
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
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
            ],
            'directTranslation' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
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
            'links' => [ //@todo add a validator
                'required' => false,
                'filters' => [
//                     ['name' => 'SionModel\Filter\TrimStringArray'],
                ],
            ],
            'entry' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
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

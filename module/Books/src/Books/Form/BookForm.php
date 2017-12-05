<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Zend\InputFilter\InputFilterProviderInterface;
use Books\Model\LibraryOptions;

class BookForm extends SionForm implements InputFilterProviderInterface
{
    /**
     * @var LibraryOptions $libraryOptions
     */
    protected $libraryOptions;

    public function __construct()
    {
        parent::__construct('publication');

        $this->add([
            'name' => 'title',
            'type' => 'Text',
            'options' => [
                'label' => 'Title',
                'required' => true,
            ],
            'attributes' => [
                'maxlength' => '300',
            ],
        ]);

        $this->add([
            'name' => 'authors',
            'type' => 'Select',
            'options' => [
                'label' => 'Author(s)',
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
            'name' => 'callNumber',
            'type' => 'Text',
            'options' => [
                'label' => 'Call number',
                'required' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. LIT 256 1.1',
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'newCallNumber',
            'type' => 'Text',
            'options' => [
                'label' => 'New call number',
                'required' => false,
                'help-block' => 'Use this field when the printed call number should be changed. It will be added to the next labels to be printed.'
            ],
            'attributes' => [
                'placeholder' => 'ex. LIT 256 1.1',
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'collectionId',
            'type' => 'Select',
            'options' => [
                'label' => 'Collection',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
        ]);
        $this->add([
            'name' => 'bookEdition',
            'type' => 'Text',
            'options' => [
                'label' => 'Edition',
                'required' => false,
            ],
            'attributes' => [
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'inLanguage',
            'type' => 'Select',
            'options' => [
                'label' => 'Language',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
            'attributes' => [
                'multiple' => true,
            ],
        ]);
        $this->add([
            'name' => 'publicationId',
            'type' => 'Select',
            'options' => [
                'label' => 'Literature dataset link',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => false,
                'value_options' => [],
            ],
        ]);
        $this->add([
            'name' => 'category',
            'type' => 'Select',
            'options' => [
                'label' => 'Category',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [],
            ],
            'attributes' => [
                'maxlength' => '500',
            ],
        ]);
        $this->add([
            'name' => 'isbn',
            'type' => 'Text',
            'options' => [
                'label' => 'ISBN',
                'required' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. 9780030426599',
                'maxlength' => '30',
            ],
        ]);
        $this->add([
            'name' => 'numberOfPages',
            'type' => 'Number',
            'options' => [
                'label' => 'Number of pages',
                'required' => false,
            ],
            'attributes' => [
                'min'         => 0,
                'max'         => 6000,
                'step'        => 'any',
                'inclusive'   => true,
            ],
        ]);
        $this->add([
            'name' => 'copyrightYear',
            'type' => 'Number',
            'options' => [
                'label' => 'Copyright year',
                'required' => false,
            ],
            'attributes' => [
                'min'         => 1800,
                'max'         => 2025,
                'step'        => 1,
                'inclusive'   => true,
            ],
        ]);
        $this->add([
            'name' => 'publisher',
            'type' => 'Select',
            'options' => [
                'label' => 'Publisher',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [],
            ],
        ]);
        $this->add([
            'name' => 'publishingPlace',
            'type' => 'Text',
            'options' => [
                'label' => 'Publishing place',
                'required' => true,
            ],
            'attributes' => [
                'placeholder' => 'Madrid, Spain',
                'maxlength' => '200',
            ],
        ]);
        $this->add([
            'name' => 'keywords',
            'type' => 'Select',
            'options' => [
                'label' => 'Keywords',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [],
            ],
            'attributes' => [
                'required' => false,
                'multiple' => true,
            ],
        ]);
        $this->add([//http://www.codingdrama.com/bootstrap-markdown/
            'name' => 'publicNotes',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Public notes',
                'required' => false,
            ],
            'attributes' => [
                'required' => false,
                'data-provide' => 'markdown',
                'data-parser' => 'CommonMark',
                'rows' => 8,
            ],
            'filters' => [
                ['name' => 'StripTags'],
            ],
        ]);

        $this->add([//http://www.codingdrama.com/bootstrap-markdown/
            'name' => 'adminNotes',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Admin notes',
                'required' => false,
            ],
            'attributes' => [
                'required' => false,
                'data-provide' => 'markdown',
                'data-parser' => 'CommonMark',
                'rows' => 8,
            ],
        ]);

        $this->add([
            'name' => 'adminTags',
            'type' => 'Select',
            'options' => [
                'label' => 'Admin tags',
                'empty_option' => '',
                'placeholder' => 'Select tags or type new ones...',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [],
            ],
            'attributes' => [
                'required' => false,
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
            'name' => 'inactivationReason',
            'type' => 'Text',
            'options' => [
                'label' => 'Inactivation reason',
                'required' => true,
            ],
            'attributes' => [
                'placeholder' => 'ex. Book lost',
                'maxlength' => '255',
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

    /**
    *
    * @param LibraryOptions $libraryOptions
    * @return self
    */
    public function setLibraryOptions(LibraryOptions $libraryOptions)
    {
        if ($libraryOptions->useCollections) {
            $this->get('collectionId')->setAttribute('disabled', true);
        }
        // @todo add regex validator to callNumber
        if (isset($libraryOptions->callNumberRegex)) {
            $this->get('callNumber')->setOption('regex', $libraryOptions->callNumberRegex);
        }
        if (isset($libraryOptions->callNumberHelpText)) {
            $this->get('callNumber')->setOption('help-block', $libraryOptions->callNumberHelpText);
        }
        $this->libraryOptions = $libraryOptions;
        return $this;
    }

    public function getInputFilterSpecification()
    {
        return [
            'title' => [
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
                            'max' => 300,
                        ],
                    ],
                ],
            ],
            'authors' => [ //@todo add a validator
                'required' => false,
                'filters' => [
                //                     ['name' => 'SionModel\Filter\TrimStringArray'],
                ],
            ],
            'callNumber' => [
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
                            'max' => 50,
                        ],
                    ],
                ],
            ],
            'newCallNumber' => [
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
                            'max' => 50,
                        ],
                    ],
                ],
            ],
            'inLanguage' => [
                'required' => false,
            ],
            'collectionId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
            'bookEdition' => [
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
                            'max' => 50,
                        ],
                    ],
                ],
            ],
            'publicationId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
            'category' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 500,
                        ],
                    ],
                ],
            ],
            'isbn' => [
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
                            'max' => 30,
                        ],
                    ],
                ],
            ],
            'numberOfPages' => [
                'required' => false,
//                 'allow_empty' => true,
//                 'continue_if_empty' => true,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_INTEGER,
                        ],
                    ],
                ],
            ],
            'copyrightYear' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_INTEGER,
                        ]
                    ],
                ],
            ],
            'publisher' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ],
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
            'publishingPlace' => [
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

            'keywords' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringToLower'],
                    ['name' => 'SionModel\Filter\SortArray'],
                ],
            ],
            'publicNotes' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
            'adminNotes' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'adminTags' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringToLower'],
                    ['name' => 'SionModel\Filter\SortArray'],
                ],
            ],
            'isActive' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'inactivationReason' => [
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
        ];
    }

//     public function getData($flag = FormInterface::VALUES_NORMALIZED)
//     {
//         $values = parent::getData($flag);

//         //check if we need to set any
//     }
}
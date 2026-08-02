<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Laminas\InputFilter\InputFilterProviderInterface;
use Books\Model\LibraryOptions;
use SionModel\Filter\ToBit;
use Laminas\Filter\ToInt;

class BookForm extends SionForm implements InputFilterProviderInterface
{
    /**
     * @var LibraryOptions $libraryOptions
     */
    protected $libraryOptions;

    public function __construct()
    {
        parent::__construct('book');

        $this->add([
            'name' => 'libraryId',
            'type' => 'Hidden',
        ]);

        $this->add([
            'name' => 'withinLibraryId',
            'type' => 'Number',
            'options' => [
                'label' => 'Barcode',
            ],
            'attributes' => [
                'required' => true,
                'min'         => 0,
//                 'max'         => 100,
                'step'        => 1,
//                 'value'       => 50,
//                 'inclusive'   => true,
            ],
        ]);
        $this->add([
            'name' => 'nextWithinLibraryId',
            'type' => 'Button',
            'options' => [
                'label' => 'Use next free barcode',
            ],
            'attributes' => [
                'id'     => "nextWithinLibraryId",
                'class' => 'btn btn-default'
            ],
        ]);
        $this->add([
            'name' => 'title',
            'type' => 'Text',
            'options' => [
                'label' => 'Title',
            ],
            'attributes' => [
                'required' => true,
                'maxlength' => '300',
            ],
        ]);

        $this->add([
            'name' => 'authors',
            'type' => 'Select',
            'options' => [
                'label' => 'Author(s)',
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
            'name' => 'authorsText',
            'type' => 'Text',
            'options' => [
                'label' => 'Author(s) [separated by `|`]',
            ],
            'attributes' => [
                'maxlength' => '500',
            ],
        ]);

        $this->add([
            'name' => 'callNumber',
            'type' => 'Text',
            'options' => [
                'label' => 'Call number'
            ],
            'attributes' => [
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'newCallNumber',
            'type' => 'Text',
            'options' => [
                'label' => 'New call number',
                'help-block' => 'Use this field when the printed call number should be changed. It will be added to the next labels to be printed.'
            ],
            'attributes' => [
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'collectionId',
            'type' => 'Select',
            'options' => [
                'label' => 'Collection',
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
            ],
            'attributes' => [
                'min'         => 0,
                'max'         => 6000,
                'step'        => 'any',
                'inclusive'   => true,
            ],
        ]);
        $this->add([
            'name' => 'publishedYear',
            'type' => 'Number',
            'options' => [
                'label' => 'Year published',
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
        $this->get('collectionId')->setAttribute('disabled', ! $libraryOptions->useCollections);
        $callNumber = $this->get('callNumber');
        $newCallNumber = $this->get('newCallNumber');
        // @todo add regex validator to callNumber
        if (isset($libraryOptions->callNumberRegex)) {
            $callNumber->setAttribute('data-regex', $libraryOptions->callNumberRegex);
            $newCallNumber->setAttribute('data-regex', $libraryOptions->callNumberRegex);
        }
        if (isset($libraryOptions->callNumberPlaceholder)) {
            $callNumber->setAttribute('placeholder', $libraryOptions->callNumberPlaceholder);
            $newCallNumber->setAttribute('placeholder', $libraryOptions->callNumberPlaceholder);
        }
        if (isset($libraryOptions->callNumberHelpText)) {
            $callNumber->setOption('help-block', $libraryOptions->callNumberHelpText);
        }
        //control the empty option and the default collection
        /** @var \Laminas\Form\Element\Select $collectionId */
        $collectionId = $this->get('collectionId');
        if (false === $libraryOptions->allowCollectionlessBooks) { //disable the empty option
            $collectionId->setEmptyOption(null);
        } else {
            $collectionId->setEmptyOption('');
        }
        if (! isset($this->data) && isset($libraryOptions->mainCollectionId)) {
            $collectionId->setValue($libraryOptions->mainCollectionId);
        }

        if (is_bool($libraryOptions->requireCallNumbers)) {
            $callNumber->setAttribute('required', $libraryOptions->requireCallNumbers);
            $this->getInputFilter()->get('callNumber')->setRequired($libraryOptions->requireCallNumbers);
        }

        $this->libraryOptions = $libraryOptions;
        return $this;
    }

    public function setData($data)
    {
        if (isset($this->libraryOptions) && isset($this->libraryOptions->libraryId)) {
            $data['libraryId'] = $this->libraryOptions->libraryId;
        }
        $return = parent::setData($data);
        return $return;
    }

    public function getInputFilterSpecification()
    {
        return [
            'libraryId' => [
                'required' => true,
                'filters' => [
                    ['name' => ToInt::class],
                ],
            ],
            'withinLibraryId' => [
                'required' => true,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_INTEGER,
                        ]
                    ],
                ],
            ],
            'title' => [
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
            'authorsText' => [
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
            ],
            'callNumber' => [
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
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
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
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
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
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
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
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                    ['name' => ToInt::class],
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
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
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
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
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
                            'type' => \Laminas\Filter\ToNull::TYPE_INTEGER,
                        ],
                    ],
                ],
            ],
            'publishedYear' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_INTEGER,
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
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
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
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
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
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
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
                    [
                        'name' => ToBit::class,
                        'options' => [
                            'null_defaults_to' => true,
                        ],
                    ],
                ],
            ],
            'inactivationReason' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    [
                        'name' => 'ToNull',
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
        ];
    }

//     public function getData($flag = FormInterface::VALUES_NORMALIZED)
//     {
//         $values = parent::getData($flag);

//         //check if we need to set any
//     }
}

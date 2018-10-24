<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Zend\InputFilter\InputFilterProviderInterface;

class PublicationForm extends SionForm implements InputFilterProviderInterface
{
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
            'name' => 'authorsAll',
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
            'name' => 'hasNoExplictEditionNumber',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Publication has no explicit edition number?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'inLanguage',
            'type' => 'Select',
            'options' => [
                'label' => 'Language',
                'required' => true,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
        ]);
        $this->add([
            'name' => 'editorsAll', //same value options as authorsAll
            'type' => 'Select',
            'options' => [
                'label' => 'Editor(s)',
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
            'name' => 'translatorsAll', //only show persons
            'type' => 'Select',
            'options' => [
                'label' => 'Translator',
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
            'name' => 'illustratorsAll', //only show persons
            'type' => 'Select',
            'options' => [
                'label' => 'Illustrator',
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
            'name' => 'description',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Description',
                'required' => false,
            ],
            'attributes' => [
                'rows' => 5,
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
            'name' => 'mainPublicationId',
            'type' => 'Select',
            'options' => [
                'label' => 'Main publication (use for outdated editions)',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => false,
                'value_options' => [],
            ],
        ]);
        $this->add([
            'name' => 'translatedFromPublicationId',
            'type' => 'Select',
            'options' => [
                'label' => 'Translated from',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => false,
                'value_options' => [],
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
            'name' => 'bookFormatType',
            'type' => 'Select',
            'options' => [
                'label' => 'Book format',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
        ]);

        $this->add([
            'name' => 'url1',
            'type' => 'Url',
            'options' => [
                'label' => 'URL 1',
                'required' => false,
                'uriHandler' => 'Zend\Uri\Http',
                'allowRelative' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
            ],
        ]);
        $this->add([
            'name' => 'url1Label',
            'type' => 'Select',
            'options' => [
                'label' => 'URL 1 Label',
                'required' => false,
                'empty_option' => '',
                'disable_inarray_validator' => true,
                'unselected_value' => '',
            ],
        ]);
        $this->add([
            'name' => 'url2',
            'type' => 'Url',
            'options' => [
                'label' => 'URL 2',
                'required' => false,
                'uriHandler' => 'Zend\Uri\Http',
                'allowRelative' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
            ],
        ]);
        $this->add([
            'name' => 'url2Label',
            'type' => 'Select',
            'options' => [
                'label' => 'URL 2 Label',
                'required' => false,
                'disable_inarray_validator' => true,
                'empty_option' => '',
                'unselected_value' => '',
            ],
        ]);
        $this->add([
            'name' => 'url3',
            'type' => 'Url',
            'options' => [
                'label' => 'URL 3',
                'required' => false,
                'uriHandler' => 'Zend\Uri\Http',
                'allowRelative' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
            ],
        ]);
        $this->add([
            'name' => 'url3Label',
            'type' => 'Select',
            'options' => [
                'label' => 'URL 3 Label',
                'required' => false,
                'disable_inarray_validator' => true,
                'empty_option' => '',
                'unselected_value' => '',
            ],
        ]);
        $this->add([
            'name' => 'volumeNumber',
            'type' => 'Text',
            'options' => [
                'label' => 'Volume number',
                'required' => false,
            ],
            'attributes' => [
                'maxlength' => '25',
            ],
        ]);
        $this->add([
            'name' => 'containedIn',
            'type' => 'Text',
            'options' => [
                'label' => 'Contained in',
                'required' => true,
            ],
            'attributes' => [
                'placeholder' => 'ex. Revista Vínculo 2014-10',
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'containedInIsbn',
            'type' => 'Text',
            'options' => [
                'label' => 'Contained in ISBN',
                'required' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. 9780030426599',
                'maxlength' => '30',
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
        $this->add([
            'name' => 'resourceId',
            'type' => 'Select',
            'options' => [
                'label' => 'Access level',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [
                    'publication_public' => 'Public',
                    'publication_user' => 'Authenticated users',
                    'publication_institute' => 'Institute users',
                    'publication_patres' => 'Schoenstatt Fathers',
                ],
            ],
            'attributes' => [
                'value' => 'publication_public',
            ],
            
        ]);
        $this->add([
            'name' => 'isAccessibleForFree',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Accessible for free?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'isScientificWork',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Scientific work?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'hasNoISBN',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Publication has no ISBN?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'isRevisedWithBookInHand',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Data has been revised with book in hand?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
//         $this->add([
//             'name' => 'publishDataAsJsonLd',
//             'type' => 'Checkbox',
//             'options' => [
//                 'label' => 'Publish data in search engines?',
//                 'checked_value' => '1',
//                 'unchecked_value' => '0',
//                 'use_hidden_element' => true,
//             ],
//             'attributes' => [
//                 'value'   => '0',
//             ],
//         ]);
        $this->add([
            'name' => 'isFormallyPublished',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Is formally published?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);

        $this->add([//http://www.codingdrama.com/bootstrap-markdown/
            'name' => 'editionNotes',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Edition notes',
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
        $this->add([
            'name' => 'categoryId',
            'type' => 'Select',
            'options' => [
                'label' => 'Category',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
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
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Submit',
                'id' => 'submit',
                'class' => 'btn-primary'
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
//         $this->getInputFilter()->get('numberOfPages')->setValidatorChain(new ValidatorChain());
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
            'authorsAll' => [ //@todo add a validator
                'required' => false,
                'filters' => [
                //                     ['name' => 'SionModel\Filter\TrimStringArray'],
                ],
            ],
            'editorsAll' => [ //@todo add a validator
                'required' => false,
                'filters' => [
                //                     ['name' => 'SionModel\Filter\TrimStringArray'],
                ],
            ],
            'translatorsAll' => [ //@todo add a validator
                'required' => false,
                'filters' => [
                //                     ['name' => 'SionModel\Filter\TrimStringArray'],
                ],
            ],
            'illustratorsAll' => [ //@todo add a validator
                'required' => false,
                'filters' => [
                //                     ['name' => 'SionModel\Filter\TrimStringArray'],
                ],
            ],
            'inLanguage' => [
                'required' => false,
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
            'hasNoExplictEditionNumber' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'description' => [
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
                            'max' => 3000,
                        ],
                    ],
                ],
            ],
            'mainPublicationId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
            'translatedFromPublicationId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
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
            'bookFormatType' => [
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
            ],
            'url1' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'url1Label' => [
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
            ],
            'url2' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'url2Label' => [
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
            ],
            'url3' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'url3Label' => [
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
            ],

            'volumeNumber' => [
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
                            'max' => 25,
                        ],
                    ],
                ],
            ],
            'containedIn' => [
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
            'containedInIsbn' => [
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
            'keywords' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringToLower'],
                    ['name' => 'SionModel\Filter\SortArray'],
                ],
            ],
            'resourceId' => [
                'required' => true,
            ],
            'isAccessibleForFree' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'isScientificWork' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'hasNoISBN' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'isRevisedWithBookInHand' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
//             'publishDataAsJsonLd' => [
//                 'required' => false,
//                 'filters' => [
//                     ['name' => 'SionModel\Filter\ToBit']
//                 ],
//             ],
            'isFormallyPublished' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'editionNotes' => [
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
            'categoryId' => [
                'required' => false,
                'filters' => [
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
        ];
    }

//     public function getData($flag = FormInterface::VALUES_NORMALIZED)
//     {
//         $values = parent::getData($flag);

//         //check if we need to set any
//     }
}

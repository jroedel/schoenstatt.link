<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Zend\InputFilter\InputFilterProviderInterface;
use Books\Model\LibraryOptions;

class EventForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('event');

        $this->add([
            'name' => 'titleEn',
            'type' => 'Text',
            'options' => [
                'label' => 'Title (English)',
            ],
            'attributes' => [
                'required' => true,
                'maxlength' => '200',
            ],
        ]);
        $this->add([
            'name' => 'titleEs',
            'type' => 'Text',
            'options' => [
                'label' => 'Title (Spanish)',
            ],
            'attributes' => [
                'required' => true,
                'maxlength' => '200',
            ],
        ]);
        $this->add([
            'name' => 'titleDe',
            'type' => 'Text',
            'options' => [
                'label' => 'Title (German)',
            ],
            'attributes' => [
                'required' => true,
                'maxlength' => '200',
            ],
        ]);
        $this->add([
            'name' => 'titlePt',
            'type' => 'Text',
            'options' => [
                'label' => 'Title (Portuguese)',
            ],
            'attributes' => [
                'required' => true,
                'maxlength' => '200',
            ],
        ]);
        $this->add([
            'name' => 'titleFr',
            'type' => 'Text',
            'options' => [
                'label' => 'Title (French)',
            ],
            'attributes' => [
                'required' => true,
                'maxlength' => '200',
            ],
        ]);
        $this->add([
            'name' => 'originalLanguage',
            'type' => 'Select',
            'options' => [
                'label' => 'Original language',
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
        ]);
        $this->add([
            'name' => 'country',
            'type' => 'Select',
            'options' => [
                'label' => 'Country',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
        ]);
        $this->add([
            'name' => 'descriptionEn',
            'type' => 'Text',
            'options' => [
                'label' => 'Description (English)',
                'required' => false,
            ],
            'attributes' => [
                'maxlength' => '1000',
            ],
        ]);
        $this->add([
            'name' => 'descriptionEs',
            'type' => 'Text',
            'options' => [
                'label' => 'Description (Spanish)',
                'required' => false,
            ],
            'attributes' => [
                'maxlength' => '1000',
            ],
        ]);
        $this->add([
            'name' => 'descriptionDe',
            'type' => 'Text',
            'options' => [
                'label' => 'Description (German)',
                'required' => false,
            ],
            'attributes' => [
                'maxlength' => '1000',
            ],
        ]);
        $this->add([
            'name' => 'descriptionPt',
            'type' => 'Text',
            'options' => [
                'label' => 'Description (Portuguese)',
                'required' => false,
            ],
            'attributes' => [
                'maxlength' => '1000',
            ],
        ]);
        $this->add([
            'name' => 'descriptionFr',
            'type' => 'Text',
            'options' => [
                'label' => 'Description (French)',
                'required' => false,
            ],
            'attributes' => [
                'maxlength' => '1000',
            ],
        ]);
        $this->add([
            'name' => 'startDate',
            'type' => 'Date',
            'options' => [
                'label' => 'Start date',
                //'format' => 'Y-m-d',
            ],
            'attributes' => [
                'min' => '1850-01-01',
                'step' => 'any',
                'required' => true,
            ],
        ]);
        $this->add([
            'name' => 'durationInDays',
            'type' => 'Number',
            'options' => [
                'label' => 'Duration (in days)',
                'help-block' => null,
                'required' => true,
            ],
            'attributes' => [
                'min'         => 0,
                'max'         => 100,
                'step'        => 1,
                'value'       => 50,
                'inclusive'   => true,
            ],
        ]);
        $this->add([
            'name' => 'accuracy',
            'type' => 'Select',
            'options' => [
                'label' => 'Accuracy',
                'help-block' => null,
                'required' => true,
                'value_options' => [
                    'day' => 'day',
                    'week' => 'week',
                    'month' => 'month',
                    'season' => 'season',
                    'year' => 'year'
                ],
            ],
        ]);
        $this->add([
            'name' => 'bestTextQuality',
            'type' => 'Select',
            'options' => [
                'label' => 'Best text quality',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [
                    'A' => 'A',
                    'B' => 'B',
                    'C' => 'C',
                    'D' => 'D',
                    'E' => 'E',
                    'F' => 'F',
                    'G' => 'G',
                ],
            ],
        ]);
        
        $this->add([
            'name' => 'tags',
            'type' => 'Select',
            'options' => [
                'label' => 'Tags',
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
            'name' => 'adminTags',
            'type' => 'Select',
            'options' => [
                'label' => 'Admin tags',
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
            'name' => 'aclResourceId',
            'type' => 'Select',
            'options' => [
                'label' => 'Access level',
                'required' => true,
                'value_options' => [],
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
        
//We won't allow editing the legacy columns
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
        return [
            'titleEn' => [
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
                            'max' => 200,
                        ],
                    ],
                ],
            ],
            'titleEs' => [
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
                            'max' => 200,
                        ],
                    ],
                ],
            ],
            'titleDe' => [
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
                            'max' => 200,
                        ],
                    ],
                ],
            ],
            'titlePt' => [
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
                            'max' => 200,
                        ],
                    ],
                ],
            ],
            'titleFr' => [
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
                            'max' => 200,
                        ],
                    ],
                ],
            ],
            'originalLanguage' => [
                'required' => false,
            ],
            'country' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
            'descriptionEn' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
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
            'descriptionEs' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
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
            'descriptionDe' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
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
            'descriptionPt' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
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
            'descriptionFr' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
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
            'startDate' => [
                'required' => true,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToDateTime'],
                ],
            ],
            'durationInDays' => [
                'required' => true,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_INTEGER,
                        ]
                    ],
                ],
            ],
            'accuracy' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
            'bestTextQuality' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
            'aclResourceId' => [
                'required' => true,
            ],
            'tags' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringToLower'],
                    ['name' => 'SionModel\Filter\SortArray'],
                ],
            ],
            'adminTags' => [
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
        ];
    }
}
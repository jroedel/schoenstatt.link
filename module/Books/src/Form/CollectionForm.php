<?php
namespace Books\Form;

use Books\Model\LibraryTable;
use SionModel\Form\SionForm;
use SionModel\Form\InputFilterProviderInterface;
use SionModel\Form\ChoiceDomain;
use SionModel\Form\CsrfSpec;
use SionModel\Form\CheckboxDomain;
use SionModel\Form\InputTypeRules;

class CollectionForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('collection');
        $this->setAttribute('method', 'post');
        $this->add([
            'name' => 'libraryId',
            'type' => 'Hidden',
            'options' => [
                'required' => true,
            ],
        ]);
        $this->add([
            'name' => 'name',
            'type' => 'Text',
            'options' => [
                'label' => 'Name',
                'required' => true,
            ],
            'attributes' => [
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'abbreviation',
            'type' => 'Text',
            'options' => [
                'label' => 'Abbreviation',
                'required' => true,
                'help-block' => 'This abbreviation will help sort search results. Keep it short to preserve database space since it is added to each book.',
            ],
            'attributes' => [
                'maxlength' => '12',
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
                'rows' => '3',
                'maxlength' => '1000',
            ],
        ]);
        $this->add([
            'name' => 'callNumberHelpText',
            'type' => 'Text',
            'options' => [
                'label' => 'Call number help text',
                'required' => false,
            ],
            'attributes' => [
                'maxlength' => '255',
                'rows' => '3',
            ],
        ]);
        $this->add([
            'name' => 'callNumberExplanation',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Call number explanation',
                'required' => true,
            ],
            'attributes' => [
                'maxlength' => '1000',
            ],
        ]);
        $this->add([
            'name' => 'mainShowDisplay',
            'type' => 'Select',
            'options' => [
                'label' => 'Main view format',
                'required' => true,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => false,
                'value_options' => LibraryTable::MAIN_SHOW_DISPLAY_VALUE_OPTIONS,
            ],
            'attributes' => [
                'maxlength' => '50',
                'value' => LibraryTable::MAIN_SHOW_DISPLAY_DEFAULT,
            ],
        ]);
        $this->add([
            'name' => 'sortTextFormat',
            'type' => 'Text',
            'options' => [
                'label' => 'Sort text format',
                'required' => false,
                'help-block' => 'Format text to pass to sprintf. See '
                . 'https://secure.php.net/manual/en/function.sprintf.php for more info. The first parameter passed is '
                . 'the collection abbreviation followed by the capture groups of the call number.',
            ],
            'attributes' => [
                'placeholder' => '%1$s%2$-8s%3$04d%4$03d%5$03d',
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'requireCallNumbers',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Require call numbers?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'callNumberRegex',
            'type' => 'Text',
            'options' => [
                'label' => 'Call number regex',
                'required' => true,
            ],
            'attributes' => [
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'enforceCallNumberRegex',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Enforce call number regex?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'labelLine1',
            'type' => 'Text',
            'options' => [
                'label' => 'Label line 1',
                'required' => true,
                'help-block' => 'The three label line fields describe how to format a label for the spine of a book.',
            ],
            'attributes' => [
                'placeholder' => ':short_category',
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'labelLine2',
            'type' => 'Text',
            'options' => [
                'label' => 'Label line 2',
                'required' => true,
            ],
            'attributes' => [
                'placeholder' => '$2',
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'labelLine3',
            'type' => 'Text',
            'options' => [
                'label' => 'Label line 3',
                'required' => true,
            ],
            'attributes' => [
                'placeholder' => '$3',
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'defaultCheckoutTimePeriodInDays',
            'type' => 'Number',
            'options' => [
                'label' => 'Default checkout time period (days)',
                'required' => false,
            ],
            'attributes' => [
                'min'         => 1,
                'max'         => 365,
                'step'        => 1,
                'value'       => 14,
                'inclusive'   => true,
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

    public function getInputFilterSpecification()
    {
        return [
            'security' => CsrfSpec::forElement($this->get('security')),
            'libraryId' => [
                'required' => true,
            ],
            'name' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 100,
                        ],
                    ],
                ],
            ],
            'abbreviation' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 12,
                        ],
                    ],
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
                            'type' => \SionModel\Filter\ToNull::TYPE_STRING,
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
            'callNumberHelpText' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_STRING,
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
            'callNumberExplanation' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_STRING,
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
            'mainShowDisplay' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_STRING,
                        ],
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
                    ...ChoiceDomain::validators($this->get('mainShowDisplay')),
                ],
            ],
            'sortTextFormat' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripNewlines'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_STRING,
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
            'requireCallNumbers' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
                'validators' => CheckboxDomain::validators($this->get('requireCallNumbers')),
            ],
            'callNumberRegex' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_STRING,
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
            'enforceCallNumberRegex' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
                'validators' => CheckboxDomain::validators($this->get('enforceCallNumberRegex')),
            ],
            'labelLine1' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_STRING,
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
            'labelLine2' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_STRING,
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
            'labelLine3' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_STRING,
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
            'defaultCheckoutTimePeriodInDays' => [
                'required' => false,
                'filters' => [
                    ...InputTypeRules::filters($this->get('defaultCheckoutTimePeriodInDays')),
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_INTEGER,
                        ],
                    ],
                ],
                'validators' => InputTypeRules::number($this->get('defaultCheckoutTimePeriodInDays')),
            ],
            'adminNotes' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'isActive' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
                'validators' => CheckboxDomain::validators($this->get('isActive')),
            ],
        ];
    }
}

<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Zend\InputFilter\InputFilterProviderInterface;

class LibraryForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('library');
        $this->setAttribute('method', 'post');
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
            'name' => 'callNumberPlaceholder',
            'type' => 'Text',
            'options' => [
                'label' => 'Call number placeholder',
                'required' => false,
            ],
            'attributes' => [
                'maxlength' => '50',
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
            'name' => 'filiationId',
            'type' => 'Select',
            'options' => [
                'label' => 'Filiation',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => false,
                'value_options' => [],
            ],
            'attributes' => [
            ],
        ]);
        $this->add([
            'name' => 'contactPersonId',
            'type' => 'Select',
            'options' => [
                'label' => 'Contact person',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => false,
                'value_options' => [],
            ],
            'attributes' => [
            ],
        ]);
        $this->add([
            'name' => 'contactEmail',
            'type' => 'Email',
            'options' => [
                'label' => 'Contact email',
                'required' => false,
            ],
            'attributes' => [
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'mainShowDisplay',
            'type' => 'Select',
            'options' => [
                'label' => 'Main library view screen',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => false,
                'value_options' => [],
            ],
            'attributes' => [
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'useCollections',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Use collections?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);


        $this->add([
            'name' => 'allowCollectionlessBooks',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Allow collectionless books?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'mainCollectionId',
            'type' => 'Select',
            'options' => [
                'label' => 'Main collection',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => false,
                'value_options' => [],
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
            'name' => 'checkoutBooksRole',
            'type' => 'Select',
            'options' => [
                'label' => 'Who can checkout books?',
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
            'attributes' => [
                'required' => false,
            ],
        ]);
        $this->add([
            'name' => 'viewRole',
            'type' => 'Select',
            'options' => [
                'label' => 'Library visibility',
                'empty_option' => null,
                'value_options' => [],
            ],
            'attributes' => [
                'required' => true,
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
            'name' => 'barcodeText',
            'type' => 'Text',
            'options' => [
                'label' => 'Barcode text',
                'required' => true,
            ],
            'attributes' => [
                'placeholder' => 'ex. Bibliotheca Sion',
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'createCheckoutsIfCheckingInANonCheckedOutBook',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Create checkouts if checking in a non-checked out book?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'defaultCheckoutPersonId',
            'type' => 'Select',
            'options' => [
                'label' => 'Default checkout person',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => false,
                'value_options' => [],
                'help-block' => 'When books are checked in without a preceding checkout, they\'ll be checked out under this person.',
            ],
        ]);
        $this->add([
            'name' => 'defaultCheckoutTimePeriodInDays',
            'type' => 'Number',
            'options' => [
                'label' => 'Default checkout time period (days)',
                'required' => true,
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
            'name' => 'enableCheckouts',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Enable checkouts?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'checkoutPersonListKind',
            'type' => 'Select',
            'options' => [
                'label' => 'Person list for checkouts',
                'required' => true,
                'help-block'    => 'This controls which person list will be displayed on the checkout form.',
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
            'name' => [
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
                            'max' => 100,
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
            'callNumberPlaceholder' => [
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
            'callNumberHelpText' => [
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
            'callNumberExplanation' => [
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
            'filiationId' => [
                'required' => false,
            ],
            'contactPersonId' => [
                'required' => false,
            ],
            'contactEmail' => [
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
            'mainShowDisplay' => [
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
                            'max' => 50,
                        ],
                    ],
                ],
            ],
            'useCollections' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'allowCollectionlessBooks' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'mainCollectionId' => [
                'required' => false,
            ],
            'requireCallNumbers' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'callNumberRegex' => [
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
            'enforceCallNumberRegex' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'checkoutBooksRole' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
            'viewRole' => [
                'required' => true,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
            'labelLine1' => [
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
            'labelLine2' => [
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
            'labelLine3' => [
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
            'barcodeText' => [
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
            'createCheckoutsIfCheckingInANonCheckedOutBook' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'defaultCheckoutPersonId' => [
                'required' => false,
            ],
            'defaultCheckoutTimePeriodInDays' => [
                'required' => true,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_INTEGER,
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
            'isActive' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'enableCheckouts' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'checkoutPersonListKind' => [
                'required' => true,
            ],
        ];
    }
}

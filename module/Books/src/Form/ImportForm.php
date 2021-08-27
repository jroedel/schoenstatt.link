<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Zend\InputFilter\InputFilterProviderInterface;

class ImportForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('library-import');
        $this->setAttribute('method', 'post');
        $this->add([
            'name' => 'name',
            'type' => 'Text',
            'options' => [
                'label' => 'Import name',
                'required' => true,
            ],
            'attributes' => [
                'maxlength' => '100',
            ],
        ]);
        $this->add([
            'name' => 'libraryId',
            'type' => 'Hidden',
            'options' => [
                'label' => 'Library',
                'required' => true,
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
                'rows' => '4',
                'maxlength' => '1000',
            ],
        ]);
        /**
         * @todo remove this element and allow uploading of file
         */
        $this->add([
            'name' => 'filePath',
            'type' => 'Text',
            'options' => [
                'label' => 'File path',
                'required' => true,
            ],
            'attributes' => [
            ],
        ]);
        $this->add([
            'name' => 'worksheet',
            'type' => 'Text',
            'options' => [
                'label' => 'Worksheet',
                'required' => true,
            ],
            'attributes' => [
            ],
        ]);

        $this->add([
            'name' => 'isCompleteImport',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Complete import?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
                'help-block' => 'Complete imports will delete books that are not found within.',
            ],
            'attributes' => [
                'value'   => '0',
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
        $this->add([
            'name' => 'import',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Import data',
                'class' => 'btn-warning'
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
            'libraryId' => [
                'required' => true,
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
            'filePath' => [
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
            'worksheet' => [
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
            'isCompleteImport' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
        ];
    }
}

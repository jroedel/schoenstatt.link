<?php
namespace Bible\Form;

use SionModel\Form\SionForm;
use Zend\InputFilter\InputFilterProviderInterface;
use Bible\Filter\NormalizeUtf8;
use Zend\Filter\StripTags;
use Zend\Filter\StripNewlines;
use Zend\Filter\StringTrim;
use Zend\Filter\ToNull;
use Zend\Validator\StringLength;

class GreekRootForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('greek_root');

        $this->add([
            'name' => 'paradigmPart1',
            'type' => 'Text',
            'options' => [
                'label' => 'Paradigm part 1',
            ],
            'attributes' => [
                'required' => false,
                'maxlength' => '100',
            ],
        ]);
        $this->add([
            'name' => 'paradigmPart2',
            'type' => 'Text',
            'options' => [
                'label' => 'Paradigm part 2',
            ],
            'attributes' => [
                'required' => false,
                'maxlength' => '100',
            ],
        ]);
        $this->add([
            'name' => 'paradigmPart3',
            'type' => 'Text',
            'options' => [
                'label' => 'Paradigm part 3',
            ],
            'attributes' => [
                'required' => false,
                'maxlength' => '100',
            ],
        ]);
        $this->add([
            'name' => 'paradigmPart4',
            'type' => 'Text',
            'options' => [
                'label' => 'Paradigm part 4',
            ],
            'attributes' => [
                'required' => false,
                'maxlength' => '100',
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
            'paradigmPart1' => [
                'required' => false,
                'filters' => [
                    ['name' => NormalizeUtf8::class],
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 100,
                        ],
                    ],
                ],
            ],
            'paradigmPart2' => [
                'required' => false,
                'filters' => [
                    ['name' => NormalizeUtf8::class],
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 100,
                        ],
                    ],
                ],
            ],
            'paradigmPart3' => [
                'required' => false,
                'filters' => [
                    ['name' => NormalizeUtf8::class],
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 100,
                        ],
                    ],
                ],
            ],
            'paradigmPart4' => [
                'required' => false,
                'filters' => [
                    ['name' => NormalizeUtf8::class],
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 100,
                        ],
                    ],
                ],
            ],
        ];
    }
}

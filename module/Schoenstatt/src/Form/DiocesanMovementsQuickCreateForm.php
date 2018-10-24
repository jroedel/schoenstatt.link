<?php
namespace Schoenstatt\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;
use SionModel\Form\SionForm;

class DiocesanMovementsQuickCreateForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct($name = null)
    {
        // we want to ignore the name passed
        parent::__construct('create_quick_diocesan_movements');

        $this->add([
            'type' => 'Zend\Form\Element\Collection',
            'name' => 'assocations',
            'options' => [
                'label' => 'Please choose categories for this product',
                'count' => 2,
                'should_create_template' => true,
                'allow_add' => true,
                'target_element' => [
                    'type' => 'Schoenstatt\Form\SimpleDiocesanMovementFieldset'
                ],
            ],
        ]);

        $this->add([
            'name' => 'security',
            'type' => 'csrf',
            'options' => [
                'csrf_options' => [
                    'timeout' => 600,
                ],
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
            'roleId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull'],
                ],
            ],
            'roleTitle' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ]],
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
            'sort' => [
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
            'isSinglePosition' => [
                'required' => false,
            ],
            'isMainRole' => [
                'required' => false,
            ],
            'isActive' => [
                'required' => false,
            ],
        ];
    }
}

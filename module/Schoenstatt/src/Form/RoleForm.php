<?php
namespace Schoenstatt\Form;

use Laminas\InputFilter\InputFilterProviderInterface;
use SionModel\Form\SionForm;
use SionModel\Form\CsrfSpec;
use SionModel\Form\CheckboxDomain;
use SionModel\Form\ChoiceDomain;
use SionModel\Form\InputTypeRules;

class RoleForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct($name = null)
    {
        // we want to ignore the name passed
        parent::__construct('edit_role');

        $this->add([
            'name' => 'associationId',
            'type' => 'Select',
            'options' => [
                'label' => 'Association',
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
        ]);

        $this->add([
            'name' => 'roleTitle',
            'type' => 'Select',
            'options' => [
                'label' => 'Role title',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [],
            ],
            'attributes' => [
                'id'        => 'roleSelect',
                'maxlength' => '50',
            ],
        ]);

        $this->add([
            'name' => 'sort',
            'type' => 'Number',
            'options' => [
                'label' => 'Sort value',
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
            'name' => 'isSinglePosition',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Does role only have one person at a time?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
        ]);

        $this->add([
            'name' => 'isMainRole',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Is main role for the association?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
        ]);

        $this->add([
            'name' => 'isMainContact',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Is main contact for the association?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);

        $this->add([
            'name' => 'isActive',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Active',
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

//      $this->add([
//          'name' => 'delete',
//          'type' => 'Button',
//          'attributes' => [
//              'value' => 'Delete',
//              'class' => 'btn-danger',
//              'data-toggle' => 'modal',
//              'data-target' => '.bs-example-modal-sm'
//          ],
//      ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'security' => CsrfSpec::forElement($this->get('security')),
            //Explicit rather than inherited from the element. A role with no association
            //is not a role; the domain itself stays the element's InArray over the
            //association list its factory supplies.
            'associationId' => [
                'required' => true,
                'validators' => ChoiceDomain::validators($this->get('associationId')),
            ],
            'roleTitle' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
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
                    ...InputTypeRules::filters($this->get('sort')),
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_INTEGER,
                        ]
                    ],
                ],
                'validators' => InputTypeRules::number($this->get('sort')),
            ],
            'isSinglePosition' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
                'validators' => CheckboxDomain::validators($this->get('isSinglePosition')),
            ],
            'isMainRole' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
                'validators' => CheckboxDomain::validators($this->get('isMainRole')),
            ],
            'isMainContact' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
                'validators' => CheckboxDomain::validators($this->get('isMainContact')),
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

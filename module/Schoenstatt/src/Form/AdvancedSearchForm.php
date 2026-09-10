<?php
namespace Schoenstatt\Form;

use SionModel\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use SionModel\Form\ChoiceDomain;
use SionModel\Form\CheckboxDomain;

class AdvancedSearchForm extends Form implements InputFilterProviderInterface
{
    public function __construct($name = null)
    {
        // we want to ignore the name passed
        parent::__construct('advanced-search');
        $this->setAttribute('method', 'GET');

        $this->add([
            'name' => 'associationKind',
            'type' => 'Select',
            'options' => [
                'label' => 'Association type',
                'empty_option' => '',
                'unselected_value' => '',
                'column-size' => 'md-4'
            ],
        ]);
        $this->add([
            'name' => 'associationCountry',
            'type' => 'Select',
            'options' => [
                'label' => 'Association country',
                'empty_option' => '',
                'unselected_value' => '',
                'column-size' => 'md-4'
            ],
        ]);
        $this->add([
            'name' => 'roleTitle',
            'type' => 'Select',
            'options' => [
                'label' => 'Role',
                'empty_option' => '',
                'unselected_value' => '',
                'column-size' => 'md-4'
            ],
            'attributes' => [
                'multiple' => true,
                'id' => 'roleTitleSelect',
            ],
        ]);
        $this->add([
            'name' => 'onlyMainRoles',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Only main roles?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'search',
            'type' => 'Text',
            'options' => [
                'label' => 'Multi-search',
                'column-size' => 'md-4'
            ],
            'attributes' => [
                'min' => 3,
            ],
        ]);

        $this->add([
            'name' => 'personName',
            'type' => 'Text',
            'options' => [
                'label' => 'Person name',
                'column-size' => 'md-4 col-md-offset-4'
            ],
            'attributes' => [
                'min' => 3,
            ],
        ]);

        $this->add([
            'name' => 'clear',
            'type' => 'Button',
            'options' => [
                'label' => 'Clear form',
            ],
            'attributes' => [
                'id'     => "clear",
                'class' => 'btn btn-default'
            ],
        ]);
        $this->add([
            'name' => 'submit',
            'type' => 'Submit',
            'options' => [
                'label' => 'Search',
            ],
            'attributes' => [
                'class' => 'btn-primary',
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'onlyMainRoles' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
                'validators' => CheckboxDomain::validators($this->get('onlyMainRoles')),
            ],
            'associationCountry' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull'],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => 2,
                            'encoding' => 'UTF-8',
                        ],
                    ],
                    //The StringLength above bounds the value; this bounds the *domain*. Both
                    //belong: a two-character string is not a country code, and this form's
                    //values reach a WHERE clause rather than a column, so the length check
                    //alone left 675 nonsense codes that produce an empty result page.
                    ...ChoiceDomain::validators($this->get('associationCountry')),
                ],
            ],
            'roleTitle' => [
                'required' => false,
                'validators' => ChoiceDomain::validators($this->get('roleTitle')),
            ],
            'associationKind' => [
                'required' => false,
                'validators' => ChoiceDomain::validators($this->get('associationKind')),
            ],
            'personName' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        //201 = the longest full name the database can hold: FirstName and
                        //LastName are varchar(100) each, plus the space between them. So
                        //this cannot reject a name that exists — the longest in the data
                        //is 43 characters — while an unbounded text input reaches the
                        //query builder with a megabyte in it. Same shape as
                        //`associationCountry` above; the fuzz harness listed this field as
                        //an accepted gap until 2026-08-14.
                        'name' => 'StringLength',
                        'options' => [
                            'max' => 201,
                            'encoding' => 'UTF-8',
                        ],
                    ],
                ],
            ],
            'search' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                ],
            ],
        ];
    }
}

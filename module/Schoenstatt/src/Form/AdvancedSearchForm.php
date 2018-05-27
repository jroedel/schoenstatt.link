<?php
namespace Schoenstatt\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

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
		        ],
		    ],
		    'roleTitle' => [
		        'required' => false,
		    ],
		    'associationKind' => [
		        'required' => false,
		    ],
		    'personName' => [
		        'required' => false,
		        'filters' => [
		            ['name' => 'StripTags'],
		            ['name' => 'StripNewlines'],
		            ['name' => 'StringTrim'],
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
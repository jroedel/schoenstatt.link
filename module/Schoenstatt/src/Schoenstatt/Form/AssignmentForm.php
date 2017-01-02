<?php
namespace Schoenstatt\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;
use SionModel\Form\SionForm;

class AssignmentForm extends SionForm implements InputFilterProviderInterface
{
	public function __construct($name = null)
	{
		// we want to ignore the name passed
		parent::__construct('edit_assignment');

		$this->add([
		    'name' => 'assignmentId',
		    'type' => 'Hidden',
		]);

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
		    'name' => 'roleId',
		    'type' => 'Select',
		    'options' => [
		        'label' => 'Role',
		        'empty_option' => '',
		        'unselected_value' => '',
		        'value_options' => [],
		    ],
		]);
		$this->add([
		    'name' => 'startDate',
		    'type' => 'Date',
		    'options' => [
		        'label' => 'Start date',
		        'format' => 'Y-m-d',
		    ],
		    'attributes' => [
		        'min' => '1900-01-01',
		        'step' => 'any',
		        'required' => false,
		    ],
		]);

		$this->add([
		    'name' => 'endDate',
		    'type' => 'Date',
		    'options' => [
		        'label' => 'End date',
		        'format' => 'Y-m-d',
		    ],
		    'attributes' => [
		        'min' => '1900-01-01',
		        'step' => 'any',
		        'required' => false,
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
		    'name' => 'delete',
		    'type' => 'Button',
		    'attributes' => [
		        'value' => 'Delete',
		        'class' => 'btn-danger',
		        'data-toggle' => 'modal',
		        'data-target' => '.bs-example-modal-sm'
		    ],
		]);
	}

	public function getInputFilterSpecification()
	{
		return [
    		'associationId' => [
    		    'required' => false,
    		    'filters' => [
    		        ['name' => 'ToInt'],
    		        ['name' => 'ToNull'],
    		    ],
    		],
    		'roleId' => [
    		    'required' => false,
    		    'filters' => [
    		        ['name' => 'ToInt'],
    		        ['name' => 'ToNull'],
    		    ],
    		],
    		'startDate' => [
    		    'required' => false,
    		    'filters' => [
    		        ['name' => 'SionModel\Filter\ToDateTime'],
    		    ],
    		],
    		'endDate' => [
    		    'required' => false,
    		    'filters' => [
    		        ['name' => 'SionModel\Filter\ToDateTime'],
    		    ],
    		],
		];
	}
}
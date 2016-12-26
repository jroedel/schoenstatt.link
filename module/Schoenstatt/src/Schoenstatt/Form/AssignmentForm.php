<?php
namespace Schoenstatt\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

class AssignmentForm extends Form implements InputFilterProviderInterface
{
	public function __construct($name = null)
	{
		// we want to ignore the name passed
		parent::__construct('edit_assignment');

		$this->add(array(
		    'name' => 'assignmentId',
		    'type' => 'Hidden',
		));
		$this->add(array(
			'name' => 'scopeName',
			'type' => 'Text',
			'options' => array(
				'label' => 'Role Scope',
			),
			'attributes' => array(
            	'required' => true,
			    'readonly' => true,
			),
		));

		$this->add(array(
			'name' => 'roleTitle',
			'type' => 'Text',
			'options' => array(
				'label' => 'Role',
			),
			'attributes' => array(
				'readonly' => true
			),

		));
		$this->add(array(
				'name' => 'assignmentName',
				'type' => 'Text',
				'options' => array(
					'label' => 'Assignment',
				),
				'attributes' => array(
	            	'readonly' => true,
				),
		));

		$this->add(array(
			'name' => 'startDate',
			'type' => 'Date',
			'options' => array(
				'label' => 'Start Date',
				'warning' => true
			),
		    'attributes' => array(
		        'min' => '1900-01-01',
		        'step' => 'any'
		    )
		));
		$this->add(array(
			'name' => 'endDate',
			'type' => 'Date',
			'options' => array(
				'label' => 'End Date',
			),
		    'attributes' => array(
		        'min' => '1900-01-01',
		        'step' => 'any'
		    )
		));
		$this->add(array(
			'name' => 'security',
			'type' => 'csrf',
		    'options' => array(
                'csrf_options' => array(
                     'timeout' => 600,
                ),
	        ),
		));
		$this->add(array(
			'name' => 'submit',
			'type' => 'Submit',
			'attributes' => array(
				'value' => 'Submit',
				'id' => 'submit',
				'class' => 'btn-primary'
			),
		));

		$this->add(array(
		    'name' => 'delete',
		    'type' => 'Button',
		    'attributes' => array(
		        'value' => 'Delete',
		        'class' => 'btn-danger',
		        'data-toggle' => 'modal',
		        'data-target' => '.bs-example-modal-sm'
		    ),
		));
	}

	public function getInputFilterSpecification()
	{
		return array(
			'endDate' => array(
				'name' => 'endDate',
				'required' => false,
                'filters' => array(
                    array('name' => 'SionModel\Filter\ToDateTime'),
                ),
			),
			'startDate' => array(
				'name' => 'startDate',
				'required' => false,
                'filters' => array(
                    array('name' => 'SionModel\Filter\ToDateTime'),
                ),
			),
			'assignmentId' => array(
	            'validators' => array(
	                array(
	                    'name'    => 'Zend\Validator\Db\RecordExists',
	                    'options' => array(
	                        'table' => 'a_data_role_assignment',
	                        'field' => 'AssignmentId',
	                        'adapter' => \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter(),
	                        'messages' => array(
	                            \Zend\Validator\Db\RecordExists::ERROR_NO_RECORD_FOUND => 'Assignment not found in database'
	                        ),
	                    ),
	                ),
	            ),
		        'filters' => array(
		            array('name' => 'ToInt'),
		        ),
			)
		);
	}
}
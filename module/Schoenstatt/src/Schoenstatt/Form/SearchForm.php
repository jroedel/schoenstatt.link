<?php
namespace Schoenstatt\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

class SearchForm extends Form implements InputFilterProviderInterface
{
	public function __construct($name = null)
	{
		// we want to ignore the name passed
		parent::__construct('search');
		$this->setAttribute('method', 'GET');

		$this->add(array(
		    'name' => 'search',
		    'type' => 'Text',
		    'options' => array(
		        'label' => '',
		    ),
		    'attributes' => array(
		        'required' => false,
		        'class' => 'input-lg',
		        'placeholder' => 'Search'
		    ),
		));
		$this->add(array(
			'name' => 'showPhotos',
			'type' => 'Checkbox',
			'options' => array(
		        'required' => false,
				'label' => 'Show Photos',
		        'unchecked_value' => 'false',
			    'checked_value' => 'true',
			    'use_hidden_element' => false,
			),
		    'attributes' => array(
		        'value'           => 'false',
		    ),
		));
		$this->add(array(
			'name' => 'exMembers',
			'type' => 'Checkbox',
			'options' => array(
		        'required' => false,
				'label' => 'Show Ex-members',
		        'unchecked_value' => 'false',
			    'checked_value' => 'true',
			    'use_hidden_element' => false,
			),
		    'attributes' => array(
		        'value'           => 'false',
		    ),
		));
	}

	public function getInputFilterSpecification()
	{
		return array(
		    'showPhotos' => array(
		        'required' => false,
		        'validators' => array(),
                'filters' => array(
                    array('name' => 'Boolean'),
                ),
		    ),
		    'exMembers' => array(
		        'required' => false,
		        'filters' => array(
		            array('name' => 'Boolean'),
		        ),
		    ),
		    'search' => array(
		        'required' => false,
		        'filters' => array(
		            array('name' => 'ToNull'),
// 		            array('name' => 'SionModel\Filter\ToAscii'),
		        ),
		    ),
		);
	}
}
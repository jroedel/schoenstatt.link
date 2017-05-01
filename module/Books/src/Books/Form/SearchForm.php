<?php
namespace Books\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

class SearchForm extends Form implements InputFilterProviderInterface
{
	public function __construct($name = null)
	{
		// we want to ignore the name passed
		parent::__construct('search');
		$this->setAttribute('method', 'GET');

		$this->add([
		    'name' => 'search',
		    'type' => 'Text',
		    'options' => [
		        'label' => '',
		    ],
		    'attributes' => [
		        'required' => false,
		        'class' => 'input-lg search-query',
		        'placeholder' => 'Search',
		        'size' => 50,
		    ],
		]);
		$this->add([
		    'name' => 'category',
		    'type' => 'Text',
		    'options' => [
		        'label' => '',
		    ],
		    'attributes' => [
		        'required' => false,
		        'size' => 50,
		    ],
		]);
        $this->add([
            'name' => 'libraryId',
            'type' => 'Hidden',
            'options' => [
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
			'name' => 'submit',
			'type' => 'Submit',
			'attributes' => [
				'value' => 'Search',
				'id' => 'submit',
				'class' => 'btn-primary'
			],
		]);
	}

	public function getInputFilterSpecification()
	{
		return [
		    'search' => [
		        'required' => false,
		        'filters' => [
		            ['name' => 'StringTrim'],
		            ['name' => 'ToNull'],
		        ],
		        'validators' => [
		            [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 3,
                            'encoding' => 'UTF-8',
                        ],
	                ],
		        ],
		    ],
            'libraryId' => [
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
		];
	}
}
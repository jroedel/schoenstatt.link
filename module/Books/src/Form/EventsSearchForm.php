<?php
namespace Books\Form;

use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;

class EventsSearchForm extends Form implements InputFilterProviderInterface
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
        ]);
        $this->add([
            'name' => 'collectionId',
            'type' => 'Select',
            'options' => [
                'label' => 'Collection',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
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
                            'type' => \Laminas\Filter\ToNull::TYPE_INTEGER,
                        ]
                    ],
                ],
            ],
            'collectionId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
        ];
    }
}

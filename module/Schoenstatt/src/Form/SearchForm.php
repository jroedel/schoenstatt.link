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

        $this->add([
            'name' => 'search',
            'type' => 'Text',
            'options' => [
                'label' => '',
            ],
            'attributes' => [
                'required' => false,
                'class' => 'input-lg',
                'placeholder' => 'Search'
            ],
        ]);
        $this->add([
            'name' => 'showPhotos',
            'type' => 'Checkbox',
            'options' => [
                'required' => false,
                'label' => 'Show Photos',
                'unchecked_value' => 'false',
                'checked_value' => 'true',
                'use_hidden_element' => false,
            ],
            'attributes' => [
                'value'           => 'false',
            ],
        ]);
        $this->add([
            'name' => 'exMembers',
            'type' => 'Checkbox',
            'options' => [
                'required' => false,
                'label' => 'Show Ex-members',
                'unchecked_value' => 'false',
                'checked_value' => 'true',
                'use_hidden_element' => false,
            ],
            'attributes' => [
                'value'           => 'false',
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'showPhotos' => [
                'required' => false,
                'validators' => [],
                'filters' => [
                    ['name' => 'Boolean'],
                ],
            ],
            'exMembers' => [
                'required' => false,
                'filters' => [
                    ['name' => 'Boolean'],
                ],
            ],
            'search' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull'],
//                  ['name' => 'SionModel\Filter\ToAscii'],
                ],
            ],
        ];
    }
}

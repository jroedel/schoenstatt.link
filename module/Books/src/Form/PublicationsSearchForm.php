<?php
namespace Books\Form;

use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;

class PublicationsSearchForm extends Form implements InputFilterProviderInterface
{
    public function __construct($name = null)
    {
        // we want to ignore the name passed
        parent::__construct('search_publications');
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
                'placeholder' => 'Search Catalogs',
                'size' => 50,
            ],
        ]);
        $this->add([
            'name' => 'showEditionsSeparately',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Show editions separately?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'includeDataSources',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Show data sources?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'inLanguage',
            'type' => 'Select',
            'options' => [
                'label' => 'Languages',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [],
            ],
            'attributes' => [
                'multiple'    => true,
                'placeholder' => 'Languages',
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
            'showEditionsSeparately' => [
                'required' => false,
            ],
            'inLanguage' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringToLower'],
                    ['name' => 'SionModel\Filter\SortArray'],
                ],
            ],
        ];
    }
}

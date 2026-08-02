<?php
namespace Bible\Form;

use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Filter\ToNull;
use Laminas\Validator\StringLength;
use Laminas\Filter\StringTrim;
use Laminas\Form\Element\Text;

class BibleSearchForm extends Form implements InputFilterProviderInterface
{
    public function __construct($name = null)
    {
        // we want to ignore the name passed
        parent::__construct('search');
        $this->setAttribute('method', 'GET');

        $this->add([
            'name' => 'search',
            'type' => Text::class,
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
        //@todo activate translationId selection
//         $this->add([
//             'name' => 'translationId',
//             'type' => 'Select',
//             'options' => [
//                 'label' => 'Translation',
//                 'required' => false,
//                 'empty_option' => '',
//                 'unselected_value' => '',
//                 'value_options' => [],
//             ],
//         ]);
        //@todo add new/old testament selection
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
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'min' => 3,
                            'encoding' => 'UTF-8',
                        ],
                    ],
                ],
            ],
            'translationId' => [
                'required' => false,
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
        ];
    }
}

<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Validator\NotEmpty;

class CheckoutForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('checkout');

        $this->add([
            'name' => 'bookIds',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Book Ids',
                'help-block' => 'One barcode per line'
            ],
            'attributes' => [
                'required' => true,
                'tabindex' => 1,
                'rows' => 8,
            ],
        ]);

        $this->add([
            'name' => 'personId',
            'type' => 'Select',
            'options' => [
                'label' => 'Who\'s checking out?',
                'required' => true,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
            'attributes' => [
                'tabindex' => 2,
            ],
        ]);

        $this->add([
            'name' => 'adminNotes',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Notes',
                'required' => false,
            ],
            'attributes' => [
                'required' => false,
                'rows' => 3,
                'tabindex' => 3,
            ],
            'filters' => [
                ['name' => 'StripTags'],
            ],
        ]);
        $this->add([
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Submit',
                'id' => 'submit',
                'class' => 'btn-primary',
                'tabindex' => 4,
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'bookIds' => [
                'required' => true,
                'filters' => [
                    ['name' => 'Books\Filter\BookList'],
                ],
                'validators' => [
                    [
                        'name' => 'Zend\Validator\NotEmpty',
                        'options' => [
                            'type' => NotEmpty::EMPTY_ARRAY
                        ],
                    ],
                ],
            ],
            'adminNotes' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'ToNull'],
                ],
            ],
        ];
    }
}
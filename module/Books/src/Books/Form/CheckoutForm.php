<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Zend\InputFilter\InputFilterProviderInterface;

class CheckoutForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('checkout');

        $this->add([
            'name' => 'bookIds',
            'type' => 'Select',
            'options' => [
                'label' => 'Book Ids',
                'empty_option' => '',
                'unselected_value' => '',
//                 'disable_inarray_validator' => true,
                'value_options' => [],
            ],
            'attributes' => [
                'tabindex' => 1,
                'multiple'  => true,
                'required' => true,
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
//                 'disable_inarray_validator' => true,
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
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
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
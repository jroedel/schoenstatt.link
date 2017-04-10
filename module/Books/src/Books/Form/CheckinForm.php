<?php
namespace Books\Form;

use SionModel\Form\SionForm;

class CheckinForm extends SionForm
{
    public function __construct()
    {
        parent::__construct('checkin');

        $this->add([
            'name' => 'bookIds',
            'type' => 'Select',
            'options' => [
                'label' => 'Book Ids to check in',
                'empty_option' => '',
                'unselected_value' => '',
//                 'disable_inarray_validator' => true,
                'value_options' => [],
            ],
            'attributes' => [
                'multiple'  => true,
                'required' => true,
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
        ];
    }
}
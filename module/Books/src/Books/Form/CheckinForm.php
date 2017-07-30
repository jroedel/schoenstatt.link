<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Validator\NotEmpty;

class CheckinForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('checkin');

        $this->add([
            'name' => 'bookIds',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Book Ids to check in',
            ],
            'attributes' => [
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
        ];
    }
}
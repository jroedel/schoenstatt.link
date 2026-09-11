<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use SionModel\Form\InputFilterProviderInterface;
use SionModel\Validator\NotEmpty;
use SionModel\Form\CsrfSpec;

class CheckinForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('checkin');

        $this->add([
            'name' => 'withinLibraryIds',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Book Ids to check in',
                'help-block' => 'One barcode per line'
            ],
            'attributes' => [
                'required' => true,
                'tabindex' => 1,
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
            'security' => CsrfSpec::forElement($this->get('security')),
            'withinLibraryIds' => [
                'required' => false,
                'filters' => [
                    ['name' => 'Books\Filter\BookList'],
                ],
                'validators' => [
                    [
                        'name' => 'SionModel\Validator\NotEmpty',
                        'options' => [
                            'type' => NotEmpty::EMPTY_ARRAY
                        ],
                    ],
                ],
            ],
        ];
    }
}

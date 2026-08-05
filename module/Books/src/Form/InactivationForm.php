<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\NotEmpty;

class InactivationForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('inactivation');

        $this->add([
            'name' => 'withinLibraryIds',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Book Ids',
                'help-block' => 'One barcode per line'
            ],
            'attributes' => [
                'required' => true,
                'tabindex' => 1,
                'rows' => 6,
            ],
        ]);

        $this->add([
            'name' => 'inactivationReason',
            'type' => 'Text',
            'options' => [
                'label' => 'Inactivation reason',
                'required' => false,
            ],
            'attributes' => [
                'required' => false,
                'tabindex' => 3,
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
            'withinLibraryIds' => [
                'required' => true,
                'filters' => [
                    ['name' => 'Books\Filter\BookList'],
                ],
                'validators' => [
                    [
                        'name' => 'Laminas\Validator\NotEmpty',
                        'options' => [
                            'type' => NotEmpty::EMPTY_ARRAY
                        ],
                    ],
                ],
            ],
            'inactivationReason' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'ToNull'],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            //lib_books.inactivation_reason is varchar(255).
                            //BookForm bounds the same column already.
                            'max' => 255,
                        ],
                    ],
                ],
            ],
        ];
    }
}

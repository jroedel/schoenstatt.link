<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use SionModel\Form\InputFilterProviderInterface;
use SionModel\Validator\NotEmpty;
use SionModel\Form\CsrfSpec;
use SionModel\Form\ChoiceDomain;

class CheckoutForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('checkout');

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
            'security' => CsrfSpec::forElement($this->get('security')),
            'withinLibraryIds' => [
                'required' => true,
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
            //Named here only to say `required` out loud. The domain is the element's:
            //Select::getInputSpecification() builds an InArray over the value options
            //CheckoutForms fills in with the library's borrowers, and a specification
            //merges into that input rather than replacing it (see
            //SionModel\Form\ChoiceDomain). Restating the InArray would be a second copy
            //of the same haystack.
            'personId' => [
                'required' => true,
                'validators' => ChoiceDomain::validators($this->get('personId')),
            ],
            'adminNotes' => [
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
                            //lib_checkouts.AdminNotes is varchar(300) — note it
                            //is not the 255 the other notes columns use.
                            'max' => 300,
                        ],
                    ],
                ],
            ],
        ];
    }
}

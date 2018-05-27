<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Zend\InputFilter\InputFilterProviderInterface;

class MassCheckoutForm extends SionForm implements InputFilterProviderInterface
{

    public function __construct()
    {
        parent::__construct('checkout');

        $this->add([
            'name' => 'checkout',
            'type' => 'Collection',
            'options' => [
                'label' => 'Book Ids',
                'target_element' => [
                    'type' => 'Books\Form\MassCheckoutFieldset',
                ],
                'count' => 4,
                'allow_add' => true,
                'allow_remove' => true,
                'should_create_template' => true,
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
                'class' => 'btn-primary',
                'tabindex' => 4,
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
        ];
    }
}
<?php
namespace Books\Form;

use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Validator\NotEmpty;
use Carbon\Carbon;
use Zend\Form\Fieldset;

class MassCheckoutFieldset extends Fieldset implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('checkout');
        $today = new Carbon();
        $todayText = $today->format('Y-d-m');
        $this->add([
            'name' => 'checkedOutOn',
            'type' => 'Date',
            'options' => [
                'label' => 'Checked out on',
            ],
            'attributes' => [
                'required' => true,
                'value' => $todayText,
                'tabindex' => 1,
            ],
        ]);
        $this->add([
            'name' => 'bookIds',
            'type' => 'Text',
            'options' => [
                'label' => 'Book Ids',
                'help-block' => 'One barcode per line'
            ],
            'attributes' => [
                'required' => true,
                'tabindex' => 2,
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
                'tabindex' => 3,
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
        ];
    }
}
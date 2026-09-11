<?php
namespace Books\Form;

use Laminas\InputFilter\InputFilterProviderInterface;
use Carbon\Carbon;
use SionModel\Form\Fieldset;

class MassCheckoutFieldset extends Fieldset implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('checkout');

        $today = new Carbon();
        $todayText = $today->format('Y-m-d');
        $this->add([
            'name' => 'checkedOutOn',
            'type' => 'Date',
            'options' => [
                'label' => 'Checked out on',
            ],
            'attributes' => [
                'required' => false,
                'value' => $todayText,
                'min' => $todayText,
                'step' => 'any',
            ],
        ]);
        $this->add([
            'name' => 'withinLibraryIds',
            'type' => 'Text',
            'options' => [
                'label' => 'Book Ids',
                'help-block' => 'One barcode per line'
            ],
            'attributes' => [
                'required' => false,
                'rows' => 6,
            ],
        ]);

        $this->add([
            'name' => 'personId',
            'type' => 'Select',
            'options' => [
                'label' => 'Who\'s checking out?',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
                'allow_empty' => true,
                'continue_if_empty' => true,
            ],
            'attributes' => [
            ],
        ]);
    }

    /**
     * @todo Ideally validation would fail if personId is set but withinLibraryIds isn't or withinLibraryIds is set, but personId not
     * {@inheritDoc}
     * @see \Laminas\InputFilter\InputFilterProviderInterface::getInputFilterSpecification()
     */
    public function getInputFilterSpecification()
    {
        return [
            'checkedOutOn' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToDateTime'],
                ],
                'validators' => [
                    ['name' => 'SionModel\Validator\ParseableDate'],
                    [
                        'name' => 'SionModel\Validator\DateWithinRange',
                        'options' => ['min' => '2000-01-01', 'max' => 'today'],
                    ],
                ],
            ],
            'withinLibraryIds' => [
                'required' => false,
                'filters' => [
                    ['name' => 'Books\Filter\BookList'],
                ],
                //Allow empty for rows that aren't filled in
            ],
            'personId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull'],
                ],
            ],
        ];
    }
}

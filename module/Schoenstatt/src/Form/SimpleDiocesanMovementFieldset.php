<?php
namespace Schoenstatt\Form;

use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;

class SimpleDiocesanMovementFieldset extends Fieldset implements InputFilterProviderInterface
{
    public function __construct($name = null)
    {
        // we want to ignore the name passed
        parent::__construct('create_quick_diocesan_movements');

        $this->add([
            'name' => 'name',
            'type' => 'Text',
            'options' => [
                'label' => 'Diocese name',
                'required' => true,
            ],
            'attributes' => [
                'placeholder' => 'ex. Vallendar',
                'maxlength' => '200',
            ],
        ]);

        $this->add([
            'name' => 'addShrineMinistry',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Has shrine ministry organization?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'addPilgrimMovement',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Has pilgrim movement organization?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'addPilgrimMother',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Has pilgrim mother organization?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'addProfessionalsBranch',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Has professionals branch?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'addMadrugadores',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Has madrugadores branch?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'addWomensYouthBranch',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Has women\'s youth branch?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'addMensYouthBranch',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Has men\'s youth branch?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'addWomensBranch',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Has women\'s branch?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'addMothersBranch',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Has mother\'s branch?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'addMensBranch',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Has men\'s branch?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'addFamilyBranch',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Has family branch?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'addShrineMinistry' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'addPilgrimMovement' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'addPilgrimMother' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'addProfessionalsBranch' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'addMadrugadores' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'addWomensYouthBranch' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'addMensYouthBranch' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'addWomensBranch' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'addMothersBranch' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'addMensBranch' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'addFamilyBranch' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
        ];
    }
}

<?php
namespace Schoenstatt\Form;

use SionModel\Form\SionForm;
use SionModel\Form\InputFilterProviderInterface;
use SionModel\Form\CsrfSpec;
use SionModel\Form\ChoiceDomain;

class ImportFatherForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('import_person');
        $this->add([
            'name' => 'personId',
            'type' => 'Select',
            'options' => [
                'label' => 'Person to import',
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
        ]);

        $this->add([
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Import',
                'id' => 'submit',
                'class' => 'btn-primary'
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'security' => CsrfSpec::forElement($this->get('security')),
            'personId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull'],
                ],
                'validators' => ChoiceDomain::validators($this->get('personId')),
            ],
        ];
    }
}

<?php
namespace Schoenstatt\Form;

use Laminas\InputFilter\InputFilterProviderInterface;
use SionModel\Form\SionForm;
use Laminas\Json\Json;

class AssignmentForm extends SionForm implements InputFilterProviderInterface
{
    /**
    * @var string[] $roleTitlesValueOptions;
    */
    protected $roleTitlesValueOptions;

    protected $isPreparedForEdit = false;

    public function __construct($roleTitlesValueOptions)
    {
        // we want to ignore the name passed
        parent::__construct('create_assignment');

        $this->roleTitlesValueOptions = $roleTitlesValueOptions;

        $this->add([
            'name' => 'associationId',
            'type' => 'Select',
            'options' => [
                'label' => 'Association',
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);
        $this->add([
            'name' => 'roleId',
            'type' => 'Select',
            'options' => [
                'label' => 'Role',
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [],
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);
        $this->add([
            'name' => 'personId',
            'type' => 'Select',
            'options' => [
                'label' => 'Person',
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);
        $this->add([
            'name' => 'startDate',
            'type' => 'Date',
            'options' => [
                'label' => 'Start date',
                'format' => 'Y-m-d',
            ],
            'attributes' => [
                'min' => '1900-01-01',
                'step' => 'any',
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'startDatePrecision',
            'type' => 'Select',
            'options' => [
                'label' => 'How precisely the start date is known',
                'value_options' => \SionModel\Form\DatePrecision::valueOptions(),
                //filterSpec() owns the domain check, so the Select's automatic
                //InArray is off to leave exactly one — see DatePrecision.
                'disable_inarray_validator' => true,
            ],
            'attributes' => [
                'required' => false,
                'value' => \SionModel\Form\DatePrecision::DEFAULT_PRECISION,
            ],
        ]);

        $this->add([
            'name' => 'endDate',
            'type' => 'Date',
            'options' => [
                'label' => 'End date',
                'format' => 'Y-m-d',
            ],
            'attributes' => [
                'min' => '1900-01-01',
                'step' => 'any',
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'endDatePrecision',
            'type' => 'Select',
            'options' => [
                'label' => 'How precisely the end date is known',
                'value_options' => \SionModel\Form\DatePrecision::valueOptions(),
                //filterSpec() owns the domain check, so the Select's automatic
                //InArray is off to leave exactly one — see DatePrecision.
                'disable_inarray_validator' => true,
            ],
            'attributes' => [
                'required' => false,
                'value' => \SionModel\Form\DatePrecision::DEFAULT_PRECISION,
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

        $this->add([
            'name' => 'delete',
            'type' => 'Button',
            'attributes' => [
                'value' => 'Delete',
                'class' => 'btn-danger',
                'data-toggle' => 'modal',
                'data-target' => '.bs-example-modal-sm'
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'associationId' => [
                'required' => true,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull'],
                ],
            ],
            'roleId' => [
                'required' => true,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull'],
                ],
            ],
            'personId' => [
                'required' => true,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull'],
                ],
            ],
            'startDate' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToDateTime'],
                ],
                'validators' => [
                    ['name' => 'SionModel\Validator\ParseableDate'],
                ],
            ],
            'startDatePrecision' => \SionModel\Form\DatePrecision::filterSpec(),
            'endDate' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToDateTime'],
                ],
                'validators' => [
                    ['name' => 'SionModel\Validator\ParseableDate'],
                ],
            ],
            'endDatePrecision' => \SionModel\Form\DatePrecision::filterSpec(),
        ];
    }

    public function setData($data)
    {
        if (key_exists('associationId', $data) &&
            key_exists($data['associationId'], $this->roleTitlesValueOptions)
        ) {
            $this->get('roleId')->setValueOptions($this->roleTitlesValueOptions[$data['associationId']]);
        }
        return parent::setData($data);
    }

    /**
     * Get the roleTitlesValueOptions value
     * @return string[]
     */
    public function getRoleTitleValueOptions()
    {
        return $this->roleTitlesValueOptions;
    }

    /**
     *
     * @param string[] $roleTitlesValueOptions;
     * @return self
     */
    public function setRoleTitleValueOptions($roleTitlesValueOptions)
    {
        $this->roleTitlesValueOptions = $roleTitlesValueOptions;
        return $this;
    }

    public function getRolesJson()
    {
        return Json::encode($this->roleTitlesValueOptions);
    }
}

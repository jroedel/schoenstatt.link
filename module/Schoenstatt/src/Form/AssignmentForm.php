<?php
namespace Schoenstatt\Form;

use Laminas\InputFilter\InputFilterProviderInterface;
use SionModel\Form\ChoiceDomain;
use SionModel\Form\SionForm;
use App\Json;

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
                //This field is never stored — sch_assignments has no association column, the
                //association is reached through the role — so the only thing it does is pick
                //which roles setData() offers below. Constraining it is what makes that
                //narrowing meaningful: an associationId nobody offered used to leave the role
                //list unnarrowed, and roleId's fallback wide open.
                'validators' => ChoiceDomain::validators($this->get('associationId')),
            ],
            'roleId' => [
                'required' => true,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull'],
                ],
                //setData() has already narrowed this element to the submitted association's
                //roles by the time the specification is built, so in the normal case the
                //haystack is per-association — tighter than any static list. The fallback
                //covers the request where that did not happen; it is the union of the same
                //map, so the two can never disagree about what a role is.
                'validators' => ChoiceDomain::validators($this->get('roleId'), $this->allRoleIds()),
            ],
            'personId' => [
                'required' => true,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull'],
                ],
                'validators' => ChoiceDomain::validators($this->get('personId')),
            ],
            'startDate' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToDateTime'],
                ],
                'validators' => [
                    ['name' => 'SionModel\Validator\ParseableDate'],
                    [
                        'name' => 'SionModel\Validator\DateWithinRange',
                        'options' => ['min' => '1914-10-18', 'max' => '+10 years'],
                    ],
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
                    [
                        'name' => 'SionModel\Validator\DateWithinRange',
                        'options' => ['min' => '1914-10-18', 'max' => '+10 years'],
                    ],
                    [
                        'name' => 'SionModel\Validator\DateNotBefore',
                        'options' => ['field' => 'startDate', 'relatedLabel' => 'start date'],
                    ],
                ],
            ],
            'endDatePrecision' => \SionModel\Form\DatePrecision::filterSpec(),
        ];
    }

    /**
     * Narrow the role options to the roles of the submitted association, so that
     * roleId validates against the right list.
     *
     * The guard is not defensive padding. Only an int or a string can be an array
     * key, and key_exists() raises a TypeError on anything else — so a request
     * sending `associationId[]=x`, which takes one line of HTML to produce, used
     * to be an uncaught TypeError here. That is worse than the same class of bug
     * inside a filter, because this runs in setData(), *before* isValid(): no
     * validator could reject the value first, and no controller could guard it by
     * checking isValid(). It was a 500 with the whole submission lost.
     *
     * A hostile value now simply leaves the role options unnarrowed, and roleId's
     * own InArray rejects whatever came with it.
     *
     * @param  array<string, mixed>|\Traversable $data
     * @return self
     */
    public function setData($data)
    {
        $associationId = is_array($data) && key_exists('associationId', $data)
            ? $data['associationId']
            : null;

        if (
            (is_int($associationId) || is_string($associationId)) &&
            key_exists($associationId, $this->roleTitlesValueOptions)
        ) {
            $this->get('roleId')->setValueOptions($this->roleTitlesValueOptions[$associationId]);
        }
        return parent::setData($data);
    }

    /**
     * Every role id in the map, flattened across associations.
     *
     * The map is keyed by association because that is what the browser needs to narrow the
     * role picker. This is the same data read the other way round: the widest set of role
     * ids that is still a role, for a request where no narrowing happened.
     *
     * @return int[]
     */
    private function allRoleIds()
    {
        $ids = [];
        foreach ($this->roleTitlesValueOptions as $associationRoles) {
            if (! is_array($associationRoles)) {
                continue;
            }
            foreach (array_keys($associationRoles) as $roleId) {
                $ids[$roleId] = true;
            }
        }
        return array_keys($ids);
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

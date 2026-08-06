<?php
namespace Schoenstatt\Form;

class EditAssignmentForm extends AssignmentForm
{
    public function prepareforEdit()
    {
        $this->setName('edit_assignment');
        $this->get('associationId')->setAttribute('disabled', true);
        $this->get('roleId')->setAttribute('disabled', true);
        $this->get('personId')->setAttribute('disabled', true);
        //The precision fields belong in the group with the dates they describe.
        //A validation group is a whitelist and getData() returns only what is in
        //it, so omitting them would let the form render two working selects whose
        //value is discarded on save — the date's precision would silently never
        //change on edit, which is worse than not offering the field.
        $this->setValidationGroup([
            'startDate',
            'startDatePrecision',
            'endDate',
            'endDatePrecision',
            'security',
        ]);
        $this->isPreparedForEdit = true;
    }
}

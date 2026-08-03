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
        $this->setValidationGroup(['startDate', 'endDate', 'security']);
        $this->isPreparedForEdit = true;
    }
}

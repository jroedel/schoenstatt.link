<?php
namespace Books\View\Helper;

use Zend\Form\View\Helper\FormSelect;

class FormSelectWithoutOptions extends FormSelect
{
    public function renderOptions(array $options, array $selectedOptions = [])
    {
        $newOptions = [];
        foreach ($selectedOptions as $value) {
            if (isset($options[$value])) {
                $newOptions[$value] = $options[$value];
            }
        }
        return parent::renderOptions($newOptions, $selectedOptions);
    }
}

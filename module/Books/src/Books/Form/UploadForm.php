<?php
namespace Books\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

class UploadForm extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('upload-form');

        $this->add([
            'name' => 'fileUpload',
            'type' => 'File',
            'options' => [
                'label' => 'File upload',
                'required' => true,
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
    }

    public function getInputFilterSpecification()
    {
        return [
            'fileUpload' => [
                'required' => true,
            ],
        ];
    }
}

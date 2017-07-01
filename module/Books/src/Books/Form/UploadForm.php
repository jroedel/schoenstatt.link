<?php
namespace Books\Form;

use Zend\Form\Form;

class UploadForm extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('upload-form');

        $this->add([
            'name' => 'fileupload',
            'type' => 'File',
            'options' => [
                'label' => 'Excel file upload',
                'required' => true,
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'fileupload' => [
                'required' => true,
            ],
        ];
    }
}

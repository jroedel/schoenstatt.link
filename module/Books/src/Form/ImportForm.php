<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Laminas\InputFilter\InputFilterProviderInterface;

/**
 * Starting a spreadsheet import: what it is called, and the file.
 *
 * ## The two fields that are gone
 *
 * `filePath` was a **text box holding a path on the server**, carrying the standing
 * `@todo remove this element and allow uploading of file`. What a librarian typed into
 * it is on the record: import 14, created 2021-08-10 and still `pending`, says
 * `C:\Users\Ramon Vergara\Desktop\intento.xlsx`. It is now a file upload, handled by
 * App\Books\Import\SpreadsheetUpload and stored by App\Books\Import\ImportStorage.
 *
 * `worksheet` was a text box too, needing the sheet's name typed exactly and answering
 * a mistake with a 500. It has moved to the step after this one, where the file has
 * been read and the names can be offered as a list.
 *
 * ## The file element is rendered here and validated elsewhere
 *
 * Deliberately. The element exists so the upload row is marked up like every other row
 * on the page, but the pages that use this form are Symfony-served and the upload
 * arrives as a `Symfony\Component\HttpFoundation\File\UploadedFile`, not as
 * `$_FILES` state for `Laminas\InputFilter\FileInput` to reason about. Two mechanisms
 * inspecting one upload is how a file passes one and fails the other; there is one,
 * and it is SpreadsheetUpload. `getInputFilterSpecification()` therefore says nothing
 * about `file`, and `isValid()` does not answer for it.
 */
class ImportForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('library-import');
        $this->setAttribute('method', 'post');
        $this->setAttribute('enctype', 'multipart/form-data');
        $this->add([
            'name' => 'name',
            'type' => 'Text',
            'options' => [
                'label' => 'Import name',
                'required' => true,
                'help-block' => 'Something you will recognise later, such as "Jornada de trabajo, June".',
            ],
            'attributes' => [
                'maxlength' => '100',
            ],
        ]);
        $this->add([
            'name' => 'libraryId',
            'type' => 'Hidden',
            'options' => [
                'label' => 'Library',
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'description',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Description',
                'required' => false,
            ],
            'attributes' => [
                'rows' => '4',
                'maxlength' => '1000',
            ],
        ]);
        $this->add([
            'name' => 'file',
            'type' => 'File',
            'options' => [
                'label' => 'Spreadsheet',
                'required' => true,
                'help-block' => 'An .xlsx, .xls or .ods file. Download the template above if you do not have one yet.',
            ],
            'attributes' => [
                'accept' => '.xlsx,.xls,.ods',
            ],
        ]);

        $this->add([
            'name' => 'isCompleteImport',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Complete import?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
                'help-block' => 'Tick only if this file is the whole library. Every active book it does not '
                    . 'list will be marked inactive.',
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);

        $this->add([
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Upload and continue',
                'id' => 'submit',
                'class' => 'btn-primary'
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'name' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 100,
                        ],
                    ],
                ],
            ],
            'libraryId' => [
                'required' => true,
            ],
            'description' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 1000,
                        ],
                    ],
                ],
            ],
            'isCompleteImport' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
        ];
    }
}

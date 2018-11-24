<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Zend\InputFilter\InputFilterProviderInterface;
use Books\Model\EventTextTable;
use Zend\Validator\Identical;

class BlogForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('event');

        $this->add([
            'name' => 'title',
            'type' => 'Text',
            'options' => [
                'label' => 'Title',
            ],
            'attributes' => [
                'required' => true,
                'maxlength' => '200',
            ],
        ]);
//         $this->add([
//             'name' => 'slug',
//             'type' => 'Text',
//             'options' => [
//                 'label' => 'URL text',
//                 'help-block' => 'This field can only be edited on draft texts. '
//                     .'Be very careful to change this as it may break published links.'
//             ],
//             'attributes' => [
//                 'required' => false,
//                 'maxlength' => '50',
//             ],
//         ]);
        $this->add([//http://www.codingdrama.com/bootstrap-markdown/
            'name' => 'markdownText',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Blog text',
            ],
            'attributes' => [
                'required' => false,
                'data-provide' => 'markdown',
                'data-parser' => 'CommonMark',
                'rows' => 12,
            ],
            'filters' => [
                ['name' => 'StripTags'],
            ],
        ]);
        $this->add([
            'name' => 'inLanguage',
            'type' => 'Select',
            'options' => [
                'label' => 'Original language',
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [
                    'en' => 'English',
                    'de' => 'German',
                    'pt' => 'Portuguese',
                    'es' => 'Spanish',
                ],
            ],
            'attributes' => [
                'value' => 'en',
            ],
        ]);
        $this->add([
            'name' => 'tags',
            'type' => 'Select',
            'options' => [
                'label' => 'Tags',
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [],
            ],
            'attributes' => [
                'required' => false,
                'multiple' => true,
            ],
        ]);
        
        $this->add([
            'name' => 'isDraft',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Is draft?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);

//         $this->add([//http://www.codingdrama.com/bootstrap-markdown/
//             'name' => 'adminNotes',
//             'type' => 'Textarea',
//             'options' => [
//                 'label' => 'Admin notes',
//             ],
//             'attributes' => [
//                 'required' => false,
//                 'data-provide' => 'markdown',
//                 'data-parser' => 'CommonMark',
//                 'rows' => 8,
//             ],
//         ]);
        $this->add([
            'name' => 'kind',
            'type' => 'Hidden',
            'attributes' => [
                'value' => EventTextTable::TEXT_KIND_BLOG,
            ]
        ]);
//We won't allow editing the legacy columns
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
            'title' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 200,
                        ],
                    ],
                ],
            ],
            'inLanguage' => [
                'required' => true,
            ],
            'kind' => [
                'required' => true,
                'validators' => [
                    [
                        'name' => Identical::class,
                        'options' => [
                            'token' => EventTextTable::TEXT_KIND_BLOG,
                        ],
                    ],
                ]
            ],
            'tags' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringToLower'],
                    ['name' => 'SionModel\Filter\SortArray'],
                ],
            ],
            'isDraft' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'markdownText' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
//             'adminNotes' => [
//                 'required' => false,
//                 'filters' => [
//                     ['name' => 'StripTags'],
//                     ['name' => 'ToNull',
//                         'options' => [
//                             'type' => \Zend\Filter\ToNull::TYPE_STRING,
//                         ]
//                     ],
//                 ],
//             ],
        ];
    }
}

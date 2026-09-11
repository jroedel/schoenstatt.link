<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use SionModel\Form\InputFilterProviderInterface;
use Books\Model\EventTextTable;
use SionModel\Validator\Identical;
use SionModel\Form\ChoiceDomain;
use SionModel\Form\CsrfSpec;
use SionModel\Form\CheckboxDomain;

class TextForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('text');
        $this->setAttribute('method', 'POST');
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
        $this->add([//http://www.codingdrama.com/bootstrap-markdown/
            'name' => 'markdownText',
            'type' => 'Textarea',
            'options' => [
                //was 'Blog text' — this is the Fr. Kentenich texts form, and the blog it
                //was named after is gone. The old phrase stays in the catalogues as a
                //stale key until someone runs a translation export.
                'label' => 'Text',
            ],
            'attributes' => [
                'required' => false,
                'data-provide' => 'markdown',
                'data-parser' => 'CommonMark',
                'rows' => 12,
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
                    'it' => 'Italian',
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

        $this->add([
            'name' => 'kind',
            'type' => 'Hidden',
            'attributes' => [
                //jk-text, not blog. This form is the Fr. Kentenich texts form and it used
                //to stamp every row it created as a blog post — 2,753 of the 2,757 rows in
                //`texts` are jk-text, so the hidden default disagreed with the data. Changed
                //when the blog was removed; new texts now carry the kind the feature reads.
                'value' => EventTextTable::TEXT_KIND_JK_TEXT,
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
            'security' => CsrfSpec::forElement($this->get('security')),
            'title' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_STRING,
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
                'validators' => ChoiceDomain::validators($this->get('inLanguage')),
            ],
            'kind' => [
                'required' => true,
                'validators' => [
                    [
                        'name' => Identical::class,
                        'options' => [
                            'token' => EventTextTable::TEXT_KIND_JK_TEXT,
                            //Without `literal`, Identical reads the token as a key into the
                            //submitted data — see App\Books\LibraryDeleteForm, where the same
                            //default let a confirmation be bypassed. Here it meant a request
                            //carrying `jk-text=other` could store any kind it liked.
                            'literal' => true,
                        ],
                    ],
                ]
            ],
            'tags' => [
                'required' => false,
                'filters' => [
//                     ['name' => 'StringToLower'],
                    ['name' => 'SionModel\Filter\SortArray'],
                ],
            ],
            'isDraft' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
                'validators' => CheckboxDomain::validators($this->get('isDraft')),
            ],
            'markdownText' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
        ];
    }
}

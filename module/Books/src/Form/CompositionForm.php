<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Zend\Validator\EmailAddress;
use Zend\Validator\Regex;
use Zend\Filter\StringTrim;
use Zend\Filter\ToNull;
use Zend\Validator\StringLength;
use Zend\Filter\StripTags;
use Zend\Filter\StripNewlines;
use Zend\Filter\StringToLower;
use SionModel\Filter\SortArray;
use Zend\InputFilter\InputFilterProviderInterface;

class CompositionForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('composition');

        $urlLabels = [
            'Album' => 'Album',
            'Lyrics' => 'Lyrics',
            'Media' => 'Media',
            'Reference' => 'Reference',
        ];

        $this->add([
            'name' => 'name',
            'type' => 'Text',
            'options' => [
                'label' => 'Composition name',
            ],
            'attributes' => [
                'required' => true,
                'placeholder' => 'ex. María de la Alianza',
                'maxlength' => '200',
            ],
        ]);
        $this->add([
            'name' => 'disambiguatingDescription',
            'type' => 'Text',
            'options' => [
                'label' => 'Disambiguating subtitle',
                'help-block' => 'Please only use when composition needs to be distinguished from another similarly-named composition.',
            ],
            'attributes' => [
                'placeholder' => 'ex. Santo de la misa criolla',
                'maxlength' => '50',
            ],
        ]);

        $this->add([
            'name' => 'inLanguage',
            'type' => 'Select',
            'options' => [
                'label' => 'Language',
                'empty_option' => '',
                'unselected_value' => '',
                'required' => false,
                'value_options' => [],
            ],
        ]);
        $this->add([
            'name' => 'country',
            'type' => 'Select',
            'options' => [
                'label' => 'Country of origin',
                'empty_option' => '',
                'unselected_value' => '',
                'required' => false,
                'value_options' => [],// $this->customValueOptions['country'],
            ],
            'attributes' => [
                'required' => false
            ],
        ]);

        $this->add([
            'name' => 'yearPublished',
            'type' => 'Text',
            'options' => [
                'label' => 'Published date',
                'help-block' => 'The year is plenty; if a more specific date is available, use format YYYY-MM-DD'
            ],
            'attributes' => [
                'placeholder' => '2007',
            ],
        ]);
        $this->add([
            'name' => 'composersAll', //same value options as authorsAll
            'type' => 'Select',
            'options' => [
                'label' => 'Composer(s)',
                'empty_option' => '',
                'unselected_value' => '',
                'required' => false,
                'disable_inarray_validator' => true,
            ],
            'attributes' => [
                'maxlength' => '500',
                'multiple' => true,
            ],
        ]);
        $this->add([
            'name' => 'lyricistsAll', //same value options as authorsAll
            'type' => 'Select',
            'options' => [
                'label' => 'Lyricist(s)',
                'empty_option' => '',
                'unselected_value' => '',
                'required' => false,
                'disable_inarray_validator' => true,
            ],
            'attributes' => [
                'maxlength' => '500',
                'multiple' => true,
            ],
        ]);
//         tags
        $this->add([
            'name' => 'derivedFromCompositionId',
            'type' => 'Select',
            'options' => [
                'required' => false,
                'label' => 'Derived or translated from',
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [
                ],
            ],
            'attributes' => [
                'required' => false,
            ],
        ]);
        $this->add([
            'name' => 'chordProSpec',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Chord pro specification',
                'help-block' => 'See <a href="https://www.chordpro.org/">Chord pro markup</a>. '
                . 'The metadata will be automatically added afterwards. Html tags are not allowed.',
            ],
            'attributes' => [
                'maxlength' => '2000',
                'rows' => 8,
            ],
        ]);
        $this->add([
            'name' => 'lyrics',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Lyrics',
                'help-block' => 'If song is specified with chord pro, it\'s not '
                . 'necessary to fill in the lyrics separately.',
            ],
            'attributes' => [
                'maxlength' => '500',
                'rows' => 6,
            ],
        ]);
        $this->add([
            'name' => 'lilyPondSpec',
            'type' => 'Textarea',
            'options' => [
                'label' => 'LilyPond music notation',
                'required' => false,
                'help-block' => 'See <a href="http://lilypond.org/">LilyPond markup</a>.',
            ],
            'attributes' => [
                'maxlength' => '5000',
                'rows' => 8,
            ],
        ]);
//         musicalKey
//         alternateKey
//         alternateKeyLabel
        $this->add([
            'name' => 'copyrightInfo',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Copyright info',
                'required' => false,
                'help-block' => 'Include information about the copyright owner, contact information, '
                . 'and under what licence it has been published.',
            ],
            'attributes' => [
                'maxlength' => '500',
            ],
        ]);
        $this->add([
            'name' => 'copyrightContactEmail',
            'type' => 'Email',
            'options' => [
                'label' => 'Copyright contact email',
            ],
            'attributes' => [
                'required' => false,
                'maxlength' => '70',
            ],
        ]);
        $this->add([
            'name' => 'url1',
            'type' => 'Url',
            'options' => [
                'label' => 'Other URL 1',
                'uriHandler' => 'Zend\Uri\Http',
                'allowRelative' => false,
            ],
            'attributes' => [
                'required' => false,
                'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'url1Label',
            'type' => 'Select',
            'options' => [
                'label' => 'Other URL 1 Label',
                'empty_option' => '',
                'disable_inarray_validator' => true,
                'unselected_value' => '',
                'value_options' => $urlLabels,
            ],
            'attributes' => [
                'required' => false,
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'url2',
            'type' => 'Url',
            'options' => [
                'label' => 'Other URL 2',
                'uriHandler' => 'Zend\Uri\Http',
                'allowRelative' => false,
            ],
            'attributes' => [
                'required' => false,
                'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'url2Label',
            'type' => 'Select',
            'options' => [
                'label' => 'Other URL 2 Label',
                'disable_inarray_validator' => true,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => $urlLabels,
            ],
            'attributes' => [
                'required' => false,
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'url3',
            'type' => 'Url',
            'options' => [
                'label' => 'Other URL 3',
                'uriHandler' => 'Zend\Uri\Http',
                'allowRelative' => false,
            ],
            'attributes' => [
                'required' => false,
                'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'url3Label',
            'type' => 'Select',
            'options' => [
                'label' => 'Other URL 3 Label',
                'disable_inarray_validator' => true,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => $urlLabels,
            ],
            'attributes' => [
                'required' => false,
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'tags',
            'type' => 'Select',
            'options' => [
                'label' => 'Tags',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [],
                'help-block' => 'Tags regarding the content of the content, form or liturgical use of the song.',
            ],
            'attributes' => [
                'required' => false,
                'multiple' => true,
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
            'name' => [
                'required' => true,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 200,
                        ],
                    ],
                ],
            ],
            'disambiguatingDescription' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 50,
                        ],
                    ],
                ],
            ],
            'inLanguage' => [
                'required' => false,
            ],
            'country' => [
                'required' => false,
            ],
            'yearPublished' => [
                'required' => false,
                'validators' => [
                    [
                        'name' => Regex::class,
                        'options' => [
                            'pattern' => '/^(?:18|19|20)\d{2,2}(?:-[0-3]\d)?(?:-[0-3]\d)?$/',
                            'messageTemplates' => [
                                Regex::NOT_MATCH => 'Please enter a valid date. Remember to add a `0` before single digit month and day numbers.',
                            ],
                        ],
                    ],
                ],
                'filters' => [
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_INTEGER,
                        ],
                    ],
                ],
            ],
            'composersAll' => [ //@todo add a validator
                'required' => false,
            ],
            'lyricistsAll' => [ //@todo add a validator
                'required' => false,
            ],


            'copyrightInfo' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'copyrightContactEmail' => [
                'required' => false,
                'filters' => [
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    ['name' => EmailAddress::class],
                ],
            ],
            'derivedFromCompositionId' => [
                'required' => false,
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'lyrics' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'chordProSpec' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'lilyPondSpec' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],

            'url1' => [
                'required' => false,
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'url1Label' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'url2' => [
                'required' => false,
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'url2Label' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'url3' => [
                'required' => false,
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'url3Label' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'tags' => [
                'required' => false,
                'filters' => [
                    ['name' => StringToLower::class],
                    ['name' => SortArray::class],
                ],
            ],
        ];
    }
}

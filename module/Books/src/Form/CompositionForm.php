<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Zend\Validator\Regex;
use Zend\Filter\StringTrim;
use Zend\Filter\ToNull;
use Zend\Validator\StringLength;
use Zend\Filter\StripTags;
use Zend\Filter\StripNewlines;

class CompositionForm extends SionForm
{
    public function __construct()
    {
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
                'help-block' => 'This is the name that would be published in Google Maps (if applicable). '
                .'Several association types include `name formats` that insert this field within a commonly '
                .'used format, for example `Schoenstatt Shrine [name]`. This simplifies mass translation, ',
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
                'required' => true,
                'placeholder' => 'ex. Santo de la misa criolla',
                'maxlength' => '50',
            ],
        ]);
        
        $this->add([
            'name' => 'inLanguage',
            'type' => 'Select',
            'options' => [
                'label' => 'Language',
                'required' => true,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
        ]);
        $this->add([
            'name' => 'country',
            'type' => 'Select',
            'options' => [
                'label' => 'Country (if not international)',
                'empty_option' => '',
                'unselected_value' => '',
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
                'required' => false,
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
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
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
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
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
//         lyrics
//         chordProSpec
//         lilyPondSpec
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
                .'and under what licence it has been published.',
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
                'required' => false,
                'uriHandler' => 'Zend\Uri\Http',
                'allowRelative' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'url1Label',
            'type' => 'Select',
            'options' => [
                'label' => 'Other URL 1 Label',
                'required' => false,
                'empty_option' => '',
                'disable_inarray_validator' => true,
                'unselected_value' => '',
                'value_options' => $urlLabels,
            ],
            'attributes' => [
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'url2',
            'type' => 'Url',
            'options' => [
                'label' => 'Other URL 2',
                'required' => false,
                'uriHandler' => 'Zend\Uri\Http',
                'allowRelative' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'url2Label',
            'type' => 'Select',
            'options' => [
                'label' => 'Other URL 2 Label',
                'required' => false,
                'disable_inarray_validator' => true,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => $urlLabels,
            ],
            'attributes' => [
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'url3',
            'type' => 'Url',
            'options' => [
                'label' => 'Other URL 3',
                'required' => false,
                'uriHandler' => 'Zend\Uri\Http',
                'allowRelative' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'url3Label',
            'type' => 'Select',
            'options' => [
                'label' => 'Other URL 3 Label',
                'required' => false,
                'disable_inarray_validator' => true,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => $urlLabels,
            ],
            'attributes' => [
                'maxlength' => '50',
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
            
            
            
//             copyrightInfo
//             copyrightContactEmail
            
            
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
        ];
    }
}

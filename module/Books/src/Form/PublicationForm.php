<?php
namespace Books\Form;

use SionModel\Form\SionForm;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Filter\ToNull;
use Laminas\Filter\StripTags;
use SionModel\Filter\ToBit;
use Laminas\Filter\StringToLower;
use SionModel\Filter\SortArray;
use Laminas\Validator\StringLength;
use Laminas\Filter\StripNewlines;
use Laminas\Filter\StringTrim;
use Laminas\Validator\Regex;
use Laminas\Validator\NotEmpty;
use SionModel\Form\ChoiceDomain;
use SionModel\Form\CsrfSpec;

class PublicationForm extends SionForm implements InputFilterProviderInterface
{
    /**
     * sch_publications.PublicNotes, AdminNotes and EditionNotes are TEXT, so
     * this bound is not about avoiding a write error the way the varchar(255)
     * ones in BookForm are — 65535 is the column's real ceiling, and the point
     * of stating it is to refuse a payload that is absurd on its face before it
     * is stored and re-rendered on every page showing the publication.
     */
    const NOTES_MAX_LENGTH = 65535;

    public function __construct()
    {
        parent::__construct('publication');

        $this->add([
            'name' => 'title',
            'type' => 'Text',
            'options' => [
                'label' => 'Title',
            ],
            'attributes' => [
                'required' => true,
                'maxlength' => '300',
            ],
        ]);

        $this->add([
            'name' => 'authorsAll',
            'type' => 'Select',
            'options' => [
                'label' => 'Author(s)',
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
            'name' => 'bookEdition',
            'type' => 'Text',
            'options' => [
                'label' => 'Edition number',
                'required' => false,
            ],
            'attributes' => [
                'placeholder' => '1',
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'hasNoExplictEditionNumber',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Publication has no explicit edition number?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'inLanguage',
            'type' => 'Select',
            'options' => [
                'label' => 'Language',
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
            'attributes' => [
                'required' => true,
                'multiple' => true,
            ],
        ]);
        $this->add([
            'name' => 'editorsAll', //same value options as authorsAll
            'type' => 'Select',
            'options' => [
                'label' => 'Editor(s)',
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
            'name' => 'translatorsAll', //only show persons
            'type' => 'Select',
            'options' => [
                'label' => 'Translator',
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
            'name' => 'description',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Description',
                'required' => false,
                'help-block' => 'Description of the book, helpful to a non-Schoenstatt visitor to the site.',
            ],
            'attributes' => [
                'rows' => 5,
            ],
        ]);
        $this->add([
            'name' => 'isbn',
            'type' => 'Text',
            'options' => [
                'label' => 'ISBN',
                'required' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. 9780030426599',
                'maxlength' => '30',
            ],
        ]);
        $this->add([
            'name' => 'mainPublicationId',
            'type' => 'Select',
            'options' => [
                'label' => 'Main publication (use for outdated editions)',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => false,
                'value_options' => [],
                'help-block' => 'To avoid showing multiple editions of the same book in the index, please select the'
                    . ' latest edition of this work from the list.'
            ],
        ]);
        $this->add([
            'name' => 'translatedFromPublicationId',
            'type' => 'Select',
            'options' => [
                'label' => 'Translated from',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => false,
                'value_options' => [],
                'help-block' => 'The work in the original language from which this book was translated. '
                    . 'If there is more than one edition in the original language, select the newest edition.'
            ],
        ]);
        $this->add([
            'name' => 'numberOfPages',
            'type' => 'Text',
            'options' => [
                'label' => 'Number of pages',
                'required' => false,
                'help-block' => 'Normally, just the number of pages unless book has roman numeral-numbered pages '
                . 'at the beginning. In this case, use the form `xxxii+442`.'
            ],
            'attributes' => [
                'placeholder' => 'xxi+133',
            ],
        ]);
        $this->add([
            'name' => 'datePublishedText',
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
            'name' => 'publisher',
            'type' => 'Select',
            'options' => [
                'label' => 'Publisher',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [],
            ],
        ]);
        $this->add([
            'name' => 'publishingPlace',
            'type' => 'Text',
            'options' => [
                'label' => 'Publishing place',
                'required' => true,
            ],
            'attributes' => [
                'placeholder' => 'Madrid, Spain',
                'maxlength' => '200',
            ],
        ]);
        $this->add([
            'name' => 'bookFormatType',
            'type' => 'Select',
            'options' => [
                'label' => 'Book format',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
        ]);
        $this->add([
            'name' => 'copyrightYear',
            'type' => 'Number',
            'options' => [
                'label' => 'Copyright year',
                'required' => false,
                'help-block' => 'The year of the first edition, ie. the creation of the creative work.',
            ],
            'attributes' => [
                'min'         => 1800,
                'max'         => 2025,
                'step'        => 1,
                'inclusive'   => true,
            ],
        ]);
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
            'name' => 'url1',
            'type' => 'Url',
            'options' => [
                'label' => 'URL 1',
                'required' => false,
                'uriHandler' => 'Laminas\Uri\Http',
                'allowRelative' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
            ],
        ]);
        $this->add([
            'name' => 'url1Label',
            'type' => 'Select',
            'options' => [
                'label' => 'URL 1 Label',
                'required' => false,
                'empty_option' => '',
                'disable_inarray_validator' => true,
                'unselected_value' => '',
            ],
        ]);
        $this->add([
            'name' => 'url2',
            'type' => 'Url',
            'options' => [
                'label' => 'URL 2',
                'required' => false,
                'uriHandler' => 'Laminas\Uri\Http',
                'allowRelative' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
            ],
        ]);
        $this->add([
            'name' => 'url2Label',
            'type' => 'Select',
            'options' => [
                'label' => 'URL 2 Label',
                'required' => false,
                'disable_inarray_validator' => true,
                'empty_option' => '',
                'unselected_value' => '',
            ],
        ]);
        $this->add([
            'name' => 'url3',
            'type' => 'Url',
            'options' => [
                'label' => 'URL 3',
                'required' => false,
                'uriHandler' => 'Laminas\Uri\Http',
                'allowRelative' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
            ],
        ]);
        $this->add([
            'name' => 'url3Label',
            'type' => 'Select',
            'options' => [
                'label' => 'URL 3 Label',
                'required' => false,
                'disable_inarray_validator' => true,
                'empty_option' => '',
                'unselected_value' => '',
            ],
        ]);
        $this->add([
            'name' => 'volumeNumber',
            'type' => 'Text',
            'options' => [
                'label' => 'Volume number',
                'required' => false,
            ],
            'attributes' => [
                'maxlength' => '25',
            ],
        ]);
        $this->add([
            'name' => 'keywords',
            'type' => 'Select',
            'options' => [
                'label' => 'Keywords',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [],
                'help-block' => 'Thematic tags regarding the content of the book',
            ],
            'attributes' => [
                'required' => false,
                'multiple' => true,
            ],
        ]);
        $this->add([
            'name' => 'resourceId',
            'type' => 'Select',
            'options' => [
                'label' => 'Access level',
                'required' => true,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [], //set in factory
                'help-block' => 'This defines which users will see this book information listed'
            ],
            'attributes' => [
                'value' => null, //set in factory
            ],
        ]);
        $this->add([
            'name' => 'isScientificWork',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Scientific work?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'hasNoISBN',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Publication has no ISBN?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'isRevisedWithBookInHand',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Data has been revised with book in hand?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'isFormallyPublished',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Is formally published?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);

        $this->add([//http://www.codingdrama.com/bootstrap-markdown/
            'name' => 'editionNotes',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Notes relevant to this particular edition',
                'required' => false,
            ],
            'attributes' => [
                'required' => false,
                'data-provide' => 'markdown',
                'data-parser' => 'CommonMark',
                'rows' => 4,
            ],
        ]);
        $this->add([//http://www.codingdrama.com/bootstrap-markdown/
            'name' => 'publicNotes',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Notes relevant to the whole work (including other editions)',
                'required' => false,
                'help-block' => ''
            ],
            'attributes' => [
                'required' => false,
                'data-provide' => 'markdown',
                'data-parser' => 'CommonMark',
                'rows' => 4,
            ],
        ]);
        $this->add([
            'name' => 'categoryId',
            'type' => 'Select',
            'options' => [
                'label' => 'Category',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
        ]);

        $this->add([//http://www.codingdrama.com/bootstrap-markdown/
            'name' => 'adminNotes',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Admin notes',
                'required' => false,
            ],
            'attributes' => [
                'required' => false,
                'data-provide' => 'markdown',
                'data-parser' => 'CommonMark',
                'rows' => 4,
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
//         $this->getInputFilter()->get('numberOfPages')->setValidatorChain(new ValidatorChain());
        return [
            'security' => CsrfSpec::forElement($this->get('security')),
            'title' => [
                'required' => true,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 300,
                        ],
                    ],
                ],
            ],
            'authorsAll' => [ //@todo add a validator
                'required' => false,
                'filters' => [
//                     ['name' => 'SionModel\Filter\TrimStringArray'],
                ],
            ],
            'editorsAll' => [ //@todo add a validator
                'required' => false,
            ],
            'translatorsAll' => [ //@todo add a validator
                'required' => false,
                'filters' => [
//                     ['name' => 'SionModel\Filter\TrimStringArray'],
                ],
            ],
            'inLanguage' => [
                'required' => true,
                'validators' => [
                    ['name' => NotEmpty::class,
                        'options' => [
                            'type' => NotEmpty::STRING,
                        ]
                    ],
                    ...ChoiceDomain::validators($this->get('inLanguage')),
                ],
            ],
            'bookEdition' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
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
            'hasNoExplictEditionNumber' => [
                'required' => false,
                'filters' => [
                    ['name' => ToBit::class]
                ],
            ],
            'description' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 3000,
                        ],
                    ],
                ],
            ],
            'mainPublicationId' => [
                'required' => false,
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
            'translatedFromPublicationId' => [
                'required' => false,
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
            'isbn' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 30,
                        ],
                    ],
                ],
            ],
            'numberOfPages' => [
                'required' => false,
                'validators' => [
                    [
                        'name' => Regex::class,
                        'options' => [
                            'pattern' => '/^[mlxvi\+0-9]{1,14}$/',
                            'messageTemplates' => [
                                Regex::NOT_MATCH => 'Please use a combination of roman numerals and/or numbers separated by `+`.',
                            ],
                        ],
                    ]
                ],
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
            'datePublishedText' => [
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
                            'type' => \Laminas\Filter\ToNull::TYPE_INTEGER,
                        ],
                    ],
                ],
            ],
            'publisher' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 255,
                        ],
                    ],
                ],
            ],
            'publishingPlace' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 255,
                        ],
                    ],
                ],
            ],
            'bookFormatType' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
                'validators' => ChoiceDomain::validators($this->get('bookFormatType')),
            ],
            'copyrightYear' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_INTEGER,
                        ]
                    ],
                ],
            ],
            'copyrightInfo' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
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
                            'max' => 500,
                        ],
                    ],
                ],
            ],
            'url1' => [
                'required' => false,
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
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
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'url2' => [
                'required' => false,
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
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
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'url3' => [
                'required' => false,
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
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
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],

            'volumeNumber' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 25,
                        ],
                    ],
                ],
            ],
            'keywords' => [
                'required' => false,
                'filters' => [
                    ['name' => StringToLower::class],
                    ['name' => SortArray::class],
                ],
            ],
            'resourceId' => [
                'required' => true,
                'validators' => ChoiceDomain::validators($this->get('resourceId')),
            ],
            //`isAccessibleForFree` was here with no element to fill it, which is worse
            //than useless: laminas builds an input for a spec key regardless, getValues()
            //returns every input, and ToBit turns the absent value into 0 — so every save
            //of this form wrote IsAccessableForFree = 0 over whatever was there. The field
            //is deliberately not rendered (see templates/books/publication-edit.html.twig),
            //so the entry is gone rather than given an element, and the column is now
            //untouched by this form. Its two siblings below keep theirs: they *are*
            //elements, and their checkboxes always post a value.
            'isScientificWork' => [
                'required' => false,
                'filters' => [
                    ['name' => ToBit::class]
                ],
            ],
            'hasNoISBN' => [
                'required' => false,
                'filters' => [
                    ['name' => ToBit::class]
                ],
            ],
            'isRevisedWithBookInHand' => [
                'required' => false,
                'filters' => [
                    ['name' => ToBit::class]
                ],
            ],
            'isFormallyPublished' => [
                'required' => false,
                'filters' => [
                    ['name' => ToBit::class]
                ],
            ],
            'editionNotes' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => ToNull::class,
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
                            'max' => self::NOTES_MAX_LENGTH,
                        ],
                    ],
                ],
            ],
            'publicNotes' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => self::NOTES_MAX_LENGTH,
                        ],
                    ],
                ],
            ],
            'categoryId' => [
                'required' => false,
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
                'validators' => ChoiceDomain::validators($this->get('categoryId')),
            ],
            'adminNotes' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => ToNull::class,
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
                            'max' => self::NOTES_MAX_LENGTH,
                        ],
                    ],
                ],
            ],
        ];
    }
}

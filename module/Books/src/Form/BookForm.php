<?php
namespace Books\Form;

use SionModel\Form\ChoiceDomain;
use SionModel\Form\SionForm;
use Laminas\InputFilter\InputFilterProviderInterface;
use Books\Model\LibraryOptions;
use SionModel\Filter\ToBit;
use Laminas\Filter\ToInt;
use SionModel\Form\CsrfSpec;
use SionModel\Form\CheckboxDomain;
use SionModel\Form\InputTypeRules;

class BookForm extends SionForm implements InputFilterProviderInterface
{
    /**
     * Matches lib_books.public_notes, admin_notes and inactivation_reason, all
     * varchar(255). Without a bound these fields accepted any length and the
     * insert failed instead — MariaDB runs with STRICT_TRANS_TABLES, so an
     * over-length value is SQLSTATE 22001 and a 500, not a silent truncation.
     * A form error is the same protection delivered where the author can act
     * on it.
     */
    const NOTES_MAX_LENGTH = 255;

    /**
     * @var LibraryOptions $libraryOptions
     */
    protected $libraryOptions;

    public function __construct()
    {
        parent::__construct('book');

        $this->add([
            'name' => 'libraryId',
            'type' => 'Hidden',
        ]);

        $this->add([
            'name' => 'withinLibraryId',
            'type' => 'Number',
            'options' => [
                'label' => 'Barcode',
                //Named for the label a librarian prints, and the one word that makes the
                //spreadsheet import legible: this is the column the import matches a row
                //to a book by, and the field it calls `withinLibraryId` internally.
                'help-block' => 'This library\'s own number for this copy. It must be unique within the '
                    . 'library, and it is what a spreadsheet import matches a row to this book by.',
            ],
            'attributes' => [
                'required' => true,
                'min'         => 0,
//                 'max'         => 100,
                'step'        => 1,
//                 'value'       => 50,
//                 'inclusive'   => true,
            ],
        ]);
        $this->add([
            'name' => 'nextWithinLibraryId',
            'type' => 'Button',
            'options' => [
                'label' => 'Use next free barcode',
            ],
            'attributes' => [
                'id'     => "nextWithinLibraryId",
                'class' => 'btn btn-default'
            ],
        ]);
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
            'name' => 'authors',
            'type' => 'Select',
            'options' => [
                'label' => 'Author(s)',
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
            'name' => 'authorsText',
            'type' => 'Text',
            'options' => [
                //Not rendered on the form — `authors` is, as a selectize picker, and
                //`preprocessBook()` joins it into this. The label still matters: this is
                //the field the spreadsheet import writes, and the one an error names.
                'label' => 'Author',
                'help-block' => 'One name, or several separated by a vertical bar.',
            ],
            'attributes' => [
                'maxlength' => '500',
            ],
        ]);

        $this->add([
            'name' => 'callNumber',
            'type' => 'Text',
            'options' => [
                'label' => 'Call number',
                'help-block' => 'Where this copy stands on the shelf, as printed on its label.',
            ],
            'attributes' => [
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'newCallNumber',
            'type' => 'Text',
            'options' => [
                'label' => 'New call number',
                'help-block' => 'Use this field when the printed call number should be changed. It will be added to the next labels to be printed.'
            ],
            'attributes' => [
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'collectionId',
            'type' => 'Select',
            'options' => [
                'label' => 'Collection',
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
        ]);
        $this->add([
            'name' => 'bookEdition',
            'type' => 'Text',
            'options' => [
                'label' => 'Edition',
                //The field that tells two copies of one work apart when nothing else
                //does, which for Schoenstatt literature is most of the time: the same
                //text is reissued by several houses in several languages.
                'help-block' => 'Which edition or printing this copy is — what distinguishes it from '
                    . 'another copy of the same work.',
            ],
            'attributes' => [
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
                'value_options' => [],
            ],
            'attributes' => [
                'multiple' => true,
            ],
        ]);
        $this->add([
            'name' => 'publicationId',
            'type' => 'Select',
            'options' => [
                //"Literature dataset link" said what it was made of, not what it does.
                //The help block is the part that was missing entirely: linking a copy
                //makes the literature record authoritative for eight of this form's
                //fields the next time a spreadsheet import touches the book, which is
                //visible nowhere else and surprised the round-trip test.
                'label' => 'Linked literature record',
                'help-block' => 'The work in the site-wide literature catalogue that this copy is of. '
                    . 'While it is set, a spreadsheet import takes the title, author, year, publisher, '
                    . 'place, pages, language and ISBN from that record rather than from the spreadsheet.',
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => false,
                'value_options' => [],
            ],
        ]);
        $this->add([
            'name' => 'category',
            'type' => 'Select',
            'options' => [
                'label' => 'Category',
                'help-block' => 'One classification for this copy. For several subject terms use Keywords.',
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [],
            ],
            'attributes' => [
                'maxlength' => '500',
            ],
        ]);
        $this->add([
            'name' => 'isbn',
            'type' => 'Text',
            'options' => [
                'label' => 'ISBN',
            ],
            'attributes' => [
                'placeholder' => 'ex. 9780030426599',
                'maxlength' => '30',
            ],
        ]);
        $this->add([
            'name' => 'numberOfPages',
            'type' => 'Number',
            'options' => [
                'label' => 'Number of pages',
            ],
            'attributes' => [
                'min'         => 0,
                'max'         => 6000,
                'step'        => 'any',
                'inclusive'   => true,
            ],
        ]);
        $this->add([
            'name' => 'publishedYear',
            'type' => 'Number',
            'options' => [
                'label' => 'Year published',
            ],
            'attributes' => [
                'min'         => 1800,
                //Was a hardcoded 2025, which since 1 January 2026 has meant the browser
                //refusing this year's books. The input filter never enforced a range at
                //all, so this was only ever an HTML hint — and a wrong one.
                'max'         => (int) date('Y') + 1,
                'step'        => 1,
                'inclusive'   => true,
            ],
        ]);
        $this->add([
            'name' => 'publisher',
            'type' => 'Select',
            'options' => [
                'label' => 'Publisher',
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
            ],
            'attributes' => [
                'placeholder' => 'Madrid, Spain',
                'maxlength' => '200',
            ],
        ]);
        $this->add([
            'name' => 'keywords',
            'type' => 'Select',
            'options' => [
                'label' => 'Keywords',
                'help-block' => 'Subject terms shown to readers. Several are fine.',
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
        $this->add([//http://www.codingdrama.com/bootstrap-markdown/
            'name' => 'publicNotes',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Public notes',
            ],
            'attributes' => [
                'required' => false,
                'data-provide' => 'markdown',
                'data-parser' => 'CommonMark',
                'rows' => 8,
            ],
        ]);

        $this->add([//http://www.codingdrama.com/bootstrap-markdown/
            'name' => 'adminNotes',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Admin notes',
            ],
            'attributes' => [
                'required' => false,
                'data-provide' => 'markdown',
                'data-parser' => 'CommonMark',
                'rows' => 8,
            ],
        ]);

        $this->add([
            'name' => 'adminTags',
            'type' => 'Select',
            'options' => [
                'label' => 'Admin tags',
                'empty_option' => '',
                'placeholder' => 'Select tags or type new ones...',
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
            'name' => 'isActive',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Active?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '1',
            ],
        ]);
        $this->add([
            'name' => 'inactivationReason',
            'type' => 'Text',
            'options' => [
                'label' => 'Inactivation reason',
            ],
            'attributes' => [
                'placeholder' => 'ex. Book lost',
                'maxlength' => '255',
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

    /**
    * Get the libraryOptions value
    * @return LibraryOptions
    */
    public function getLibraryOptions()
    {
        return $this->libraryOptions;
    }

    /**
    *
    * @param LibraryOptions $libraryOptions
    * @return self
    */
    public function setLibraryOptions(LibraryOptions $libraryOptions)
    {
        //First, not last. getInputFilterSpecification() reads it, and the old ordering
        //assigned it on the way out — after a getInputFilter() call in this very method
        //had already built and memoised the filter.
        $this->libraryOptions = $libraryOptions;

        $this->get('collectionId')->setAttribute('disabled', ! $libraryOptions->useCollections);
        $callNumber = $this->get('callNumber');
        $newCallNumber = $this->get('newCallNumber');
        // @todo add regex validator to callNumber
        if (isset($libraryOptions->callNumberRegex)) {
            $callNumber->setAttribute('data-regex', $libraryOptions->callNumberRegex);
            $newCallNumber->setAttribute('data-regex', $libraryOptions->callNumberRegex);
        }
        if (isset($libraryOptions->callNumberPlaceholder)) {
            $callNumber->setAttribute('placeholder', $libraryOptions->callNumberPlaceholder);
            $newCallNumber->setAttribute('placeholder', $libraryOptions->callNumberPlaceholder);
        }
        if (isset($libraryOptions->callNumberHelpText)) {
            $callNumber->setOption('help-block', $libraryOptions->callNumberHelpText);
        }
        //control the empty option and the default collection
        /** @var \Laminas\Form\Element\Select $collectionId */
        $collectionId = $this->get('collectionId');
        if (false === $libraryOptions->allowCollectionlessBooks) { //disable the empty option
            $collectionId->setEmptyOption(null);
        } else {
            $collectionId->setEmptyOption('');
        }
        if (! isset($this->data) && isset($libraryOptions->mainCollectionId)) {
            $collectionId->setValue($libraryOptions->mainCollectionId);
        }

        //The attribute only — the browser's own hint. Whether the *server* requires a
        //call number is stated in getInputFilterSpecification() now; see
        //requiresCallNumber() for why the line that used to be here was a problem.
        if (is_bool($libraryOptions->requireCallNumbers)) {
            $callNumber->setAttribute('required', $libraryOptions->requireCallNumbers);
        }

        return $this;
    }

    public function setData($data)
    {
        if (isset($this->libraryOptions) && isset($this->libraryOptions->libraryId)) {
            $data['libraryId'] = $this->libraryOptions->libraryId;
        }
        $return = parent::setData($data);
        return $return;
    }

    /**
     * Whether this library insists on a call number.
     *
     * `lib_libraries.RequireCallNumbers` is per library and really varies: Bellavista and
     * both Austin libraries require one, Colegio Mayor and the rest do not.
     *
     * ## Why this is a method and not a line in setLibraryOptions()
     *
     * It was that line until 2026-09-11:
     *
     *     $this->getInputFilter()->get('callNumber')->setRequired($libraryOptions->…);
     *
     * which reached into the **built** input filter and patched it. Two things were wrong
     * with it, and the second is why it had to go.
     *
     * It called `getInputFilter()` from inside `setLibraryOptions()`, which builds and
     * memoises the filter then and there — while `$this->libraryOptions` was still unset,
     * because the assignment was the last statement in the method. Anything the
     * specification wanted to read about the library was therefore guaranteed absent at
     * the moment the specification ran.
     *
     * And a rule that exists only in the assembled filter is a rule
     * `SionModel\Form\Validation\InputFilter` cannot see. That engine reads the
     * specification and nothing else, so this was the single field in the application
     * whose validation the step 5 cutover could not have carried —
     * `test/Integration/EngineMatchesAssembledFilterTest` had it as a named exception.
     *
     * Defaults to false, which is what the specification said before the patch was
     * applied: a form built without library options requires nothing, exactly as it did.
     */
    public function requiresCallNumber(): bool
    {
        return isset($this->libraryOptions)
            && true === $this->libraryOptions->requireCallNumbers;
    }

    public function getInputFilterSpecification()
    {
        return [
            'security' => CsrfSpec::forElement($this->get('security')),
            'libraryId' => [
                'required' => true,
                'filters' => [
                    ['name' => ToInt::class],
                ],
            ],
            'withinLibraryId' => [
                'required' => true,
                'filters' => [
                    ...InputTypeRules::filters($this->get('withinLibraryId')),
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_INTEGER,
                        ]
                    ],
                ],
                'validators' => InputTypeRules::number($this->get('withinLibraryId')),
            ],
            'title' => [
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
                            'max' => 300,
                        ],
                    ],
                ],
            ],
            'authors' => [ //@todo add a validator
                'required' => false,
                'filters' => [
                    //                     ['name' => 'SionModel\Filter\TrimStringArray'],
                ],
            ],
            'authorsText' => [
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
            ],
            //Per-library, and read here rather than patched into the built filter
            //afterwards — see requiresCallNumber().
            'callNumber' => [
                'required' => $this->requiresCallNumber(),
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
                            'max' => 50,
                        ],
                    ],
                ],
            ],
            'newCallNumber' => [
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
                            'max' => 50,
                        ],
                    ],
                ],
            ],
            //Three of the 11,401 books held a language the list did not offer, and each was a
            //different failure. Two were `es;de;en` — three languages semicolon-joined into a
            //field that is `multiple` and stores its values pipe-joined, so the moderator's
            //intent was expressible and the syntax was not. The third was `ceb`: Cebuano has
            //no ISO 639-1 code, so a 184-entry two-letter list could not represent it at all.
            //LanguageSupport now carries the 455 three-letter codes as well, which is why this
            //InArray is safe to add; db7.9 corrects the two semicolon rows.
            'inLanguage' => [
                'required' => false,
                'validators' => ChoiceDomain::validators($this->get('inLanguage')),
            ],
            'collectionId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
                'validators' => ChoiceDomain::validators($this->get('collectionId')),
            ],
            'bookEdition' => [
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
                            'max' => 50,
                        ],
                    ],
                ],
            ],
            'publicationId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                    ['name' => ToInt::class],
                ],
                'validators' => ChoiceDomain::validators($this->get('publicationId')),
            ],
            'category' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
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
                            'max' => 500,
                        ],
                    ],
                ],
            ],
            'isbn' => [
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
                            'max' => 30,
                        ],
                    ],
                ],
            ],
            'numberOfPages' => [
                'required' => false,
//                 'allow_empty' => true,
//                 'continue_if_empty' => true,
                'filters' => [
                    ...InputTypeRules::filters($this->get('numberOfPages')),
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_INTEGER,
                        ],
                    ],
                ],
                'validators' => InputTypeRules::number($this->get('numberOfPages')),
            ],
            'publishedYear' => [
                'required' => false,
                'filters' => [
                    ...InputTypeRules::filters($this->get('publishedYear')),
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_INTEGER,
                        ]
                    ],
                ],
                'validators' => InputTypeRules::number($this->get('publishedYear')),
            ],
            'publisher' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
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
                            'max' => 255,
                        ],
                    ],
                ],
            ],
            'publishingPlace' => [
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
                            'max' => 255,
                        ],
                    ],
                ],
            ],

            'keywords' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringToLower'],
                    ['name' => 'SionModel\Filter\SortArray'],
                ],
            ],
            'publicNotes' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'ToNull',
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
            'adminNotes' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
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
                            'max' => self::NOTES_MAX_LENGTH,
                        ],
                    ],
                ],
            ],
            'adminTags' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringToLower'],
                    ['name' => 'SionModel\Filter\SortArray'],
                ],
            ],
            'isActive' => [
                'required' => false,
                'filters' => [
                    [
                        'name' => ToBit::class,
                        'options' => [
                            'null_defaults_to' => true,
                        ],
                    ],
                ],
                'validators' => CheckboxDomain::validators($this->get('isActive')),
            ],
            'inactivationReason' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    [
                        'name' => 'ToNull',
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
                            'max' => 255,
                        ],
                    ],
                ],
            ],
        ];
    }

//     public function getData($flag = FormInterface::VALUES_NORMALIZED)
//     {
//         $values = parent::getData($flag);

//         //check if we need to set any
//     }
}

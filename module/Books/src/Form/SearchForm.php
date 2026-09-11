<?php
namespace Books\Form;

use SionModel\Form\Form;
use SionModel\Form\InputFilterProviderInterface;
use SionModel\Form\ChoiceDomain;

class SearchForm extends Form implements InputFilterProviderInterface
{
    /**
     * Every collection id in the database, as a floor under `collectionId`.
     *
     * The element's own options are the *right* domain and they are narrower than this: the
     * consuming action replaces them with the collections of the library being viewed, before
     * validating. This list only answers the case where that did not happen, so that a
     * `collectionId` reaching the search query is at least a collection.
     * @see \Books\Service\SearchFormFactory
     * @var int[]
     */
    protected $allCollectionIds;

    public function __construct($name = null, array $allCollectionIds = [])
    {
        // we want to ignore the name passed
        parent::__construct('search');
        $this->allCollectionIds = $allCollectionIds;
        $this->setAttribute('method', 'GET');

        $this->add([
            'name' => 'search',
            'type' => 'Text',
            'options' => [
                'label' => '',
            ],
            'attributes' => [
                'required' => false,
                'class' => 'input-lg search-query',
                'placeholder' => 'Search',
                'size' => 50,
            ],
        ]);
        $this->add([
            'name' => 'inLanguage',
            'type' => 'Text',
            'options' => [
                'label' => 'Language',
            ],
            'attributes' => [
                'required' => false,
                'size' => 2,
            ],
        ]);
        $this->add([
            'name' => 'category',
            'type' => 'Text',
            'options' => [
                'label' => '',
            ],
            'attributes' => [
                'required' => false,
                'size' => 50,
            ],
        ]);
        $this->add([
            'name' => 'libraryId',
            'type' => 'Hidden',
            'options' => [
                'required' => true,
            ],
        ]);
        $this->add([
            'name' => 'collectionId',
            'type' => 'Select',
            'options' => [
                'label' => 'Collection',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
        ]);

        $this->add([
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Search',
                'id' => 'submit',
                'class' => 'btn-primary'
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'search' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull'],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 3,
                            'encoding' => 'UTF-8',
                        ],
                    ],
                ],
            ],
            'inLanguage' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull'],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 2,
                            'max' => 2,
                            'encoding' => 'UTF-8',
                        ],
                    ],
                ],
            ],
            //Was the one field on this form with no filter, no validator and no length
            //bound — a raw query-string value going to LibraryTable::searchBooks(). The
            //shape is `search`'s and `inLanguage`'s above; the bound is lib_books.category
            //varchar(500). It also means a `category[]=a&category[]=b` query is now
            //rejected rather than reaching searchBooks()' multi-value branch: nothing
            //renders such a link, the element is a single Text, and `search[]=` has always
            //failed the same way.
            'category' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull'],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => 500,
                            'encoding' => 'UTF-8',
                        ],
                    ],
                ],
            ],
            'libraryId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_INTEGER,
                        ]
                    ],
                ],
            ],
            'collectionId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \SionModel\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
                'validators' => ChoiceDomain::validators($this->get('collectionId'), $this->allCollectionIds),
            ],
        ];
    }
}

<?php
namespace Books\Form;

use SionModel\Form\Form;
use SionModel\Form\InputFilterProviderInterface;
use SionModel\Form\ChoiceDomain;
use SionModel\I18n\LanguageSupport;

class TextSearchForm extends Form implements InputFilterProviderInterface
{
    public function __construct($name = null)
    {
        // we want to ignore the name passed
        parent::__construct('search');
        $this->setAttribute('method', 'GET');

        $this->add([
            'name' => 'search',
            'type' => 'Text',
            'options' => [
                'label' => '',
            ],
            'attributes' => [
                'required' => true,
                'class' => 'input-lg search-query',
                'placeholder' => 'Search',
                'size' => 50,
            ],
        ]);
        //Nothing had ever filled this select in — not this constructor, not a factory (it has
        //none: both controllers do `new TextSearchForm()`), not the view. It rendered as a
        //language chooser offering no languages, and because a spec entry discards an
        //element's own InArray, `?inLanguage=` accepted any string at all on the way to the
        //search query. LanguageSupport is a static table and needs nothing injected, so the
        //form can answer this itself rather than acquiring a factory to be told.
        $languageSupport = new LanguageSupport();
        $this->add([
            'name' => 'inLanguage',
            'type' => 'Select',
            'options' => [
                'label' => 'Language',
                'empty_option' => '',
                'value_options' => $languageSupport->getLanguageNames(
                    \Locale::getPrimaryLanguage(\Locale::getDefault())
                ),
            ],
            'attributes' => [
                'required' => false,
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
                'required' => true,
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
                    ['name' => 'ToNull'],
                ],
                'validators' => ChoiceDomain::validators($this->get('inLanguage')),
            ],
        ];
    }
}

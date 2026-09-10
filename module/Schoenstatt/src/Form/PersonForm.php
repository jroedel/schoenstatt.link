<?php
namespace Schoenstatt\Form;

use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Filter\ToNull;
use SionModel\Form\SionForm;
use SionModel\Form\ChoiceDomain;
use SionModel\Form\CsrfSpec;
use SionModel\Form\CheckboxDomain;
use SionModel\Form\InputTypeRules;

class PersonForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('edit_person');
    }
    public function init()
    {
        $this->add([
            'name' => 'email',
            'type' => 'Email',
            'options' => [
                'label' => 'Main Email',
            ],
            'attributes' => [
                'required' => false,
                'maxlength' => '70',
            ],
        ]);
        $this->add([
            'name' => 'email2',
            'type' => 'Email',
            'options' => [
                'label' => 'Alternative Email',
            ],
            'attributes' => [
                'required' => false,
                'maxlength' => '70',
            ],
        ]);
        $this->add([
            'name' => 'cellPhone',
            'type' => 'Phone',
            'options' => [
                'label' => 'Cell phone (main number)',
                'help-block' => 'Please begin with a \'+\' followed by the country code.',
            ],
            'attributes' => [
                'placeholder' => 'ex. +49 151 55555555',
                'id' => 'cellPhone',
                'maxlength' => '30',
            ],
        ]);
        $this->add([
            'name' => 'cellPhoneHasWhatsApp',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Cell phone has WhatsApp?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
        ]);
        $this->add([
            'name' => 'phone1',
            'type' => 'Phone',
            'options' => [
                'label' => 'Phone 1',
                'help-block' => 'Please begin with a \'+\' followed by the country code.',
            ],
            'attributes' => [
                'placeholder' => 'ex. +49 151 55555555',
                'maxlength' => '30',
            ],
        ]);
        $this->add([
            'name' => 'phone1Label',
            'type' => 'Select',
            'options' => [
                'label' => 'Phone 1 label (optional)',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [
                    'Alternate cell phone' => 'Alternate cell phone',
                    'Only WhatsApp' => 'Only WhatsApp',
                    'Movement house' => 'Movement house',
                    'Office' => 'Office',
                    'Parish' => 'Parish',
                    'Personal house phone' => 'Personal house phone',
                ],
            ],
            'attributes' => [
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'phone2',
            'type' => 'Phone',
            'options' => [
                'label' => 'Phone 2',
                'help-block' => 'Please begin with a \'+\' followed by the country code.',
            ],
            'attributes' => [
                'placeholder' => 'ex. +49 151 55555555',
                'maxlength' => '30',
            ],
        ]);
        $this->add([
            'name' => 'phone2Label',
            'type' => 'Select',
            'options' => [
                'label' => 'Phone 2 label (optional)',
                'required' => false,
                'empty_option' => '',
                'disable_inarray_validator' => true,
                'unselected_value' => '',
                'value_options' => [
                    'Alternate cell phone' => 'Alternate cell phone',
                    'Only WhatsApp' => 'Only WhatsApp',
                    'Movement house' => 'Movement house',
                    'Office' => 'Office',
                    'Parish' => 'Parish',
                    'Personal house phone' => 'Personal house phone',
                ],
            ],
            'attributes' => [
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'phone3',
            'type' => 'Phone',
            'options' => [
                'label' => 'Phone 3',
                'help-block' => 'Please begin with a \'+\' followed by the country code.',
            ],
            'attributes' => [
                'placeholder' => 'ex. +49 151 55555555',
                'maxlength' => '30',
            ],
        ]);
        $this->add([
            'name' => 'phone3Label',
            'type' => 'Select',
            'options' => [
                'label' => 'Phone 3 label (optional)',
                'required' => false,
                'empty_option' => '',
                'disable_inarray_validator' => true,
                'unselected_value' => '',
                'value_options' => [
                    'Alternate cell phone' => 'Alternate cell phone',
                    'Only WhatsApp' => 'Only WhatsApp',
                    'Movement house' => 'Movement house',
                    'Office' => 'Office',
                    'Parish' => 'Parish',
                    'Personal house phone' => 'Personal house phone',
                ],
            ],
            'attributes' => [
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'twitterUser',
            'type' => 'Text',
            'options' => [
                'label' => 'Twitter user',
                'required' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. fr_johnsmith',
                'maxlength' => '15',
            ],
        ]);
        $this->add([
            'name' => 'instagramUser',
            'type' => 'Text',
            'options' => [
                'label' => 'Instagram user',
                'required' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. fr_johnsmith',
                'maxlength' => '30',
            ],
        ]);
        //Skype and Slack were validated but never offered. Their spec entries below are
        //as complete as Twitter's and Instagram's — dedicated validators, filters, a
        //`contactInfo` many-to-one stamp — and `sch_persons.SkypeUser` holds 63 live
        //values that the person page displays. Only the elements were missing, so the
        //fields were editable by a hand-made POST and by nothing else.
        $this->add([
            'name' => 'skypeUser',
            'type' => 'Text',
            'options' => [
                'label' => 'Skype user',
                'required' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. fr_johnsmith',
                //SionModel\Validator\Skype caps the name at 32; the column holds 50.
                //The browser bound is the one a value can actually pass.
                'maxlength' => '32',
            ],
        ]);
        $this->add([
            'name' => 'slackUser',
            'type' => 'Text',
            'options' => [
                'label' => 'Slack user',
                'required' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. fr.johnsmith',
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'facebookUrl',
            'type' => 'Url',
            'options' => [
                'label' => 'Facebook URL',
                'required' => false,
                'uriHandler' => 'Laminas\Uri\Http',
                'allowRelative' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
                'maxlength' => '255',
            ],
        ]);
        $this->add([
            'name' => 'url1',
            'type' => 'Url',
            'options' => [
                'label' => 'Other URL 1',
                'required' => false,
                'uriHandler' => 'Laminas\Uri\Http',
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
                'value_options' => [
                    'Blog' => 'Blog',
                    'G+' => 'G+',
                    'Personal website' => 'Personal website',
                ],
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
                'uriHandler' => 'Laminas\Uri\Http',
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
                'value_options' => [
                    'Blog' => 'Blog',
                    'G+' => 'G+',
                    'Personal website' => 'Personal website',
                ],
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
                'uriHandler' => 'Laminas\Uri\Http',
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
                'value_options' => [
                    'Blog' => 'Blog',
                    'G+' => 'G+',
                    'Personal website' => 'Personal website',
                ],
            ],
            'attributes' => [
                'maxlength' => '50',
            ],
        ]);

        $this->add([
            'name' => 'postStreet1',
            'type' => 'Text',
            'options' => [
                'label' => 'Street Line 1',
            ],
        ]);
        $this->add([
            'name' => 'postStreet2',
            'type' => 'Text',
            'options' => [
                'label' => 'Street Line 2',
            ],
        ]);
        $this->add([
            'name' => 'postCityState',
            'type' => 'Text',
            'options' => [
                'label' => 'City/State',
            ],
        ]);
        $this->add([
            'name' => 'postZip',
            'type' => 'Text',
            'options' => [
                'label' => 'Zip/PLZ',
            ],
        ]);
        $this->add([
            'name' => 'postCountry',
            'type' => 'Select',
            'options' => [
                'label' => 'Country',
                'empty_option' => '',
                'unselected_value' => '',
            ],
            'attributes' => [
                'required' => false
            ],
        ]);

        $this->add([
            'name' => 'contactNotes',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Contact detail notes',
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'firstName',
            'type' => 'Text',
            'options' => [
                'label' => 'First name',
                'required' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. John Andrew',
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'lastName',
            'type' => 'Text',
            'options' => [
                'label' => 'Last name',
                'required' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. Smith Johnson',
                'maxlength' => '50',
            ],
        ]);

        $this->add([
            'name' => 'lifeCommunity',
            'type' => 'Select',
            'options' => [
                'label' => 'Life community',
                'empty_option' => '',
                'unselected_value' => '',
            ],
            'attributes' => [
                'required' => false
            ],
        ]);
        $this->add([
            'name' => 'spousePersonId',
            'type' => 'Select',
            'options' => [
                'label' => 'Spouse',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => false,
                'value_options' => [],
            ],
        ]);
        $this->add([
            'name' => 'personTags',
            'type' => 'Select',
            'options' => [
                'label' => 'Person tags',
                'required' => false,
                'empty_option' => '',
                'unselected_value' => '',
                'disable_inarray_validator' => true,
                'value_options' => [],
            ],
            'attributes' => [
                'multiple' => true,
            ],
        ]);
        $this->add([
            'name' => 'manualTitle',
            'type' => 'Text',
            'options' => [
                'label' => 'Manual title',
                'required' => true,
            ],
            'attributes' => [
                'placeholder' => 'ex. Fr. Prof.',
                'maxlength' => '50',
            ],
        ]);
        $this->add([
            'name' => 'automaticTitle',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Automatically generate title',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value' => '1',
                'data-toggle'     => 'collapse',
                'data-target'     => '#titleGroup',
                'aria-expanded'   => 'false',
                'aria-controls'   => 'titleGroup'
            ],
        ]);
        $this->add([
            'name' => 'country',
            'type' => 'Select',
            'options' => [
                'label' => 'Home country',
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => [],
            ],
            'attributes' => [
                'required' => false
            ],
        ]);

        $this->add([//http://www.codingdrama.com/bootstrap-markdown/
            'name' => 'publicNotes',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Public notes',
                'required' => false,
            ],
            'attributes' => [
                'required' => false,
                'data-provide' => 'markdown',
                'data-parser' => 'CommonMark',
                'rows' => 8,
            ],
            'filters' => [
                ['name' => 'StripTags'],
            ],
        ]);

        $this->add([
            'name' => 'birthDate',
            'type' => 'Date',
            'options' => [
                'label' => 'Birth date',
                'format' => 'Y-m-d',
            ],
            'attributes' => [
                'min' => '1900-01-01',
                'step' => 'any',
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'birthDatePrecision',
            'type' => 'Select',
            'options' => [
                'label' => 'How precisely the birth date is known',
                'value_options' => \SionModel\Form\DatePrecision::valueOptions(),
                //filterSpec() owns the domain check, so the Select's automatic
                //InArray is off to leave exactly one — see DatePrecision.
                'disable_inarray_validator' => true,
            ],
            'attributes' => [
                'required' => false,
                'value' => \SionModel\Form\DatePrecision::DEFAULT_PRECISION,
            ],
        ]);
        $this->add([
            'name' => 'priestDate',
            'type' => 'Date',
            'options' => [
                'label' => 'Priest ordination date',
                'format' => 'Y-m-d',
            ],
            'attributes' => [
                'min' => '1900-01-01',
                'step' => 'any',
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'priestDatePrecision',
            'type' => 'Select',
            'options' => [
                'label' => 'How precisely the ordination date is known',
                'value_options' => \SionModel\Form\DatePrecision::valueOptions(),
                //filterSpec() owns the domain check, so the Select's automatic
                //InArray is off to leave exactly one — see DatePrecision.
                'disable_inarray_validator' => true,
            ],
            'attributes' => [
                'required' => false,
                'value' => \SionModel\Form\DatePrecision::DEFAULT_PRECISION,
            ],
        ]);
        $this->add([
            'name' => 'bishopDate',
            'type' => 'Date',
            'options' => [
                'label' => 'Bishop ordination date',
                'format' => 'Y-m-d',
            ],
            'attributes' => [
                'min' => '1900-01-01',
                'step' => 'any',
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'bishopDatePrecision',
            'type' => 'Select',
            'options' => [
                'label' => 'How precisely the episcopal ordination date is known',
                'value_options' => \SionModel\Form\DatePrecision::valueOptions(),
                //filterSpec() owns the domain check, so the Select's automatic
                //InArray is off to leave exactly one — see DatePrecision.
                'disable_inarray_validator' => true,
            ],
            'attributes' => [
                'required' => false,
                'value' => \SionModel\Form\DatePrecision::DEFAULT_PRECISION,
            ],
        ]);
        $this->add([
            'name' => 'isAuthor',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Is author?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'isBorrower',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Is library user?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'nameDay',
            'type' => 'DateSelect',
            'options' => [
                'label' => 'Name day',
                'create_empty_option' => true,
                'render_delimiters' => false,
                'min_year' => 1900,
                'day_attributes'      => [
                    'class' => 'form-control',
                ],
                'month_attributes'    => [
                    'class' => 'form-control',
                ],
                'year_attributes'     => [
                    'value' => 1900,
                    'hidden' => true,
                ],
            ],
        ]);

        $this->add([
            'name' => 'deathDate',
            'type' => 'Date',
            'options' => [
                'label' => 'Death date',
                'format' => 'Y-m-d',
            ],
            'attributes' => [
                'min' => '1900-01-01',
                'step' => 'any',
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'deathDatePrecision',
            'type' => 'Select',
            'options' => [
                'label' => 'How precisely the death date is known',
                'value_options' => \SionModel\Form\DatePrecision::valueOptions(),
                //filterSpec() owns the domain check, so the Select's automatic
                //InArray is off to leave exactly one — see DatePrecision.
                'disable_inarray_validator' => true,
            ],
            'attributes' => [
                'required' => false,
                'value' => \SionModel\Form\DatePrecision::DEFAULT_PRECISION,
            ],
        ]);

/**
 * Private info
 * [
        'adminNotes', 'adminTags'
    ],
 */

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
/**
 * Common elements
 */
        $this->add([
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Submit',
                'id' => 'submit',
                'class' => 'btn-primary'
            ],
        ]);
        $this->add([
            'name' => 'delete',
            'type' => 'Button',
            'attributes' => [
                'value' => 'Delete',
                'class' => 'btn-danger',
                'data-toggle' => 'modal',
                'data-target' => '.bs-example-modal-sm'
            ],
        ]);
    }

    public function setInputFilterSpecification($spec)
    {
        $this->filterSpec = $spec;
    }

    public function getInputFilterSpecification()
    {
        if ($this->filterSpec) {
            return $this->filterSpec;
        }
        $this->filterSpec = [
            'security' => CsrfSpec::forElement($this->get('security')),
            'email' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    ...InputTypeRules::email($this->get('email')),
                    ['name' => 'EmailAddress'],
                ],
            ],
            'email2' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    ...InputTypeRules::email($this->get('email2')),
                    ['name' => 'EmailAddress'],
                ],
            ],
            'cellPhoneHasWhatsApp' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
                'validators' => CheckboxDomain::validators($this->get('cellPhoneHasWhatsApp')),
            ],
            'cellPhone' => $this->phoneInputFilterSpec,
            'phone1' => $this->phoneInputFilterSpec,
            'phone1Label' => $this->phoneLabelInputFilterSpec,
            'phone2' => $this->phoneInputFilterSpec,
            'phone2Label' => $this->phoneLabelInputFilterSpec,
            'phone3' => $this->phoneInputFilterSpec,
            'phone3Label' => $this->phoneLabelInputFilterSpec,
            'url1' => [
                'required' => false,
                'filters' => [
                    ...InputTypeRules::filters($this->get('url1')),
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => InputTypeRules::url($this->get('url1')),
            ],
            'url1Label' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'url2' => [
                'required' => false,
                'filters' => [
                    ...InputTypeRules::filters($this->get('url2')),
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => InputTypeRules::url($this->get('url2')),
            ],
            'url2Label' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'url3' => [
                'required' => false,
                'filters' => [
                    ...InputTypeRules::filters($this->get('url3')),
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => InputTypeRules::url($this->get('url3')),
            ],
            'url3Label' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'facebookUrl' => [
                'required' => false,
                'filters' => [
                    ...InputTypeRules::filters($this->get('facebookUrl')),
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => InputTypeRules::url($this->get('facebookUrl')),
            ],
            'skypeUser' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    ['name' => 'SionModel\Validator\Skype'],
                    //sch_persons.SkypeUser is varchar(50). The regex above already caps
                    //the name at 32, so this bound is what the *column* asks for rather
                    //than a second opinion about Skype.
                    ['name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 50,
                        ],
                    ],
                ],
            ],
            'twitterUser' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    ['name' => 'SionModel\Validator\Twitter'],
                ],
            ],
            'instagramUser' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    ['name' => 'SionModel\Validator\Instagram'],
                ],
            ],
            'slackUser' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringToLower'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    ['name' => 'SionModel\Validator\Slack'],
                    //Unlike Skype's, the Slack pattern has no length ceiling of its own,
                    //so without this the field is bounded by nothing but
                    //sch_persons.SlackUser varchar(50) — which under STRICT_TRANS_TABLES
                    //means an over-long name is a 500, not a validation message.
                    ['name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 50,
                        ],
                    ],
                ],
            ],
            'postStreet1' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull'],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 70,
                        ],
                    ],
                ],
            ],
            'postStreet2' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull'],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 70,
                        ],
                    ],
                ],
            ],
            'postCityState' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull'],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 40,
                        ],
                    ],
                ],
            ],
            'postZip' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull'],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 15,
                        ],
                    ],
                ],
            ],
            'postCountry' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull'],
                ],
                'validators' => ChoiceDomain::validators($this->get('postCountry')),
            ],
            'contactNotes' => [
                'required' => false,
                //HtmlEntities removed. It was the only use of that filter in the
                //application, and it bought nothing: this field is rendered only
                //through formRow(), which escapes, and the one raw markdown render
                //of it is commented out on the associations page as deprecated. So
                //the value was being encoded at rest for an output path that does
                //not exist.
                //
                //It was also the last input in any form able to take a request
                //down. Laminas\Filter\HtmlEntities runs iconv() first, which
                //returns false on an invalid UTF-8 sequence, and then calls
                //htmlentities(false) — a TypeError inside isValid(), i.e. a 500
                //with the whole submission lost. That is a bug in the filter, not
                //in its configuration here, and not using it is a better answer
                //than working around it.
                //
                //SionForm::setData() still decodes entities on the way in, so rows
                //already storing encoded text decode when edited and save back
                //plain. The representation converges rather than needing a
                //migration.
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            //sch_persons.ContactNotes is TEXT. 65535 is the
                            //column's real ceiling, not a guess at what a note
                            //should be — a bound tighter than the column would
                            //start rejecting notes nobody has written yet.
                            'max' => 65535,
                        ],
                    ],
                ],
            ],

/**
 * Personal info input filter
 */

            'firstName' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
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
            'lastName' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
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
            //`ToInt` then `ToNull`, the same pair AssociationInputFilterSpec uses for
            //`parentId` and the pair every other nullable-id select in the application
            //already carries. Without them this input was `['required' => false]` and
            //nothing else, so an unchosen spouse posted `''` straight through to
            //`sch_persons.SpousePersonId`, an integer column, and MariaDB refused it:
            //
            //    22007 - 1366 - Incorrect integer value: '' for column `SpousePersonId`
            //
            //On **create** that was every person without a spouse, i.e. nearly every
            //person: createEntity() writes every column it is handed. On **edit** it was
            //narrower and just as real — updateEntity() skips a column when
            //`$value == $data[$field]`, and `494 == ''` is false in PHP 8, so *clearing* a
            //spouse wrote `''` too. 82 of 325 persons have one.
            //
            //An audit of every create form against the database's own column types found
            //this was the only `Select` in the application in that state; the other 73
            //candidates are checkboxes, which post `0`/`1` and never `''`. See
            //test/Integration/EmptyStringToTypedColumnTest, which is that audit.
            'spousePersonId' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'ToInt'],
                    [
                        'name'    => ToNull::class,
                        'options' => ['type' => ToNull::TYPE_INTEGER],
                    ],
                ],
                'validators' => ChoiceDomain::validators($this->get('spousePersonId')),
            ],
            'isAuthor' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
                'validators' => CheckboxDomain::validators($this->get('isAuthor')),
            ],
            'isBorrower' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
                'validators' => CheckboxDomain::validators($this->get('isBorrower')),
            ],
            'personTags' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StripNewlines'],
                    ['name' => 'StringTrim'],
                    ['name' => 'StringToLower'],
                    ['name' => 'SionModel\Filter\SortArray'],
                ],
            ],
            'manualTitle' => [
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
            'automaticTitle' => [
                'required' => false,
                'validators' => CheckboxDomain::validators($this->get('automaticTitle')),
            ],
            'country' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringToUpper'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 2,
                        ],
                    ],
                    ...ChoiceDomain::validators($this->get('country')),
                ],
            ],
            'publicNotes' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'birthDate' => [
                'required' => false,
                'filters' => [
                    ...InputTypeRules::filters($this->get('birthDate')),
                    ['name' => 'SionModel\Filter\ToDateTime'],
                ],
                'validators' => [
                    ...InputTypeRules::date($this->get('birthDate')),
                    ['name' => 'SionModel\Validator\ParseableDate'],
                    [
                        'name' => 'SionModel\Validator\DateWithinRange',
                        'options' => ['min' => '1850-01-01', 'max' => 'today'],
                    ],
                ],
            ],
            'birthDatePrecision' => \SionModel\Form\DatePrecision::filterSpec(),
            'priestDate' => [
                'required' => false,
                'filters' => [
                    ...InputTypeRules::filters($this->get('priestDate')),
                    ['name' => 'SionModel\Filter\ToDateTime'],
                ],
                'validators' => [
                    ...InputTypeRules::date($this->get('priestDate')),
                    ['name' => 'SionModel\Validator\ParseableDate'],
                    [
                        'name' => 'SionModel\Validator\DateWithinRange',
                        'options' => ['min' => '1850-01-01', 'max' => '+2 years'],
                    ],
                    [
                        'name' => 'SionModel\Validator\DateNotBefore',
                        'options' => ['field' => 'birthDate', 'relatedLabel' => 'birth date'],
                    ],
                ],
            ],
            'priestDatePrecision' => \SionModel\Form\DatePrecision::filterSpec(),
            'bishopDate' => [
                'required' => false,
                'filters' => [
                    ...InputTypeRules::filters($this->get('bishopDate')),
                    ['name' => 'SionModel\Filter\ToDateTime'],
                ],
                'validators' => [
                    ...InputTypeRules::date($this->get('bishopDate')),
                    ['name' => 'SionModel\Validator\ParseableDate'],
                    [
                        'name' => 'SionModel\Validator\DateWithinRange',
                        'options' => ['min' => '1850-01-01', 'max' => '+2 years'],
                    ],
                    [
                        'name' => 'SionModel\Validator\DateNotBefore',
                        'options' => ['field' => 'priestDate', 'relatedLabel' => 'date of priestly ordination'],
                    ],
                ],
            ],
            'bishopDatePrecision' => \SionModel\Form\DatePrecision::filterSpec(),
            'nameDay' => [
                'required' => false,
                'filters' => [
                    ...InputTypeRules::filters($this->get('nameDay')),
                    ['name' => 'SionModel\Filter\DateSelectNoYear'],
                ],
                'validators' => InputTypeRules::date($this->get('nameDay')),
            ],
            'deathDate' => [
                'required' => false,
                'filters' => [
                    ...InputTypeRules::filters($this->get('deathDate')),
                    ['name' => 'SionModel\Filter\ToDateTime'],
                ],
                'validators' => [
                    ...InputTypeRules::date($this->get('deathDate')),
                    ['name' => 'SionModel\Validator\ParseableDate'],
                    [
                        'name' => 'SionModel\Validator\DateWithinRange',
                        'options' => ['min' => '1850-01-01', 'max' => 'today'],
                    ],
                    [
                        'name' => 'SionModel\Validator\DateNotBefore',
                        'options' => ['field' => 'birthDate', 'relatedLabel' => 'birth date'],
                    ],
                ],
            ],
            'deathDatePrecision' => \SionModel\Form\DatePrecision::filterSpec(),

/**
 * Private info input filter
 */
            'lifeCommunity' => [
                'required' => false,
                'validators' => ChoiceDomain::validators($this->get('lifeCommunity')),
            ],
            'adminNotes' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
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
        ];
        return $this->filterSpec;
    }
}

<?php
namespace Schoenstatt\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;
use SionModel\Validator\Skype;
use Zend\Uri\Uri;
use Zend\Filter\ToNull;
use SionModel\Form\SionForm;

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
        $this->add([
            'name' => 'facebookUrl',
            'type' => 'Url',
            'options' => [
                'label' => 'Facebook URL',
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
                    ['name' => 'EmailAddress'],
                ],
            ],
            'cellPhoneHasWhatsApp' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
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
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
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
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
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
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
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
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
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
            ],
            'contactNotes' => [
                'required' => false,
                'filters' => [
                    ['name' => 'HtmlEntities'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
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
            'spousePersonId' => [
                'required' => false,
            ],
            'isAuthor' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
            ],
            'isBorrower' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToBit']
                ],
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
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
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
                    ['name' => 'SionModel\Filter\ToDateTime'],
                ],
            ],
            'priestDate' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToDateTime'],
                ],
            ],
            'bishopDate' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToDateTime'],
                ],
            ],
            'nameDay' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\DateSelectNoYear'],
                ],
            ],
            'deathDate' => [
                'required' => false,
                'filters' => [
                    ['name' => 'SionModel\Filter\ToDateTime'],
                ],
            ],

/**
 * Private info input filter
 */
            'lifeCommunity' => [
                'required' => false,
            ],
            'street1' => [
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
            'street2' => [
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
            'cityState' => [
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
            'zip' => [
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

<?php
namespace Schoenstatt\Form;

use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Filter\ToNull;
use SionModel\Form\SionForm;
use Schoenstatt\Validator\OpeningHoursSpecificationJson;
use Schoenstatt\Validator\TimeZone;
use Zend\Validator\StringLength;
use Zend\Filter\StripTags;
use Zend\Filter\StripNewlines;
use Zend\Filter\StringTrim;
use SionModel\Filter\ToGeoPoint;
use Zend\Validator\GpsPoint;
use SionModel\Validator\Twitter;
use Zend\Validator\EmailAddress;
use SionModel\Filter\ToDateTime;
use SionModel\Filter\ToBit;
use SionModel\Validator\Instagram;

class AssociationForm extends SionForm implements InputFilterProviderInterface
{
    public const PHONE_LABEL_VALUE_OPTIONS = [
        'Alternate cell phone' => 'Alternate cell phone',
        'Only WhatsApp' => 'Only WhatsApp',
        'Movement house' => 'Movement house',
        'Office' => 'Office',
        'Parish' => 'Parish',
        'Personal house phone' => 'Personal house phone',
        'Fax' => 'Fax',
    ];
    
    public function __construct()
    {
        parent::__construct('edit_association');
    }

    public function init()
    {
        $urlLabels = [
            'Blog' => 'Blog',
            'G+' => 'G+',
            'Information' => 'Information',
            'Map' => 'Map',
            'Media' => 'Media',
            'Personal website' => 'Personal website',
        ];

        $this->add([
            'name' => 'name',
            'type' => 'Text',
            'options' => [
                'label' => 'Name for the public',
                'help-block' => 'This is the name that would be published in Google Maps (if applicable). '
                    .'Several association types include `name formats` that insert this field within a commonly '
                    .'used format, for example `Schoenstatt movement of [name]`. This simplifies mass translation, '
                    .'but can be overridden below.',
            ],
            'attributes' => [
                'required' => true,
                'placeholder' => 'ex. Schoenstatt Fathers',
                'maxlength' => '200',
            ],
        ]);
        $this->add([
            'name' => 'isNameTranslateable',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Should the name be translated?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value' => '0',
            ],
        ]);
        $this->add([
            'name' => 'overrideNameFormat',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Override name format?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);
        $this->add([
            'name' => 'internalName',
            'type' => 'Text',
            'options' => [
                'label' => 'Name within Schoenstatt',
                'help-block' => 'This is the name that would be that will be shown to most users of the page '
                    .'(supposing most users are Schoenstatters). This field has no name formats as the '
                    .'public name field does. If the internal name would be the same as the public name, '
                    .'please leave blank.',
            ],
            'attributes' => [
                'required' => false,
                'placeholder' => 'ex. Exile Shrine',
                'maxlength' => '200',
            ],
        ]);
        $this->add([
            'name' => 'isInternalNameTranslateable',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Should the internal name be translated?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value' => '0',
            ],
        ]);
        $this->add([
            'name' => 'parentId',
            'type' => 'Select',
            'options' => [
                'label' => 'Parent organization (for sorting purposes)',
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
            'name' => 'kind',
            'type' => 'Select',
            'options' => [
                'label' => 'Association type',
                'value_options' => [
                ],
            ],
            'attributes' => [
                'required' => true,
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
        $timeZoneOptions = array_merge(["" => ""], TimeZone::getTimeZoneValueOptions());
        $this->add([
            'name' => 'timeZoneId',
            'type' => 'Select',
            'options' => [
                'label' => 'Time zone',
                'empty_option' => '',
                'unselected_value' => '',
                'value_options' => $timeZoneOptions,
            ],
            'attributes' => [
                'required' => false,
            ],
        ]);
        $this->add([
            'name' => 'openingHoursHuman',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Opening hours (free text)',
                'help-block' => 'Be as descriptive as possible, including closing days throughout the year! 
There\'s nothing worse for a pilgrim than finding a closed door.',
            ],
            'attributes' => [
                'required' => false,
                'placeholder' => 'Sun-Sat 9-11am, 12-8pm, except first Tuesdays of the month',
            ],
        ]);
        $this->add([
            'name' => 'openingHoursSpecificationJson',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Opening hours specification JSON (advanced users)',
                'help-block' => 'Please see OpeningHours::create([...]);
at <a href="https://github.com/spatie/opening-hours" target="_blank">spatie/opening-hours</a></br>
',
            ],
            'attributes' => [
                'required' => false,
                'rows' => 8,
                'placeholder' => '{
  "friday":["09:00-12:00","13:00-18:00"],
  "saturday":["09:00-12:00","13:00-18:00"],
  "sunday":["09:00-12:00","13:00-18:00"],
  "exceptions": {
    "2016-11-11": ["09:00-12:00"],
    "2016-12-25": [],
    "01-01": [],
}}',
            ],
        ]);
        $this->add([
            'name' => 'eventsHuman',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Mass, adoration and reconciliation schedules (free text)',
                'help-block' => 'Please add all details available, including exceptions to the general rule. 
(Summer/winter schedules, months without mass, etc.)',
            ],
            'attributes' => [
                'required' => false,
                'placeholder' => 'Covenant mass every 3rd Sunday, daily mass every Wednesday at 7am, '
.'except in June and July. Confessions will be offered 30 minutes before every mass. '
.'Youth adoration every 1st and 3rd Friday while school is in session. Please verify on the Facebook page.',
            ],
        ]);
        $this->add([
            'name' => 'eventsJson',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Mass, adoration and reconciliation JSON (advanced users)',
                'help-block' => 'Please see eventSchedule specification
at <a href="https://schema.org/eventSchedule" target="_blank">schema.org</a>.</br>
',
            ],
            'attributes' => [
                'required' => false,
                'rows' => 8,
                'placeholder' => '{
  "friday":["09:00-12:00","13:00-18:00"],
  "saturday":["09:00-12:00","13:00-18:00"],
  "sunday":["09:00-12:00","13:00-18:00"],
  "exceptions": {
    "2016-11-11": ["09:00-12:00"],
    "2016-12-25": [],
    "01-01": [],
}}',
            ],
        ]);
        $this->add([
            'name' => 'foundationDate',
            'type' => 'Date',
            'options' => [
                'label' => 'Foundation date',
                'format' => 'Y-m-d',
            ],
            'attributes' => [
                'min' => '1900-01-01',
                'step' => 'any',
                'required' => false,
            ],
        ]);
        $this->add([
            'name' => 'isActive',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Active',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'           => 1,
                'data-toggle'     => 'collapse',
                'data-target'     => '#canonicalSuppressionGroup',
                'aria-expanded'   => 'false',
                'aria-controls'   => 'canoicalSuppressionGroup'
            ],
        ]);
//         $this->add([
//             'name' => 'suppressionDate',
//             'type' => 'Date',
//             'options' => [
//                 'label' => 'Suppression date',
//                 'format' => 'Y-m-d',
//             ],
//             'attributes' => [
//                 'min' => '1900-01-01',
//                 'step' => 'any',
//                 'required' => false,
//             ],
//         ]);

        $this->add([
            'name' => 'isLifeCommunity',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Has lifetime membership?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
        ]);

        $this->add([
            'name' => 'isAuthor',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Has association authored books?',
                'checked_value' => '1',
                'unchecked_value' => '0',
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'value'   => '0',
            ],
        ]);

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
//         $this->add([
//             'name' => 'email2',
//             'type' => 'Email',
//             'options' => [
//                 'label' => 'Alternative Email',
//             ],
//             'attributes' => [
//                 'required' => false,
//                 'maxlength' => '70',
//             ],
//         ]);
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
                'value_options' => self::PHONE_LABEL_VALUE_OPTIONS,
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
                'value_options' => self::PHONE_LABEL_VALUE_OPTIONS,
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
                'value_options' => self::PHONE_LABEL_VALUE_OPTIONS,
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

        $this->add([
            'name' => 'geoPoint',
            'type' => 'Text',
            'options' => [
                'label' => 'Gps location (lat, long)',
                'required' => false,
            ],
            'attributes' => [
                'placeholder' => 'ex. 30.311108, -97.842738',
                'maxlength' => '32',
            ],
        ]);
        $this->add([
            'name' => 'street1',
            'type' => 'Text',
            'options' => [
                'label' => 'Street line 1',
            ],
        ]);
        $this->add([
            'name' => 'street2',
            'type' => 'Text',
            'options' => [
                'label' => 'Street line 2',
            ],
        ]);
        $this->add([
            'name' => 'cityState',
            'type' => 'Text',
            'options' => [
                'label' => 'City/State',
            ],
        ]);
        $this->add([
            'name' => 'zip',
            'type' => 'Text',
            'options' => [
                'label' => 'Zip/PLZ',
            ],
        ]);
        $this->add([
            'name' => 'googlePlaceId',
            'type' => 'Text',
            'options' => [
                'label' => 'Google place ID',
                'help-block' => 'Used for linking to the association\'s Google Place, for example, for reviews. '
                .'Use the <a href="https://developers.google.com/places/place-id">Place ID finder.</a>',
            ],
            'attributes' => [
                'placeholder' => 'ChIJj61dQgK6j4AR4GeTYWZsKWw',
                'maxlength' => '200',
            ],
        ]);
//         $this->add([
//             'name' => 'contactNotes',
//             'type' => 'Textarea',
//             'options' => [
//                 'label' => 'Contact detail notes',
//                 'required' => false,
//             ],
//         ]);

        $this->add([//http://www.codingdrama.com/bootstrap-markdown/
            'name' => 'publicNotes',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Description',
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

//      $this->add([
//          'name' => 'adminTags',
//          'type' => 'Select',
//          'options' => [
//              'label' => 'Admin tags',
//              'empty_option' => '',
//              'placeholder' => 'Select tags or type new ones...',
//              'unselected_value' => '',
//              'disable_inarray_validator' => true,
//              'value_options' => [],
//          ],
//          'attributes' => [
//              'required' => false,
//              'multiple' => true,
//          ],
//      ]);
/**
 * Common elements
 */
        $this->add([
            'name' => 'associationId',
            'type' => 'Hidden',
        ]);
        $this->add([
            'name' => 'security',
            'type' => 'csrf',
            'options' => [
                'csrf_options' => [
                     'timeout' => 600,
                ],
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
            'associationId'  => [
                'required' => true,
                'filters' => [
                    ['name' => 'ToInt'],
                ],
            ],
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
            'overrideNameFormat' => [
                'required' => false,
                'filters' => [
                    ['name' => ToBit::class]
                ],
            ],
            'isNameTranslateable' => [
                'required' => false,
                'filters' => [
                    ['name' => ToBit::class]
                ],
            ],
            'internalName' => [
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
                            'max' => 200,
                        ],
                    ],
                ],
            ],
            'isInternalNameTranslateable' => [
                'required' => false,
                'filters' => [
                    ['name' => ToBit::class]
                ],
            ],
            'parentId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_INTEGER,
                        ],
                    ],
                ],
            ],
            'kind' => [
                'required' => true,
            ],
            'country' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringToUpper'],
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
                            'max' => 6,
                        ],
                    ],
                ],
            ],
            'timeZoneId' => [
                'required' => false,
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'openingHoursHuman' => [
                'required' => false,
                'filters' => [
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
                            'max' => 500,
                        ],
                    ],
                ],
            ],
            'openingHoursSpecificationJson' => [
                'required' => false,
                'filters' => [
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
                            'max' => 1000,
                        ],
                    ],
                    ['name' => OpeningHoursSpecificationJson::class]
                ],
            ],
            'eventsHuman' => [
                'required' => false,
                'filters' => [
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
                            'max' => 1000,
                        ],
                    ],
                ],
            ],
            'eventsJson' => [
                'required' => false,
                'filters' => [
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
                            'max' => 3000,
                        ],
                    ],
                    ['name' => OpeningHoursSpecificationJson::class]
                ],
            ],
            'foundationDate' => [
                'required' => false,
                'filters' => [
                    ['name' => ToDateTime::class],
                ],
            ],
            'isAuthor' => [
                'required' => false,
                'filters' => [
                    ['name' => ToBit::class]
                ],
            ],
            'isActive' => [
                'required' => false,
                'filters' => [
                    ['name' => ToBit::class]
                ],
            ],
            'suppressionDate' => [
                'required' => false,
                'filters' => [
                    ['name' => ToDateTime::class],
                ],
            ],
            'isLifeCommunity' => [
                'required' => false,
            ],
            'email' => [
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
//             'email2' => [
//                 'required' => false,
//                 'filters' => [
//                     ['name' => StringTrim::class],
//                     ['name' => ToNull::class,
//                         'options' => [
//                             'type' => ToNull::TYPE_STRING,
//                         ]
//                     ],
//                 ],
//                 'validators' => [
//                     ['name' => EmailAddress::class],
//                 ],
//             ],
            'phone1' => $this->phoneInputFilterSpec,
            'phone1Label' => $this->phoneLabelInputFilterSpec,
            'phone2' => $this->phoneInputFilterSpec,
            'phone2Label' => $this->phoneLabelInputFilterSpec,
            'phone3' => $this->phoneInputFilterSpec,
            'phone3Label' => $this->phoneLabelInputFilterSpec,
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
            'facebookUrl' => [
                'required' => false,
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
            ],
            'twitterUser' => [
                'required' => false,
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    ['name' => Twitter::class],
                ],
            ],
            'instagramUser' => [
                'required' => false,
                'filters' => [
                    ['name' => ToNull::class,
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ]
                    ],
                ],
                'validators' => [
                    ['name' => Instagram::class],
                ],
            ],
            'geoPoint' => [
                'required' => false,
                'filters' => [
                    ['name' => ToGeoPoint::class],
                ],
                'validators' => [
                    ['name' => GpsPoint::class],
                ],
            ],
            'street1' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
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
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
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
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
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
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class],
                ],
                'validators' => [
                    [
                        'name' => StringLength::class,
                        'options' => [
                            'encoding' => 'UTF-8',
                            'max' => 15,
                        ],
                    ],
                ],
            ],
            'googlePlaceId' => [
                'required' => false,
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StripNewlines::class],
                    ['name' => StringTrim::class],
                    ['name' => ToNull::class],
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
            'publicNotes' => [
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
            'adminNotes' => [
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
//          'adminTags' => [
//              'required' => false,
//                 'filters' => [
//                     ['name' => 'StringToLower'],
//                     ['name' => 'SionModel\Filter\SortArray'],
//                 ],
//          ],
        ];
        return $this->filterSpec;
    }
}

<?php
namespace Schoenstatt\Form;

use Laminas\InputFilter\InputFilterProviderInterface;
use App\Schoenstatt\Association\AssociationFieldDomains;
use App\Schoenstatt\Association\AssociationInputFilterSpec;
use SionModel\Form\SionForm;
use Schoenstatt\Validator\TimeZone;

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

    /**
     * The value domains for the four fields whose valid values come from the database
     * or from config. Set by the factory; see getInputFilterSpecification().
     */
    private AssociationFieldDomains $fieldDomains;

    public function __construct()
    {
        parent::__construct('edit_association');
    }

    public function setFieldDomains(AssociationFieldDomains $domains): void
    {
        $this->fieldDomains = $domains;
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
                    . 'Several association types include `name formats` that insert this field within a commonly '
                    . 'used format, for example `Schoenstatt Shrine [name]`. This simplifies mass translation, '
                    . 'but can be overridden below.
For Schoenstatt Shrine names, please use the name of the closest city to which the shrine would be associated'
                . ' (for example, Tucumán), '
                . 'or in the case of a little known city, add the State/Province separated by a comma '
                . ' (for example, Sleepy Eye, Minnesota). '
                . 'If there are multiple shrines in the same city, make sure to disambiguate one from the other. '
                . 'Try to keep names as short as possible, but avoid abbreviations. '
                . 'Longer names can be used for the `Name within Schoenstatt`.',
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
                'help-block' => 'This is the name that will be shown to most users of the page '
                    . '(supposing most users are Schoenstatters). This field has no name formats as the '
                    . 'public name field does. If the internal name would be the same as the public name, '
                    . 'please leave blank.',
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
                //AssociationInputFilterSpec owns the domain check, so the Select's
                //automatic InArray is off to leave exactly one — the same arrangement
                //DatePrecision documents. Without the flag a container-built form
                //validates twice and reports the failure twice.
                'disable_inarray_validator' => true,
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
                'disable_inarray_validator' => true,
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
                //Off for the reason above, and for a second one specific to this
                //field: AssociationsController::editAction() narrows these options to
                //the association's country for display, so the element's own InArray
                //would be a *narrower* check than the one the value has to pass.
                'disable_inarray_validator' => true,
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
    "01-01": []
}}',
            ],
        ]);
        $this->add([
            'name' => 'eventsHuman',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Mass, adoration and reconciliation schedules (free text)',
                'help-block' => 'Please add all details available, including exceptions to the general rule. 
(Summer/winter schedules, months without mass, etc.). If you found the schedule on a webpage, include the link 
so users can double-check. Warning: this field is not translated.',
            ],
            'attributes' => [
                'required' => false,
                'placeholder' => 'Covenant mass every 3rd Sunday, daily mass every Wednesday at 7am, '
            . 'except in June and July. Confessions will be offered 30 minutes before every mass. '
            . 'Youth adoration every 1st and 3rd Friday while school is in session. Please verify on the Facebook page.',
            ],
        ]);
//         $this->add([
//             'name' => 'eventsJson',
//             'type' => 'Textarea',
//             'options' => [
//                 'label' => 'Mass, adoration and reconciliation JSON (advanced users)',
//                 'help-block' => 'Please see the <a href="https://schema.org/eventSchedule" target="_blank">
// Event</a> and <a href="https://schema.org/eventSchedule" target="_blank">eventSchedule</a> specification
// under format JSON+LD. Please make sure to include a location property linking to the shrine.'
// //                 , and check
// // the schema at Google\'s <a href="https://search.google.com/structured-data/testing-tool" target="_blank">
// // Structured Data Tool</a>.
// // ',
//             ],
//             'attributes' => [
//                 'required' => false,
//                 'rows' => 8,
//                 'placeholder' => '{
//   "@context": "http://schema.org/",
//   "@type": "Event",
//   "name": "Mass",
//   "description": "Mass at the Schoenstatt Shrine",
//   "location": {"@type":"CatholicChurch", "@id":"https://schoenstatt.link/en/associations/SL10519A#location"},
//   "startDate": "2019-06-02",
//   "eventSchedule": [{
//      "@type": "Schedule",
//      "byDay": ["http://schema.org/Sunday", "http://schema.org/Monday", "http://schema.org/Tuesday","http://schema.org/Wednesday","http://schema.org/Thursday","http://schema.org/Friday", "http://schema.org/Saturday"],
//      "startTime": "07:00"
//   },{
//      "@type": "Schedule",
//      "byDay": ["http://schema.org/Sunday", "http://schema.org/Monday", "http://schema.org/Tuesday","http://schema.org/Wednesday","http://schema.org/Thursday","http://schema.org/Friday", "http://schema.org/Saturday"],
//      "startTime": "15:00"
//   }
//   ]
// }',
//             ],
//         ]);
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
            'name' => 'foundationDatePrecision',
            'type' => 'Select',
            'options' => [
                'label' => 'How precisely the foundation date is known',
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
                . 'Use the <a href="https://developers.google.com/places/place-id">Place ID finder.</a>',
            ],
            'attributes' => [
                'placeholder' => 'ChIJj61dQgK6j4AR4GeTYWZsKWw',
                'maxlength' => '200',
            ],
        ]);

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

    /**
     * Delegated to App\Schoenstatt\Association\AssociationInputFilterSpec, which is
     * where the rules now live so that the API can hold agents to exactly the same
     * ones and so that they survive laminas-mvc's removal. See that class for what
     * changed when they moved, and for the three gaps deliberately left open.
     *
     * The domains the specification needs are set by AssociationFormFactory. A form
     * built without them cannot answer this question — an empty haystack would refuse
     * every submission — so it says so instead of guessing.
     */
    public function getInputFilterSpecification()
    {
        if ($this->filterSpec) {
            return $this->filterSpec;
        }
        if (! isset($this->fieldDomains)) {
            throw new \RuntimeException(
                'AssociationForm was built without its field domains, so its validation rules cannot be '
                . 'assembled. Build it through Schoenstatt\Service\AssociationFormFactory, which supplies '
                . 'them.'
            );
        }

        $this->filterSpec = (new AssociationInputFilterSpec($this->fieldDomains))->toArray();
        return $this->filterSpec;
    }

    public function setData($data)
    {
        $phoneLabels = self::PHONE_LABEL_VALUE_OPTIONS;
        $hasChanged = false;
        //A submitted label is used as an array *key* below, and only an int or a
        //string can be one: `isset($phoneLabels[$data['phone1Label']])` raises
        //"Cannot access offset of type array" for anything else. This runs in
        //setData(), before isValid(), so no validator could reject the value
        //first — `phone1Label[]=x` was a 500 with the whole submission lost.
        //Non-scalar labels are dropped here and the field's own validators
        //report whatever came with them.
        $isUsableLabel = static fn($v) => is_int($v) || is_string($v);
        if (
            isset($data['phone1Label']) && $isUsableLabel($data['phone1Label']) &&
            ! isset($phoneLabels[$data['phone1Label']])
        ) {
            $phoneLabels[$data['phone1Label']] = $data['phone1Label'];
            $hasChanged = true;
        }
        if (
            isset($data['phone2Label']) && $isUsableLabel($data['phone2Label']) &&
            ! isset($phoneLabels[$data['phone2Label']])
        ) {
            $phoneLabels[$data['phone2Label']] = $data['phone2Label'];
            $hasChanged = true;
        }
        if (
            isset($data['phone3Label']) && $isUsableLabel($data['phone3Label']) &&
            ! isset($phoneLabels[$data['phone3Label']])
        ) {
            $phoneLabels[$data['phone3Label']] = $data['phone3Label'];
            $hasChanged = true;
        }
        if ($hasChanged) {
            $this->get('phone1Label')->setValueOptions($phoneLabels);
            $this->get('phone2Label')->setValueOptions($phoneLabels);
            $this->get('phone3Label')->setValueOptions($phoneLabels);
        }
        return parent::setData($data);
    }
}

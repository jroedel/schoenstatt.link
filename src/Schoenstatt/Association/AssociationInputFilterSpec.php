<?php

declare(strict_types=1);

namespace App\Schoenstatt\Association;

use Laminas\Filter\StringToUpper;
use Laminas\Filter\StringTrim;
use Laminas\Filter\StripNewlines;
use Laminas\Filter\StripTags;
use Laminas\Filter\ToInt;
use Laminas\Filter\ToNull;
use Laminas\Validator\Date as DateValidator;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\GpsPoint;
use Laminas\Validator\GreaterThan;
use Laminas\Validator\InArray;
use Laminas\Validator\Regex;
use Laminas\Validator\StringLength;
use Laminas\Validator\Uri;
use Schoenstatt\Validator\OpeningHoursSpecificationJson;
use SionModel\Filter\ToBit;
use SionModel\Filter\ToDateTime;
use SionModel\Filter\ToGeoPoint;
use SionModel\Form\DatePrecision;
use SionModel\Validator\DateWithinRange;
use SionModel\Validator\Instagram;
use SionModel\Validator\ParseableDate;
use SionModel\Validator\Phone;
use SionModel\Validator\Twitter;

use function array_keys;

/**
 * What a valid association looks like — the whole rule set, in one place, expressed
 * as a `Laminas\InputFilter` specification and owned by nothing that will be deleted.
 *
 * ## Why this is not in the form
 *
 * It used to be, as `Schoenstatt\Form\AssociationForm::getInputFilterSpecification()`,
 * and that was fine while a browser was the only thing that could edit a shrine. It
 * stopped being fine for two reasons at once:
 *
 * 1. **Automated agents now write through the API**, and the requirement is that they
 *    are held to the same rules as the web form — not similar rules, the same ones.
 *    Two copies of a rule set drift; the only way to be sure they agree is for there
 *    to be one.
 * 2. **laminas-mvc is on its way out**, and a rule set written inside a class that
 *    the MVC stack owns goes with it. Neither `laminas-form` nor `laminas-inputfilter`
 *    actually depends on `laminas-mvc` — so both can survive — but the *module* this
 *    form lives in will not, and the rules should not have to move twice.
 *
 * The form now delegates here and keeps rendering. When the form goes, this stays.
 *
 * ## This is the rules, not the whole filter
 *
 * An important distinction, and one that cost a wrong implementation to learn:
 * validating an association is **this specification plus what the form's elements
 * contribute**. `BaseInputFilter::add()` merges rather than replaces, so an `Email`
 * element's `Regex`, a `Url` element's `Uri` and a `Checkbox`'s `InArray` all survive
 * into the built filter alongside the entries below. Twelve fields are affected.
 *
 * So nothing should build an input filter straight from this array and call it "the
 * association rules" — it would be looser than the web form on those twelve.
 * {@see AssociationValidator} is what assembles the real thing, and it is what the
 * API uses.
 *
 * A bare `new Laminas\InputFilter\Factory()` does resolve every name below, custom
 * validators included — measured, not assumed — which is why no container is needed
 * anywhere in this path.
 *
 * ## What changed when it moved
 *
 * The move was the moment to close the gaps `test/Fuzz` had been recording, so this
 * is not a byte-for-byte copy. Every difference is deliberate:
 *
 * - **`kind`, `country`, `parentId` and `timeZoneId` gained an `InArray`.** All four
 *   are `Select`s, and a form specification *replaces* the element-derived input by
 *   name, so naming them here without restating the domain check is what removed it.
 *   The domains come from {@see AssociationFieldDomains}. Every value stored in
 *   `sch_associations` today was checked against the new haystacks first: 23 kinds,
 *   41 countries and 29 time zones, all inside. No existing row became uneditable.
 * - **Nine text fields gained a `StringLength`**, each matched to the column it is
 *   written into. The two `text` columns are bounded at 16,383 rather than 65,535:
 *   `StringLength` counts characters and the column holds bytes, so the byte-safe
 *   character count under 4-byte UTF-8 is a quarter of the column width.
 * - **The three URL labels gained a `StringLength` of 50**, matching the phone labels,
 *   which get theirs from `SionForm`. Without it a long label is an SQLSTATE 22001 on
 *   save rather than a validation message.
 * - **`eventsJson` is gone.** Its element is commented out on the form, but the key
 *   stayed in the specification — and a specification key naming no element still
 *   becomes an input, so `getData()` emitted `eventsJson => null` on every submission
 *   and `SionTable::updateHelper()` wrote that null over whatever was stored. Four
 *   associations hold an `EventsJson` value; editing any of them through the form
 *   erased it. Removing the key means the field is absent from the data, and
 *   `updateHelper()` skips absent fields.
 *
 * ## What deliberately stayed a gap
 *
 * Three groups are still recorded in `test/Fuzz/known-form-gaps.php`, because closing
 * them would do harm:
 *
 * - **The six `*Label` selects have no `InArray`,** and must not. They are
 *   free text with a suggested list — `AssociationForm::setData()` injects an
 *   unrecognised submitted label into the options so it renders — and the database
 *   proves the freedom is used: `Casa del Peregrino`, `Gift shop`, `Rector`,
 *   `Retreat center`, `Sr. Philomina` and seven more sit outside the two fixed lists,
 *   across 40-odd rows. A domain check would make every one of those uneditable. They
 *   are bounded by length instead, which is the constraint that actually applies.
 * - **`geoPoint` has no length bound.** `ToGeoPoint` runs first and turns the string
 *   into a `SionModel\Db\GeoPoint`, so by the time validators see it there is no
 *   string to measure — a `StringLength` would reject every valid coordinate. `GpsPoint`
 *   is the check that belongs here.
 * - **`associationId` has no length bound**, for the same shape of reason: `ToInt`
 *   makes it an int. It is also decorative — `SionController` takes the id it updates
 *   from the route, never from this field.
 */
final class AssociationInputFilterSpec
{
    /**
     * Byte-safe character bound for a MySQL `text` column.
     *
     * `text` holds 65,535 **bytes**; `StringLength` counts **characters**. A string of
     * 65,535 characters is up to four times that in bytes under utf8mb4, so bounding at
     * the column width would still permit an SQLSTATE 22001. A quarter of it cannot.
     */
    private const TEXT_COLUMN_CHARS = 16383;

    public function __construct(private readonly AssociationFieldDomains $domains)
    {
    }

    /**
     * The field names this specification governs, which is also the set an API caller
     * may write. Derived from the specification rather than listed separately, so the
     * two cannot disagree.
     *
     * @return list<string>
     */
    public function fieldNames(): array
    {
        return array_keys($this->toArray());
    }

    /**
     * What a checkbox may post: its checked and unchecked values, and nothing else.
     *
     * `Laminas\Form\Element\Checkbox` builds exactly this for itself
     * (`InArray(['1', '0'], strict: false)`) and hands it to the input filter, which is
     * why six association fields were constrained here today without this class saying
     * so — see `SionModel\Form\CheckboxDomain`. That stops being true when
     * `SionModel\Form\Validation\InputFilter` replaces `Laminas\InputFilter`: it reads
     * the specification and nothing else.
     *
     * Stated as a literal rather than read off an element, because **this class has no
     * elements**. It is the shared contract between the web form and the API, and the API
     * builds no form. That the values are `'1'` and `'0'` is a fact about the data — the
     * columns are `tinyint` — rather than about how a checkbox renders, so this is the
     * right place for it. `AssociationValidationParityTest` asserts the form's checkboxes
     * still agree, so the literal cannot drift away from the elements unnoticed.
     *
     * Five of the six also carry `ToBit`, which normalises anything to 0 or 1 before a
     * validator runs and makes this check unreachable for them. `isLifeCommunity` does
     * not, and there the check is the only thing standing between an arbitrary string and
     * the column.
     */
    /**
     * What `Laminas\\Form\\Element\\Url`, `Email` and `Date` add to a field of their own
     * accord, restated here for the same reason as {@see CHECKBOX_DOMAIN}: this class has
     * no elements to read, and `SionModel\\Form\\Validation\\InputFilter` reads the
     * specification and nothing else.
     *
     * Every other form derives these from the element through
     * `SionModel\\Form\\InputTypeRules`, which is where the reasoning and the citations
     * live. Six association fields cannot, so `AssociationValidationParityTest` asserts
     * the elements still match what is written here.
     */
    private const URI = [
        'name'    => Uri::class,
        'options' => ['allowAbsolute' => true, 'allowRelative' => false],
    ];

    /** laminas' own HTML5 pattern, which is looser than the EmailAddress beside it. */
    private const HTML5_EMAIL = [
        'name'    => Regex::class,
        'options' => ['pattern' => '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/'],
    ];

    /**
     * The `Date` element's format check and its `min` attribute. Note the two lower
     * bounds differ on purpose and both already ran: 1900-01-01 is the element's, and
     * `DateWithinRange` narrows it to the movement's founding.
     */
    private const HTML5_DATE = [
        ['name' => DateValidator::class, 'options' => ['format' => 'Y-m-d']],
        ['name' => GreaterThan::class, 'options' => ['min' => '1900-01-01', 'inclusive' => true]],
    ];

    private const CHECKBOX_DOMAIN = [
        [
            'name'    => InArray::class,
            'options' => ['haystack' => ['1', '0'], 'strict' => false],
        ],
    ];

    /**
     * @return array<string, mixed> a `Laminas\InputFilter\Factory` specification
     */
    public function toArray(): array
    {
        return [
            'associationId'  => [
                'required' => true,
                'filters'  => [
                    ['name' => ToInt::class],
                ],
            ],
            'name' => [
                'required' => true,
                'filters'  => self::plainTextFilters(),
                'validators' => [
                    self::maxLength(200),
                ],
            ],
            'overrideNameFormat' => [
                'required' => false,
                'filters'  => [['name' => ToBit::class]],
                'validators' => self::CHECKBOX_DOMAIN,
            ],
            'isNameTranslateable' => [
                'required' => false,
                'filters'  => [['name' => ToBit::class]],
                'validators' => self::CHECKBOX_DOMAIN,
            ],
            'internalName' => [
                'required' => false,
                'filters'  => self::plainTextFilters(),
                'validators' => [
                    self::maxLength(200),
                ],
            ],
            'isInternalNameTranslateable' => [
                'required' => false,
                'filters'  => [['name' => ToBit::class]],
                'validators' => self::CHECKBOX_DOMAIN,
            ],
            'parentId' => [
                'required' => false,
                'filters'  => [
                    ['name' => ToInt::class],
                    [
                        'name'    => ToNull::class,
                        'options' => ['type' => ToNull::TYPE_INTEGER],
                    ],
                ],
                //Loose, because `ToInt` yields an int today but a numeric string would be
                //just as correct a parent id and a strict check would refuse it. See
                //self::inArray() for why "loose" here is mode 0 and never mode -1.
                'validators' => [
                    self::inArray($this->domains->parentIds, strict: false),
                ],
            ],
            'kind' => [
                'required'   => true,
                'validators' => [
                    self::inArray($this->domains->kinds),
                ],
            ],
            'country' => [
                'required' => false,
                'filters'  => [
                    ['name' => StringToUpper::class],
                    [
                        'name'    => ToNull::class,
                        'options' => ['type' => ToNull::TYPE_STRING],
                    ],
                ],
                'validators' => [
                    self::maxLength(6),
                    //After StringToUpper, so the haystack is the uppercase form the
                    //value options already use — `GB-SCT` included.
                    self::inArray($this->domains->countries),
                ],
            ],
            'timeZoneId' => [
                'required' => false,
                'filters'  => [
                    [
                        'name'    => ToNull::class,
                        'options' => ['type' => ToNull::TYPE_STRING],
                    ],
                ],
                'validators' => [
                    self::inArray($this->domains->timeZones),
                ],
            ],
            'openingHoursHuman' => [
                'required'   => false,
                'filters'    => self::toNullString(),
                'validators' => [self::maxLength(500)],
            ],
            'openingHoursSpecificationJson' => [
                'required'   => false,
                'filters'    => self::toNullString(),
                'validators' => [
                    self::maxLength(1000),
                    ['name' => OpeningHoursSpecificationJson::class],
                ],
            ],
            'eventsHuman' => [
                'required'   => false,
                'filters'    => self::toNullString(),
                'validators' => [self::maxLength(1000)],
            ],
            'foundationDate' => [
                'required' => false,
                'filters'  => [
                    ['name' => ToDateTime::class],
                ],
                'validators' => [
                    ...self::HTML5_DATE,
                    ['name' => ParseableDate::class],
                    [
                        'name'    => DateWithinRange::class,
                        'options' => ['min' => '1914-10-18', 'max' => 'today'],
                    ],
                ],
            ],
            'foundationDatePrecision' => DatePrecision::filterSpec(),
            'isAuthor' => [
                'required' => false,
                'filters'  => [['name' => ToBit::class]],
                'validators' => self::CHECKBOX_DOMAIN,
            ],
            'isActive' => [
                'required' => false,
                'filters'  => [['name' => ToBit::class]],
                'validators' => self::CHECKBOX_DOMAIN,
            ],
            'isLifeCommunity' => [
                'required' => false,
                'validators' => self::CHECKBOX_DOMAIN,
            ],
            'email' => [
                'required' => false,
                'filters'  => [
                    ['name' => StringTrim::class],
                    [
                        'name'    => ToNull::class,
                        'options' => ['type' => ToNull::TYPE_STRING],
                    ],
                ],
                'validators' => [
                    self::HTML5_EMAIL,
                    ['name' => EmailAddress::class],
                    self::maxLength(200),
                ],
            ],
            'phone1'      => self::phone(),
            'phone1Label' => self::phoneLabel(),
            'phone2'      => self::phone(),
            'phone2Label' => self::phoneLabel(),
            'phone3'      => self::phone(),
            'phone3Label' => self::phoneLabel(),
            'url1'        => self::url(),
            'url1Label'   => self::urlLabel(),
            'url2'        => self::url(),
            'url2Label'   => self::urlLabel(),
            'url3'        => self::url(),
            'url3Label'   => self::urlLabel(),
            'facebookUrl' => self::url(),
            'twitterUser' => [
                'required'   => false,
                'filters'    => self::toNullString(),
                'validators' => [
                    ['name' => Twitter::class],
                    self::maxLength(50),
                ],
            ],
            'instagramUser' => [
                'required'   => false,
                'filters'    => self::toNullString(),
                'validators' => [
                    ['name' => Instagram::class],
                    self::maxLength(50),
                ],
            ],
            'geoPoint' => [
                'required' => false,
                'filters'  => [
                    ['name' => ToGeoPoint::class],
                ],
                'validators' => [
                    ['name' => GpsPoint::class],
                ],
            ],
            'street1'   => self::boundedPlainText(70),
            'street2'   => self::boundedPlainText(70),
            'cityState' => self::boundedPlainText(40),
            'zip'       => self::boundedPlainText(15),
            'googlePlaceId' => self::boundedPlainText(200),
            'publicNotes'   => self::markdownNotes(),
            'adminNotes'    => self::markdownNotes(),
        ];
    }

    // ------------------------------------------------------------------ fragments

    /**
     * A phone number, and a label for one.
     *
     * These two are copies of `SionForm::$phoneInputFilterSpec` and
     * `$phoneLabelInputFilterSpec`, which the form used to substitute in. They are
     * stated here instead because a form property cannot be read without a form, and
     * an API request has none. The copy is not left to trust:
     * `test/Integration/AssociationValidationParityTest` reaches both by reflection
     * and fails the day they diverge.
     *
     * @return array<string, mixed>
     */
    private static function phone(): array
    {
        return [
            'required'   => false,
            'filters'    => [['name' => ToNull::class]],
            'validators' => [
                self::maxLength(50),
                ['name' => Phone::class],
            ],
        ];
    }

    /**
     * A phone label: free text with a suggested list, bounded at the varchar(50) it
     * lands in. See the class docblock for why there is no `InArray`.
     *
     * The bare `ToNull` — no `type` option, so the default `ALL` — is `SionForm`'s and
     * is kept rather than tidied to `TYPE_STRING` like its URL-label twin below. The
     * two really do differ in the original, `ALL` also nulls `'0'`, and quietly
     * aligning them would be a behaviour change smuggled in under a refactor.
     *
     * @return array<string, mixed>
     */
    private static function phoneLabel(): array
    {
        return [
            'required'   => false,
            'filters'    => [
                ['name' => StripTags::class],
                ['name' => StripNewlines::class],
                ['name' => StringTrim::class],
                ['name' => ToNull::class],
            ],
            'validators' => [self::maxLength(50)],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function plainTextFilters(): array
    {
        return [
            ['name' => StripTags::class],
            ['name' => StripNewlines::class],
            ['name' => StringTrim::class],
            [
                'name'    => ToNull::class,
                'options' => ['type' => ToNull::TYPE_STRING],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function toNullString(): array
    {
        return [
            [
                'name'    => ToNull::class,
                'options' => ['type' => ToNull::TYPE_STRING],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function boundedPlainText(int $max): array
    {
        return [
            'required' => false,
            'filters'  => [
                ['name' => StripTags::class],
                ['name' => StripNewlines::class],
                ['name' => StringTrim::class],
                ['name' => ToNull::class],
            ],
            'validators' => [self::maxLength($max)],
        ];
    }

    /**
     * A description field: markdown, newlines kept, tags stripped.
     *
     * @return array<string, mixed>
     */
    private static function markdownNotes(): array
    {
        return [
            'required' => false,
            'filters'  => [
                ['name' => StripTags::class],
                [
                    'name'    => ToNull::class,
                    'options' => ['type' => ToNull::TYPE_STRING],
                ],
            ],
            'validators' => [self::maxLength(self::TEXT_COLUMN_CHARS)],
        ];
    }

    /** @return array<string, mixed> */
    private static function url(): array
    {
        return [
            'required'   => false,
            'filters'    => self::toNullString(),
            //varchar(1000). The element's `maxlength` attribute says 255, which is a
            //client-side courtesy; the column is what a stored value has to fit.
            'validators' => [
                self::URI,
                self::maxLength(1000),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function urlLabel(): array
    {
        return [
            'required' => false,
            'filters'  => self::plainTextFilters(),
            //Matches the phone labels, which get theirs from SionForm, and the
            //varchar(50) both land in. No InArray — see the class docblock.
            'validators' => [self::maxLength(50)],
        ];
    }

    /** @return array<string, mixed> */
    private static function maxLength(int $max): array
    {
        return [
            'name'    => StringLength::class,
            'options' => [
                'encoding' => 'UTF-8',
                'max'      => $max,
            ],
        ];
    }

    /**
     * A domain check.
     *
     * The loose mode is `COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY` (0)
     * and **never** `COMPARE_NOT_STRICT` (-1), which is not merely lax but broken:
     * measured against a haystack of association ids, `InArray` in mode -1 answered
     * `true` for `999999`, a value nothing in the list resembles. It is the mode
     * laminas named after the vulnerability it carries. Mode 0 rejects that and still
     * accepts `'569'` for `569`, which is the tolerance a loose check is actually
     * wanted for.
     *
     * @param list<string>|list<int> $haystack
     * @return array<string, mixed>
     */
    private static function inArray(array $haystack, bool $strict = true): array
    {
        return [
            'name'    => InArray::class,
            'options' => [
                'haystack' => $haystack,
                'strict'   => $strict
                    ? InArray::COMPARE_STRICT
                    : InArray::COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY,
            ],
        ];
    }
}

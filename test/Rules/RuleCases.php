<?php

declare(strict_types=1);

namespace SchoenstattTest\Rules;

use SchoenstattTest\Fuzz\HostileInputCorpus;

require_once __DIR__ . '/../Fuzz/HostileInputCorpus.php';

/**
 * Every filter and validator the application's specifications name, and what to feed them.
 *
 * ## Why a case list and not the live specifications
 *
 * {@see \SchoenstattTest\Form\EngineSurface} already runs the live specifications: it
 * submits two datasets to all 42 forms and records the verdict, the values and the
 * messages. That is the integration-level oracle and it is the more important of the two.
 *
 * It is also blind in a way that matters here. A form's two datasets reach each rule with
 * the one or two values that form's fields happen to carry, so `StripTags` is measured on
 * whatever `FormData` produced and never on a comment, an unclosed tag or an attribute;
 * `ToNull` is measured on `''` and never on `'0'`, which is the value it treats
 * differently from every other falsy one; `InArray`'s haystacks come from the capsule's
 * database and change when the data does. **This file is the unit-level oracle**: every
 * rule, against a fixed corpus that does not move, so a replacement that differs only on
 * an input no form dataset produces is a failing test rather than a surprise in the
 * database.
 *
 * ## Why the labels carry no class name
 *
 * `test/Element/element-surface.php` recorded 441 elements and the element swap changed 432
 * lines, **every one of them a class name** — a diff that says nothing survived to say
 * anything with. The lesson is in laminas-exit-iterations.md and it is applied here: a case
 * is labelled by the short rule name and its options, never by the class, and resolution
 * goes through {@see \SionModel\Form\Validation\InputFilter}'s own resolver — the same one
 * production uses. So when the laminas rules are replaced, the keys of the recording do not
 * move and every changed line is an answer that changed.
 *
 * ## Where the cases come from
 *
 * The census of 2026-09-11: every `(name, options)` pair reachable from
 * `FormSpecification::of()` over all 42 forms, with three sets left out and one added.
 *
 * - **`InArray`'s real haystacks** are the database's, up to 4,203 entries, and a recording
 *   of them would fail the day someone adds a country. The haystacks here are synthetic and
 *   cover what the rule actually decides: strict and non-strict, and the int-versus-string
 *   comparison that `COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY` exists for.
 * - **`Callback`'s callbacks** are per-form closures, so the case here pins the contract —
 *   the callback is called with the value and its return replaces it — rather than any
 *   one form's.
 * - **`Db\RecordExists` and `Db\NoRecordExists`** carry a live adapter and answer a question
 *   about the capsule's rows, not about themselves. What is recorded of them is the SQL they
 *   build; see {@see RuleSurface::databaseQueries()}.
 *
 * And one case that was here and should not have been: a `Uri` validator with **no**
 * options. laminas resolved that to the generic `Laminas\Uri\Uri`, which accepts
 * `javascript:` and `mailto:` and does not force a path, where every real use passed
 * `uriHandler => Laminas\Uri\Http` and got neither. Recording the default was recording a
 * configuration nothing has, and its five answers would have read as regressions of a rule
 * nothing applies.
 */
final class RuleCases
{
    /**
     * label => `['name' => <spec name>, 'options' => <spec options>]`, exactly as a
     * specification writes them.
     *
     * Both the short names (`'StringTrim'`) and the fully-qualified ones appear in the
     * live specifications and resolve through different branches of the resolver, so both
     * spellings are represented rather than normalised away.
     *
     * @return array<string, array{name: string, options: array<string, mixed>}>
     */
    public static function filters(): array
    {
        return [
            'StringTrim'                  => ['name' => 'StringTrim', 'options' => []],
            'StringTrim (fully qualified)' => ['name' => 'SionModel\Filter\StringTrim', 'options' => []],
            'StripTags'                   => ['name' => 'StripTags', 'options' => []],
            'StripNewlines'               => ['name' => 'StripNewlines', 'options' => []],
            'StringToLower'               => ['name' => 'StringToLower', 'options' => []],
            'StringToUpper'               => ['name' => 'StringToUpper', 'options' => []],
            'ToInt'                       => ['name' => 'ToInt', 'options' => []],
            'Int (the alias)'             => ['name' => 'Int', 'options' => []],
            'ToNull'                      => ['name' => 'ToNull', 'options' => []],
            'ToNull type=2 (integer)'     => ['name' => 'ToNull', 'options' => ['type' => 2]],
            'ToNull type=8 (string)'      => ['name' => 'ToNull', 'options' => ['type' => 8]],
            'Boolean'                     => ['name' => 'Boolean', 'options' => []],
            'DateSelect'                  => ['name' => 'SionModel\Filter\DateSelect', 'options' => []],
            'Callback (strrev)'           => ['name' => 'Callback', 'options' => ['callback' => 'strrev']],

            //Ours, and re-parented by this iteration: what they answer must not move either.
            'SionModel ToBit'                       => ['name' => 'SionModel\Filter\ToBit', 'options' => []],
            'SionModel ToBit null_defaults_to=true' => ['name' => 'SionModel\Filter\ToBit', 'options' => ['null_defaults_to' => true]],
            'SionModel ToDateTime'                  => ['name' => 'SionModel\Filter\ToDateTime', 'options' => []],
            'SionModel ToGeoPoint'                  => ['name' => 'SionModel\Filter\ToGeoPoint', 'options' => []],
            'SionModel SortArray'                   => ['name' => 'SionModel\Filter\SortArray', 'options' => []],
            'SionModel DateSelectNoYear'            => ['name' => 'SionModel\Filter\DateSelectNoYear', 'options' => []],
            'Books BookList'                        => ['name' => 'Books\Filter\BookList', 'options' => []],
        ];
    }

    /**
     * @return array<string, array{name: string, options: array<string, mixed>}>
     */
    public static function validators(): array
    {
        $emailPattern = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/';

        return [
            'NotEmpty'                    => ['name' => 'NotEmpty', 'options' => []],
            'NotEmpty type=8 (string)'    => ['name' => 'NotEmpty', 'options' => ['type' => 8]],
            'NotEmpty type=32 (array)'    => ['name' => 'NotEmpty', 'options' => ['type' => 32]],
            'NotEmpty with own message'   => [
                'name'    => 'NotEmpty',
                'options' => ['messages' => ['isEmpty' => 'Type the library name to confirm the deletion.']],
            ],

            'StringLength max=2'          => ['name' => 'StringLength', 'options' => ['max' => 2]],
            'StringLength max=2 UTF-8'    => ['name' => 'StringLength', 'options' => ['encoding' => 'UTF-8', 'max' => 2]],
            'StringLength min=3 UTF-8'    => ['name' => 'StringLength', 'options' => ['min' => 3, 'encoding' => 'UTF-8']],
            'StringLength min=4 max=40'   => ['name' => 'StringLength', 'options' => ['min' => 4, 'max' => 40]],
            'StringLength max=255 (fully qualified)' => [
                'name'    => 'SionModel\Validator\StringLength',
                'options' => ['encoding' => 'UTF-8', 'max' => 255],
            ],

            'Regex (hex-40)'              => ['name' => 'Regex', 'options' => ['pattern' => '/\A[0-9a-f]{40}\z/']],
            'Regex (decimal)'             => ['name' => 'Regex', 'options' => ['pattern' => '(^-?\d*(\.\d+)?$)']],
            'Regex (email pattern)'       => ['name' => 'Regex', 'options' => ['pattern' => $emailPattern]],
            'Regex with own message'      => [
                'name'    => 'SionModel\Validator\Regex',
                'options' => [
                    'pattern'          => '/^(?:18|19|20)\d{2,2}(?:-[0-3]\d)?(?:-[0-3]\d)?$/',
                    'messageTemplates' => [
                        'regexNotMatch' => 'Please enter a valid date. Remember to add a `0` before single digit month and day numbers.',
                    ],
                ],
            ],

            'InArray (strings, loose)'    => [
                'name'    => 'InArray',
                'options' => ['haystack' => ['alpha', 'beta', '7'], 'strict' => 0],
            ],
            'InArray (strings, strict)'   => [
                'name'    => 'InArray',
                'options' => ['haystack' => ['alpha', 'beta', '7'], 'strict' => 1],
            ],
            'InArray (ints, loose)'       => [
                'name'    => 'InArray',
                'options' => ['haystack' => [1, 2, 7], 'strict' => 0],
            ],
            'InArray (ints, strict)'      => [
                'name'    => 'InArray',
                'options' => ['haystack' => [1, 2, 7], 'strict' => 1],
            ],
            'InArray (strict=false)'      => [
                'name'    => 'InArray',
                'options' => ['haystack' => ['alpha', 'beta'], 'strict' => false],
            ],

            'Explode over Regex'          => [
                'name'    => 'Explode',
                'options' => ['validator' => ['name' => 'Regex', 'options' => ['pattern' => $emailPattern]]],
            ],
            'Explode over InArray'        => [
                'name'    => 'SionModel\Validator\Explode',
                'options' => ['validator' => ['name' => 'InArray', 'options' => ['haystack' => ['alpha', 'beta'], 'strict' => 0]]],
            ],

            'Date'                        => ['name' => 'Date', 'options' => []],
            'Date format=Y-m-d'           => ['name' => 'Date', 'options' => ['format' => 'Y-m-d']],
            'Digits'                      => ['name' => 'Digits', 'options' => []],
            'EmailAddress'                => ['name' => 'EmailAddress', 'options' => []],
            'GpsPoint'                    => ['name' => 'GpsPoint', 'options' => []],
            'Timezone'                    => ['name' => 'Timezone', 'options' => []],
            'GreaterThan min=1800'        => ['name' => 'GreaterThan', 'options' => ['min' => 1800, 'inclusive' => true]],
            'GreaterThan min=date'        => ['name' => 'GreaterThan', 'options' => ['min' => '1900-01-01', 'inclusive' => true]],
            'LessThan max=2027'           => ['name' => 'LessThan', 'options' => ['max' => 2027, 'inclusive' => true]],
            'Step base=1800 step=1'       => ['name' => 'Step', 'options' => ['baseValue' => 1800, 'step' => 1]],
            'Identical literal'           => ['name' => 'Identical', 'options' => ['token' => 'jk-text', 'literal' => true]],
            'Identical literal with own message' => [
                'name'    => 'Identical',
                'options' => [
                    'token'    => 'fuzz',
                    'literal'  => true,
                    'strict'   => true,
                    'messages' => ['notSame' => "That is not this library's name. Nothing has been deleted."],
                ],
            ],
            'Uri absolute only'           => [
                'name'    => 'Uri',
                //`uriHandler` is gone from the options and the answers did not move.
                //laminas needed it — without one it validated through the generic
                //`Laminas\Uri\Uri`, which accepts `javascript:` and `mailto:` — and
                //`SionModel\Validator\Uri` has no generic mode to fall into.
                'options' => ['allowAbsolute' => true, 'allowRelative' => false],
            ],

            //Ours, and re-parented by this iteration.
            'SionModel ParseableDate'     => ['name' => 'SionModel\Validator\ParseableDate', 'options' => []],
            'SionModel Phone'             => ['name' => 'SionModel\Validator\Phone', 'options' => []],
            'SionModel Instagram'         => ['name' => 'SionModel\Validator\Instagram', 'options' => []],
            'SionModel Twitter'           => ['name' => 'SionModel\Validator\Twitter', 'options' => []],
            'SionModel Skype'             => ['name' => 'SionModel\Validator\Skype', 'options' => []],
            'SionModel Slack'             => ['name' => 'SionModel\Validator\Slack', 'options' => []],
            'SionModel RegularExpression' => ['name' => 'SionModel\Validator\RegularExpression', 'options' => []],
            'SionModel DateWithinRange'   => [
                'name'    => 'SionModel\Validator\DateWithinRange',
                'options' => ['min' => '1914-10-18', 'max' => '2026-09-11'],
            ],
            'Schoenstatt OpeningHoursSpecificationJson' => [
                'name'    => 'Schoenstatt\Validator\OpeningHoursSpecificationJson',
                'options' => [],
            ],
        ];
    }

    /**
     * label => value. The hostile corpus, plus the benign and boundary values a rule needs
     * to be told apart from a rule that always says yes.
     *
     * Labels are stable identifiers: they are the keys of the recording, so renaming one
     * invalidates every entry under it.
     *
     * @return array<string, mixed>
     */
    public static function inputs(): array
    {
        return HostileInputCorpus::values() + [
            //--- plain text, and the whitespace and markup the filters are for
            'plain-text'            => 'Hello world',
            'padded'                => '  padded  ',
            'newlines'              => "line one\nline two\r\nline three",
            'html-basic'            => '<b>bold</b> &amp; <i>x</i>',
            'html-attribute'        => '<a href="x" onclick="y">link</a>',

            //--- the three values ToNull tells apart, and the two Boolean does
            'string-zero'           => '0',
            'string-false'          => 'false',
            'string-true'           => 'true',
            'string-on'             => 'on',
            'bool-true'             => true,
            'bool-false'            => false,
            'int-zero'              => 0,
            'int-42'                => 42,
            'float-pi'              => 3.14,

            //--- lengths, around the smallest bound any specification declares
            'two-chars'             => 'ab',
            'three-chars'           => 'abc',
            'forty-one-chars'       => '12345678901234567890123456789012345678901',

            //--- addresses, including the two cases a TLD table decides and a shape check
            //    does not
            'email-plain'           => 'user@example.com',
            'email-mixed-case'      => 'User.Name+tag@Example.CO.UK',
            'email-unknown-tld'     => 'user@example.invalidtld',
            'email-no-dot'          => 'user@localhost',
            'email-idn'             => 'user@exämple.de',
            'email-two-at'          => 'a@b@example.com',
            'email-list'            => 'one@example.com,two@example.com',

            //--- URIs. `toString()` normalisation is what reaches the database.
            'url-https'             => 'https://example.com/path?a=b#c',
            'url-uppercase-scheme'  => 'HTTPS://Example.COM/Path',
            'url-no-scheme'         => 'example.com/path',
            'url-relative'          => '/path/only',
            'url-space'             => 'https://example.com/a b',
            'url-percent-encoded'   => 'https://example.com/herbstst%C3%BCrme.pdf',
            'url-raw-utf8-path'     => 'https://example.com/Santuário-de-Schoenstatt',
            'url-javascript'        => 'javascript:alert(1)',
            'url-mailto'            => 'mailto:user@example.com',

            //--- the rest of the specialised rules
            'gps-point'             => '50.1,8.6',
            'gps-one-coordinate'    => '50.1',
            'timezone'              => 'Europe/Berlin',
            'timezone-abbreviation' => 'CET',
            'date-iso'              => '2020-03-15',
            'date-iso-datetime'     => '2020-03-15 14:30:00',
            'date-german'           => '15.03.2020',
            'phone-international'   => '+49 1234 567',
            'year-1799'             => '1799',
            'year-2020'             => '2020',
            'handle'                => 'someone_1',
            'opening-hours-json'    => '[{"dayOfWeek":"Monday","opens":"09:00","closes":"17:00"}]',

            //--- the shapes a date-select element posts, which DateSelect reassembles
            'date-select-parts'     => ['year' => '2020', 'month' => '03', 'day' => '15'],
            'date-select-no-year'   => ['month' => '03', 'day' => '15'],
        ];
    }
}

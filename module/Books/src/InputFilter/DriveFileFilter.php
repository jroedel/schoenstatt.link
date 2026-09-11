<?php

declare(strict_types=1);

namespace Books\InputFilter;

use SionModel\Filter\StripNewlines;
use SionModel\Filter\StripTags;
use SionModel\Form\Validation\InputFilter;
use SionModel\Validator\Digits;
use SionModel\Validator\StringLength;
use SionModel\Validator\Uri;

/**
 * What a row of Google Drive's file listing has to look like before it is shown.
 *
 * The data is a third party's JSON, so this is the only thing standing between the API's
 * answer and a publication page. `Books\Service\DriveGateway::getPublicationFiles()` drops
 * a row that fails rather than reporting it.
 *
 * It was a `Laminas\InputFilter\InputFilter` subclass until iteration A, which is why it
 * reads as a specification rather than as a chain of `add()` calls: the engine that
 * replaced that package — {@see InputFilter} — takes the whole specification at once, the
 * way `getInputFilterSpecification()` always described a form's.
 */
final class DriveFileFilter
{
    /**
     * @return array<string, mixed>
     */
    public static function specification(): array
    {
        $text = [
            ['name' => StripNewlines::class],
            ['name' => StripTags::class],
        ];

        return [
            'eventId'       => [
                'required'   => false,
                'validators' => [['name' => Digits::class]],
            ],
            'publicationId' => [
                'required'   => false,
                'validators' => [['name' => Digits::class]],
            ],
            'fileId'        => ['required' => false],
            'fileName'      => [
                'required' => true,
                'filters'  => $text,
            ],
            'url'           => [
                'required'   => true,
                'validators' => [
                    [
                        'name'    => Uri::class,
                        'options' => ['allowRelative' => false],
                    ],
                ],
            ],
            'size'          => [
                'required'   => false,
                'validators' => [['name' => Digits::class]],
            ],
            'description'   => [
                'required'   => false,
                'validators' => [
                    [
                        'name'    => StringLength::class,
                        'options' => ['max' => 300],
                    ],
                ],
                'filters'    => $text,
            ],
            'mimeType'      => [
                'required'   => false,
                'validators' => [
                    [
                        'name'    => StringLength::class,
                        'options' => ['max' => 70],
                    ],
                ],
                'filters'    => $text,
            ],
            'tags'          => [
                'required'   => false,
                'validators' => [
                    [
                        'name'    => StringLength::class,
                        'options' => ['max' => 255],
                    ],
                ],
                'filters'    => $text,
            ],
        ];
    }

    public static function engine(): InputFilter
    {
        return InputFilter::withRules(self::specification());
    }
}

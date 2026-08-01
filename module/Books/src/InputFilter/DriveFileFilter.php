<?php
namespace Books\InputFilter;

use Laminas\InputFilter\InputFilter;

class DriveFileFilter extends InputFilter
{
    public function __construct()
    {
        $this->add([
            'name'       => 'eventId',
            'required'   => false,
            'validators' => [
                ['name' => \Laminas\Validator\Digits::class ],
            ],
            'filters' => [],
        ]);
        $this->add([
            'name'       => 'publicationId',
            'required'   => false,
            'validators' => [
                ['name' => \Laminas\Validator\Digits::class ],
            ],
            'filters' => [],
        ]);
        $this->add([
            'name'       => 'fileId',
            'required'   => false,
            'validators' => [],
            'filters' => [],
        ]);
        $this->add([
            'name'       => 'fileName',
            'required'   => true,
            'validators' => [],
            'filters' => [
                ['name' => \Laminas\Filter\StripNewlines::class],
                ['name' => \Laminas\Filter\StripTags::class],
            ],
        ]);
        $this->add([
            'name'       => 'url',
            'required'   => true,
            'validators' => [
                [
                    'name' => \Laminas\Validator\Uri::class,
                    'options' => [
                        'allowRelative' => false,
                    ],
                ]
            ],
            'filters' => [],
        ]);
        $this->add([
            'name'       => 'size',
            'required'   => false,
            'validators' => [
                ['name' => \Laminas\Validator\Digits::class ],
            ],
            'filters' => [
            ],
        ]);
        $this->add([
            'name'       => 'description',
            'required'   => false,
            'validators' => [
                [
                    'name' => \Laminas\Validator\StringLength::class,
                    'options' => [
                        'max' => 300
                    ],
                ],
            ],
            'filters' => [
                ['name' => \Laminas\Filter\StripNewlines::class],
                ['name' => \Laminas\Filter\StripTags::class],
            ],
        ]);
        $this->add([
            'name'       => 'mimeType',
            'required'   => false,
            'validators' => [
                [
                    'name' => \Laminas\Validator\StringLength::class,
                    'options' => [
                        'max' => 70
                    ],
                ],
            ],
            'filters' => [
                ['name' => \Laminas\Filter\StripNewlines::class],
                ['name' => \Laminas\Filter\StripTags::class],
            ],
        ]);
        $this->add([
            'name'       => 'tags',
            'required'   => false,
            'validators' => [
                [
                    'name' => \Laminas\Validator\StringLength::class,
                    'options' => [
                        'max' => 255
                    ],
                ],
            ],
            'filters' => [
                ['name' => \Laminas\Filter\StripNewlines::class],
                ['name' => \Laminas\Filter\StripTags::class],
            ],
        ]);
    }
}

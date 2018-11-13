<?php
namespace Schoenstatt\Validator;

use Zend\Validator\Regex;

class SchoenstattLinkIdentifier extends Regex
{
    const ENTITY_ASSOCIATION = 'association';
    const ENTITY_PERSON  = 'person';
    const ENTITY_PUBLICATION = 'publication';

    const ENTITY_TYPE_ABBRS = [
        'A' => self::ENTITY_ASSOCIATION,
        'P' => self::ENTITY_PERSON,
        'L' => self::ENTITY_PUBLICATION,
    ];

    const GENERAL_REGEX = '/^SL([0-9]{5,5})([APL])$/';
    const ENTITY_REGEXS = [
        self::ENTITY_ASSOCIATION => '/^SL([0-9]{5,5})A$/',
        self::ENTITY_PERSON => '/^SL([0-9]{5,5})P$/',
        self::ENTITY_PUBLICATION => '/^SL([0-9]{5,5})L$/',
    ];
    const ENTITY_STARTING_NUMBER = [
        self::ENTITY_ASSOCIATION => 10000,
        self::ENTITY_PERSON => 30000,
        self::ENTITY_PUBLICATION => 60000,
    ];

    public function __construct($entityType = null)
    {
        if (!isset($entityType)) {
            parent::__construct(self::GENERAL_REGEX);
        } elseif (!isset(self::ENTITY_REGEXS[$entityType])) {
            throw new \Exception("Invalid entity type `$entityType`");
        } else {
            parent::__construct(self::ENTITY_REGEXS[$entityType]);
        }
    }
}

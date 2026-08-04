<?php

namespace Schoenstatt\Validator;

use SionModel\Validator\AbstractPatternValidator;

/**
 * Validates a site-wide Schoenstatt link identifier (e.g. SL300001P).
 *
 * Extends AbstractPatternValidator rather than Laminas\Validator\Regex, which
 * laminas marked `@final`. Beyond validation this class is the canonical home
 * of the entity-type constants below, which are read across ~15 files.
 */
class SchoenstattLinkIdentifier extends AbstractPatternValidator
{
    const ENTITY_ASSOCIATION = 'association';
    const ENTITY_PERSON  = 'person';
    const ENTITY_TEXT = 'text';
    const ENTITY_EVENT = 'event';
    const ENTITY_PUBLICATION = 'publication';
    const ENTITY_COMPOSITION = 'composition';

    const ENTITY_TYPE_ABBRS = [
        'A' => self::ENTITY_ASSOCIATION,
        'P' => self::ENTITY_PERSON,
        'T' => self::ENTITY_TEXT,
        'E' => self::ENTITY_EVENT,
        'L' => self::ENTITY_PUBLICATION,
        'C' => self::ENTITY_COMPOSITION,
    ];

    /*
     * All routes listed must receive both a sw_id and slug parameter
     */
    const ENTITY_TYPE_ROUTES = [
        self::ENTITY_ASSOCIATION => 'association',
        self::ENTITY_PERSON => null,
        self::ENTITY_TEXT => 'text',
        self::ENTITY_EVENT => 'event',
        self::ENTITY_PUBLICATION => 'publication',
        self::ENTITY_COMPOSITION => 'composition',
    ];

    const GENERAL_REGEX = '/^SL([1-9][0-9]{5,5})([APTELC])$/';
    const ENTITY_REGEXS = [
        self::ENTITY_ASSOCIATION => '/^SL(1[0-9]{5,5})A$/',
        self::ENTITY_PERSON => '/^SL(3[0-9]{5,5})P$/',
        self::ENTITY_TEXT => '/^SL(4[0-9]{5,5})T$/',
        self::ENTITY_EVENT => '/^SL(6[0-9]{5,5})E$/',
        self::ENTITY_PUBLICATION => '/^SL(2[0-9]{5,5})L$/',
        self::ENTITY_COMPOSITION => '/^SL(5[0-9]{5,5})C$/',
    ];
    const ENTITY_STARTING_NUMBER = [ //starting Apr 1, 2020
        self::ENTITY_ASSOCIATION => 100000,
        self::ENTITY_PERSON => 300000,
        self::ENTITY_TEXT => 400000,
        self::ENTITY_EVENT => 600000,
        self::ENTITY_PUBLICATION => 200000,
        self::ENTITY_COMPOSITION => 500000,
    ];
    const ENTITY_STARTING_NUMBER_DIFFERENCE_APRIL_2020_CHANGE = [ //until Apr 1, 2020
        self::ENTITY_ASSOCIATION => 90000,
        self::ENTITY_PERSON => 270000,
        self::ENTITY_TEXT => 360000,
        self::ENTITY_EVENT => 600000,
        self::ENTITY_PUBLICATION => 140000,
        self::ENTITY_COMPOSITION => 405000,
    ];

    const GENERAL_OLD_REGEX = '/^SL([0-9]{5,5})([APLC])$/';
    const ENTITY_OLD_REGEXS = [
        self::ENTITY_ASSOCIATION => '/^SL([0-9]{5,5})A$/',
        self::ENTITY_PERSON => '/^SL([0-9]{5,5})P$/',
        self::ENTITY_TEXT => '/^SL([45][0-9]{4,4})T$/',
        self::ENTITY_EVENT => '/^SL(0[0-9]{4,4})E$/',
        self::ENTITY_PUBLICATION => '/^SL([0-9]{5,5})L$/',
        self::ENTITY_COMPOSITION => '/^SL(95[0-9]{3,3})C$/',
    ];
    const ENTITY_OLD_STARTING_NUMBER = [ //until Apr 1, 2020
        self::ENTITY_ASSOCIATION => 10000,
        self::ENTITY_PERSON => 30000,
        self::ENTITY_TEXT => 40000,
        self::ENTITY_EVENT => 0,
        self::ENTITY_PUBLICATION => 60000,
        self::ENTITY_COMPOSITION => 95000,
    ];

    /**
     * In April 2020, site-wide ids we're expanded from 5 to 6 digit numbers. If you wish to work
     * with pre-april 2020 ids, set $usePreApril2020Format to true in the constructor
     * @var bool $usePreApril2020Format
     */
    protected $usePreApril2020Format;

    public function __construct($entityType = null, $usePreApril2020Format = false)
    {
        $this->usePreApril2020Format = (bool)$usePreApril2020Format;
        if (! isset($entityType)) {
            if (! $this->usePreApril2020Format) {
                parent::__construct(self::GENERAL_REGEX);
            } else {
                parent::__construct(self::GENERAL_OLD_REGEX);
            }
        } elseif (! isset(self::ENTITY_REGEXS[$entityType])) {
            throw new \Exception("Invalid entity type `$entityType`");
        } else {
            if (! $this->usePreApril2020Format) {
                parent::__construct(self::ENTITY_REGEXS[$entityType]);
            } else {
                parent::__construct(self::ENTITY_OLD_REGEXS[$entityType]);
            }
        }
    }
}

<?php
namespace Schoenstatt\Filter;

use Zend\Filter\AbstractFilter;

class SchoenstattLinkIdentifier extends AbstractFilter
{
    protected $pattern;

    protected $baseNumber;
    
    /**
     * In April 2020, site-wide ids we're expanded from 5 to 6 digit numbers. If you wish to work
     * with pre-april 2020 ids, set $usePreApril2020Format to true in the constructor
     * @var bool $usePreApril2020Format
     */
    protected $usePreApril2020Format;
    
    /**
     * The class caches the last entity type that was processed. If the user needs to know
     * what it was, you can call getLastEntityType(). It will return null if 
     * @var string $lastEntityType
     */
    protected $lastEntityType;

    public function __construct($entityType = null, $usePreApril2020Format = false)
    {
        if (isset($entityType)
            && !isset(\Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_REGEXS[$entityType])
        ) {
            throw new \Exception("Invalid entity type `$entityType`");
        }
        $this->usePreApril2020Format = (bool)$usePreApril2020Format;
        $this->entityType = $entityType;
        $this->lastEntityType = $entityType;
        if (!isset($entityType)) {
            if (!$this->usePreApril2020Format) {
                $this->pattern = \Schoenstatt\Validator\SchoenstattLinkIdentifier::GENERAL_REGEX;
            } else {
                $this->pattern = \Schoenstatt\Validator\SchoenstattLinkIdentifier::GENERAL_OLD_REGEX;
            }
        } else {
            if (!$this->usePreApril2020Format) {
                $this->pattern = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_REGEXS[$entityType];
                $this->baseNumber = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_STARTING_NUMBER[$entityType];
            } else {
                $this->pattern = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_OLD_REGEXS[$entityType];
                $this->baseNumber = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_OLD_STARTING_NUMBER[$entityType];
            }
        }
    }

    public function filter($value)
    {
        if (!is_string($value)) {
            $this->lastEntityType = null;
            throw new \Exception('String expected');
        }

        $matches = null;
        preg_match($this->pattern, $value, $matches, PREG_OFFSET_CAPTURE, 0);

        if (isset($matches) && isset($matches[1]) && isset($matches[1][0])) {
            $number = (int)$matches[1][0];
            if (isset($this->baseNumber)) {
                $baseNumber = $this->baseNumber;
            } else {
                if (isset($matches[1]) && isset($matches[2][0])) {
                    $entityTypeAbbr = $matches[2][0];
                    $entityType = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_TYPE_ABBRS[$entityTypeAbbr];
                    $this->lastEntityType = $entityType;
                    if (!$this->usePreApril2020Format) {
                        $baseNumber = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_STARTING_NUMBER[$entityType];
                    } else {
                        $baseNumber = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_OLD_STARTING_NUMBER[$entityType];
                    }
                } else {
                    $this->lastEntityType = null;
                    throw \Exception('We should never be here.');
                }
            }
            return $number-$baseNumber;
        }
        $this->lastEntityType = null;
        throw new \Exception('This filter only processes valid site-wide identifiers');
    }
    
    public function getLastEntityType()
    {
        return $this->lastEntityType;
    }
}

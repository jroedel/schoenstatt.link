<?php
namespace Schoenstatt\Filter;

use Zend\Filter\AbstractFilter;

class SchoenstattLinkIdentifier extends AbstractFilter
{
    protected $pattern;

    protected $baseNumber;
    
    protected $usePreApril2020Format;
    
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
                    throw \Exception('We should never be here.');
                }
            }
            return $number-$baseNumber;
        }
    }
    
    public function getLastEntityType()
    {
        return $this->lastEntityType;
    }
}

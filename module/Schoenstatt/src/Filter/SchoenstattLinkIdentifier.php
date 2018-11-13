<?php
namespace Schoenstatt\Filter;

use Zend\Filter\AbstractFilter;

class SchoenstattLinkIdentifier extends AbstractFilter
{
    const ENTITY_ASSOCIATION = 'association';
    const ENTITY_PERSON  = 'person';
    const ENTITY_PUBLICATION = 'publication';

    protected $pattern;

    protected $baseNumber;

    public function __construct($entityType = null)
    {
        if (isset($entityType)
            && !isset(\Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_REGEXS[$entityType])
        ) {
            throw new \Exception("Invalid entity type `$entityType`");
        }
        $this->entityType = $entityType;
        if (!isset($entityType)) {
            $this->pattern = \Schoenstatt\Validator\SchoenstattLinkIdentifier::GENERAL_REGEX;
        } else {
            $this->pattern = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_REGEXS[$entityType];
            $this->baseNumber = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_STARTING_NUMBER[$entityType];
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
                    $baseNumber = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_STARTING_NUMBER[$entityType];
                } else {
                    throw \Exception('We should never be here.');
                }
            }
            return $number-$baseNumber;
        }
    }
}

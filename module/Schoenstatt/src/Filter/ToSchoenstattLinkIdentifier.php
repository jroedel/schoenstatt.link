<?php
namespace Schoenstatt\Filter;

use Zend\Filter\AbstractFilter;

class ToSchoenstattLinkIdentifier extends AbstractFilter
{
    const ENTITY_ASSOCIATION = 'association';
    const ENTITY_PERSON  = 'person';
    const ENTITY_PUBLICATION = 'publication';

    protected $entityType;

    protected $pattern;

    protected $baseNumber;

    public function __construct($entityType)
    {
        if (!isset($entityType)) {
            throw new \Exception("Invalid entity type");
        } elseif (!isset(\Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_REGEXs[$entityType])) {
            throw new \Exception("Invalid entity type `$entityType`");
        }
        $this->entityType = $entityType;
        $this->pattern = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_REGEXs[$entityType];
        $this->baseNumber = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_STARTING_NUMBER[$entityType];
    }

    public function filter($value)
    {
        if (!is_numeric($value)) {
            throw new \Exception('Number expected');
        }
        $value = (int)$value + $this->baseNumber;
        $abbr = array_search($this->entityType, \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_TYPE_ABBRS);
        $return = 'SL'.$value.$abbr;
        return $return;
    }
}

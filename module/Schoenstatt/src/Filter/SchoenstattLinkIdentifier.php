<?php
namespace Schoenstatt\Filter;

use Zend\Filter\AbstractFilter;

class SchoenstattLinkIdentifier extends AbstractFilter
{
    protected $pattern;

    protected $baseNumber;

    public function __construct($entityType)
    {
        if (!isset(\Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_REGEXs[$entityType])) {
            throw new \Exception("Invalid entity type `$entityType`");
        }
        $this->pattern = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_REGEXs[$entityType];
        $this->baseNumber = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_STARTING_NUMBER[$entityType];
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
            return $number-$this->baseNumber;
        }
    }
}

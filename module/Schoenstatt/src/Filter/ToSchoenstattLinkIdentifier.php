<?php
namespace Schoenstatt\Filter;

use Zend\Filter\AbstractFilter;

class ToSchoenstattLinkIdentifier extends AbstractFilter
{
    protected $entityType;

    protected $pattern;

    protected $baseNumber;
    
    /**
     * In April 2020, site-wide ids we're expanded from 5 to 6 digit numbers. If you wish to work
     * with pre-april 2020 ids, set $usePreApril2020Format to true in the constructor
     * @var bool $usePreApril2020Format
     */
    protected $usePreApril2020Format;

    public function __construct($entityType, $usePreApril2020Format = false)
    {
        $this->usePreApril2020Format = (bool)$usePreApril2020Format;
        if (!isset($entityType)) {
            throw new \Exception("Invalid entity type");
        } elseif (!$this->usePreApril2020Format 
            && !isset(\Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_REGEXS[$entityType])
        ) {
            throw new \Exception("Invalid entity type `$entityType`");
        } elseif ($this->usePreApril2020Format
            && !isset(\Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_OLD_REGEXS[$entityType])
            ) {
                throw new \Exception("Invalid entity type `$entityType`");
        }
        $this->entityType = $entityType;
        if (!$this->usePreApril2020Format) {
            $this->pattern = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_REGEXS[$entityType];
            $this->baseNumber = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_STARTING_NUMBER[$entityType];
        } else {
            $this->pattern = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_OLD_REGEXS[$entityType];
            $this->baseNumber = \Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_OLD_STARTING_NUMBER[$entityType];
        }
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

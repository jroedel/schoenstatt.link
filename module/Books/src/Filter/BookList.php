<?php
namespace Books\Filter;

use Zend\Filter\AbstractFilter;

class BookList extends AbstractFilter
{
    /**
     * @todo Allow other barcode patterns for future library schemas
     * {@inheritDoc}
     * @see \Zend\Filter\FilterInterface::filter()
     */
    public function filter($value)
    {
        if (is_null($value)) {
            return [];
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Only string values excepted.');
        }
        $re = '/\d{5,5}/';

        $matches = null;
        preg_match_all($re, $value, $matches, PREG_PATTERN_ORDER, 0);

        return $matches[0];
    }
}

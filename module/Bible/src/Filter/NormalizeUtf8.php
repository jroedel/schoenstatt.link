<?php
namespace Bible\Filter;

use Zend\Filter\AbstractFilter;
use voku\helper\UTF8;

class NormalizeUtf8 extends AbstractFilter
{
    public function filter($value)
    {
        return UTF8::filter($value);
    }
}

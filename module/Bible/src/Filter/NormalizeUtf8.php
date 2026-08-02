<?php
namespace Bible\Filter;

use Laminas\Filter\AbstractFilter;
use voku\helper\UTF8;

class NormalizeUtf8 extends AbstractFilter
{
    public function filter($value)
    {
        return UTF8::filter($value);
    }
}

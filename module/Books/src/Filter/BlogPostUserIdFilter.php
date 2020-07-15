<?php
namespace Schoenstatt\Filter;

use Zend\Filter\AbstractFilter;

class BlogPostUserIdFilter extends AbstractFilter
{
    protected $pattern = '/^blog_post_([0-9]{1,5})$/';

    public function filter($value)
    {
        if (! is_string($value)) {
            throw new \Exception('String expected');
        }

        $matches = null;
        preg_match($this->pattern, $value, $matches, PREG_OFFSET_CAPTURE, 0);

        if (isset($matches) && isset($matches[1]) && isset($matches[1][0])) {
            $number = (int)$matches[1][0];
            return $number;
        }
        return null;
    }
}

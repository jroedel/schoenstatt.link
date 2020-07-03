<?php
namespace Books\View\Helper;

use Zend\View\Helper\AbstractHelper;

class Markdown extends AbstractHelper
{
    protected $filter;
    
    public function __construct()
    {
        $this->filter = new \ParsedownExtra();
    }
    
    public function __invoke($value)
    {
        return $this->filter->text($value);
    }
}

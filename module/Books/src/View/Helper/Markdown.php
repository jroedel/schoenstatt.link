<?php
namespace Books\View\Helper;


class Markdown
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

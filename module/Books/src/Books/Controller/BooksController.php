<?php
namespace Books\Controller;

use SionModel\Controller\SionController;

class BooksController extends SionController
{
    public function __construct()
    {
        return parent::__construct('book');
    }
}
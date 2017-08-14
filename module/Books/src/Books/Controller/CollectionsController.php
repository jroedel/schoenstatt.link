<?php
namespace Books\Controller;

use SionModel\Controller\SionController;

class CollectionsController extends SionController
{
    public function __construct()
    {
        return parent::__construct('collection');
    }
}

<?php
namespace Books\Controller;

use SionModel\Controller\SionController;

class BooksController extends SionController
{
    public function __construct()
    {
        return parent::__construct('book');
    }

    public function showAction()
    {
        $view = parent::showAction();
        /** @var \Books\Model\LibraryTable $table */
        $table = $this->getSionTable();
        $entity = $table->getBook($view->getVariable('entityId'));
        $view->setVariable('entity', $entity);
        return $view;
    }
}
<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use BjyAuthorize\Exception\UnAuthorizedException;

class BooksController extends SionController
{
    public function __construct()
    {
        return parent::__construct('book');
    }

    public function createAction()
    {
        $resourceId = 'library_'.$this->params ()->fromRoute ( 'library_id' );
        if (!$this->isAllowed($resourceId, 'administrate')) {
            throw new UnAuthorizedException();
        }

        $view = parent::createAction();
        return $view;
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

    public function searchAction()
    {

    }
}
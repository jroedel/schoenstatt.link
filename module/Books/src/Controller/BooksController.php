<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use BjyAuthorize\Exception\UnAuthorizedException;
use Books\Model\PublicationsTable;

class BooksController extends SionController
{
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
        if (isset($entity['currentCheckout'])) {
            $borrowers = $this->services['Books\BorrowersValueOptions'];
            $view->setVariable('borrowers', $borrowers);
        }
        if (isset($entity['publicationId'])) {
            /** @var \Books\Model\PublicationsTable $pubTable */
            $pubTable = $this->services[PublicationsTable::class];
            $publication = $pubTable->getPublication($entity['publicationId']);
            $entity['publication'] = $publication;
        }
        $view->setVariable('entity', $entity);
        return $view;
    }

    public function searchAction()
    {

    }
}
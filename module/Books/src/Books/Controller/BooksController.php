<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use BjyAuthorize\Exception\UnAuthorizedException;
use Books\Model\PublicationsTable;

class BooksController extends SionController
{
    /**
     * @var PublicationsTable $publicationsTable
     */
    protected $publicationsTable;
    
    /**
     * @var array $borrowersValueOptions
     */
    protected $borrowersValueOptions;
    
    public function __construct(PublicationsTable $publicationsTable, array $borrowersValueOptions)
    {
        $this->publicationsTable = $publicationsTable;
        //@todo confirm that the value options are being brought in, we'll probably need a factory for this
        $this->borrowersValueOptions = $borrowersValueOptions;
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
        if (isset($entity['currentCheckout'])) {
            $borrowers = $this->borrowersValueOptions;
            $view->setVariable('borrowers', $borrowers);
        }
        if (isset($entity['publicationId'])) {
            /** @var \Books\Model\PublicationsTable $pubTable */
            $pubTable = $this->publicationsTable;
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
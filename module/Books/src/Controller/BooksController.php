<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use BjyAuthorize\Exception\UnAuthorizedException;
use Books\Model\PublicationsTable;
use Books\Model\LibraryTable;

class BooksController extends SionController
{
    public function createAction()
    {
        $resourceId = 'library_'.$this->params()->fromRoute('library_id');
        if (!$this->isAllowed($resourceId, 'administrate')) {
            throw new UnAuthorizedException();
        }
        $view = parent::createAction();
        if ($view instanceof \Zend\Stdlib\ResponseInterface) {
            return $view;
        }

        $request = $this->getRequest();
        if ($request->isGet()) {
            $copyBook = $this->params()->fromQuery('copyBook');
            if (isset($copyBook) && is_numeric($copyBook)) {
                /** @var \Books\Model\LibraryTable $table */
                $table = $this->services[LibraryTable::class];
                if ($table->existsEntity('book', $copyBook)) {
                    $copyBookObj = $table->getSimpleBook($copyBook);
                    //allow copying books from other libraries as long as the user has show permissions
                    if ($this->isAllowed('library_'.$copyBookObj['libraryId'], 'show')) {
                        $form = $view->getVariable('form');
                        $form->get('title')->setValue($copyBookObj['title']);
                        $form->get('authors')->setValue($copyBookObj['authors']);
                        if (isset($copyBookObj['newCallNumber'])) {
                            $form->get('newCallNumber')->setValue($copyBookObj['newCallNumber']);
                        } else {
                            $form->get('callNumber')->setValue($copyBookObj['callNumber']);
                        }
                        //only copy the collectionId if they're in the same library
                        if ($copyBookObj['libraryId'] == $this->params()->fromRoute('library_id')) {
                            $form->get('collectionId')->setValue($copyBookObj['collectionId']);
                        }
                        $form->get('bookEdition')->setValue($copyBookObj['bookEdition']);
                        $form->get('inLanguage')->setValue($copyBookObj['inLanguage']);
                        $form->get('publicationId')->setValue($copyBookObj['publicationId']);
                        $form->get('category')->setValue($copyBookObj['category']);
                        $form->get('isbn')->setValue($copyBookObj['isbn']);
                        $form->get('numberOfPages')->setValue($copyBookObj['numberOfPages']);
                        $form->get('copyrightYear')->setValue($copyBookObj['copyrightYear']);
                        $form->get('publisher')->setValue($copyBookObj['publisher']);
                        $form->get('publishingPlace')->setValue($copyBookObj['publishingPlace']);
                        $form->get('keywords')->setValue($copyBookObj['keywords']);
                        $form->get('publicNotes')->setValue($copyBookObj['publicNotes']);
                        $form->get('adminNotes')->setValue($copyBookObj['adminNotes']);
                        $form->get('adminTags')->setValue($copyBookObj['adminTags']);
                    }
                }
            }
        }
        return $view;
    }

    public function showAction()
    {
        $view = parent::showAction();
        if ($view instanceof \Zend\Stdlib\ResponseInterface) {
            return $view;
        }
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

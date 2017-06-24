<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Zend\Mvc\Controller\Plugin\FlashMessenger;
use Books\Model\LibraryTable;
use Zend\View\Model\ViewModel;
use JTranslate\Controller\Plugin\NowMessenger;

class CheckoutsController extends SionController
{
    public function __construct()
    {
        return parent::__construct('checkout');
    }

    public function createAction()
    {
        $view = parent::createAction();
        if (!is_null($view)) {
            //@todo In the SionController function, I should automatically retrieve all route params and add them to the view
            $libraryId = $this->params()->fromRoute('library_id');
            $view->setVariable('libraryId', $libraryId);
            return $view;
        }
    }

    /**
     * This function will be called by the SionController::createAction and passed
     * prevalidated data from CheckoutForm
     * Foreach book:
     *  1. Check each book to make sure it exists, and if one or more aren't available, warn user
     *  2. If it's checked out, check it in first
     *  3. If it's still not available, don't do anything.
     * @param mixed[] $data
     */
    public function createCheckouts($data)
    {
        $table = $this->getSionTable();
        $bookLookup = $table->getActiveLibraryBookLookup();
        $badValues = [];
        foreach ($data['bookIds'] as $value) {
            if (!key_exists($value, $bookLookup)) {
                $badValues[] = $value;
                continue;
            }
        }
        if (!empty($badValues)) {
            $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )
                ->addMessage ('The following book id\'s are invalid: '.implode(', ', $badValues).' Please try again.');
            return;
        }

        //first check-in each of the books we're about to checkout
        $table->checkinBooks($data['bookIds']);

        foreach ($data['bookIds'] as $bookId) {
            $currentBook = $data;
            $currentBook['bookId'] = $bookLookup[$bookId];
            if (!$newId = $table->createEntity('checkout', $currentBook))
            {
                $badValues[] = $bookId;
            }
        }
        if (!empty($badValues)) {
            $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )
            ->addMessage (sprintf("There was a problem checking out one of the books: (%s) Any other books have been checked out. Please try again.",
                implode(', ', $badValues)));
            return;
        }

        //if all went well redirect to the borrower's page
        $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )
            ->addMessage ('Books successfully checked out.' );
        $id = ( int ) $this->params ()->fromRoute ( 'library_id' );
        $this->redirect()->toRoute('borrowers/borrower', ['person_id' => $data['personId']]);
    }

    public function libraryAction()
    {
        //get the parameter
        $id = ( int ) $this->params ()->fromRoute ( 'library_id' );
        if (!$id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Library not found.');
            $this->redirect ()->toRoute ( 'libraries');
        }

        $sm = $this->getServiceLocator();

        //get the checkouts
        /** @var LibraryTable $table */
        $table = $sm->get('Books\Model\LibraryTable');
        $entities = $table->getCheckoutsForLibrary($id);
        $library = $table->getLibrary($id);

        /** @var PatresTable $table */
        $patresTable = $sm->get('Patres\Model\PatresTable');
        $persons = $patresTable->getPersons();

        return new ViewModel([
            'library'   => $library,
            'entities'  => $entities,
            'persons'   => $persons,
        ]);
    }

    public function checkinAction()
    {
        //get the parameter
        $id = ( int ) $this->params ()->fromRoute ( 'library_id' );
        if (!$id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Library not found.');
            $this->redirect ()->toRoute ( 'libraries');
        }

        $sm = $this->getServiceLocator();
        $form = $sm->get('Books\Form\CheckinForm');

        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                $table = $this->getSionTable();
                if (!($return = $table->checkinBooks($data['bookIds']))) {
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                } else {
                    $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )
                        ->addMessage ('Books successfully checked in.' );
                    $this->redirect ()->toRoute ('libraries/library', ['library_id' => $id]);
                }
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        }
        return new ViewModel([
            'libraryId' => $id,
            'form'      => $form,
        ]);
    }
}
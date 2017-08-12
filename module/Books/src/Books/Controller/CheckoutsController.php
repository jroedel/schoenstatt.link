<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Zend\Mvc\Controller\Plugin\FlashMessenger;
use Books\Model\LibraryTable;
use Zend\View\Model\ViewModel;
use JTranslate\Controller\Plugin\NowMessenger;
use Books\Form\CheckinForm;
use Books\Form\MassCheckoutForm;
use Zend\Form\Element\Select;

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
        $id = $this->getLibraryId();
        /** @var LibraryTable $table */
        $table = $this->getSionTable();
        $bookLookup = $table->getLibraryBookLookup($id);
        $badValues = [];
        foreach ($data['withinLibraryIds'] as $value) {
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

        //@todo first we should make sure the person exists, and if not create him

        if (true !== $badValues = $table->checkoutWithinLibraryBooks($id, $data)) {
            $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )
            ->addMessage (sprintf("There was a problem checking out one of the books: (%s) Any other books have been checked out. Please try again.",
                implode(', ', $badValues)));
            return;
        }

        //if all went well redirect to the borrower's page
        $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )
            ->addMessage ('Books successfully checked out.' );
        $this->redirect()->toRoute('borrowers/borrower', ['person_id' => $data['personId']]);
    }

    public function libraryAction()
    {
        //get the parameter
        $id = $this->getLibraryId();

        $sm = $this->getServiceLocator();

        $subset = $this->params()->fromRoute('subset', 'all');
        //get the checkouts
        /** @var LibraryTable $table */
        $table = $sm->get('Books\Model\LibraryTable');
        $entities = $table->getCheckoutsForLibrary($id, $subset);
        $library = $table->getLibrary($id);

        /** @var PatresTable $table */
//         $patresTable = $sm->get('Schoenstatt\FathersValueOptions');
//         $persons = $patresTable->getPersons();
        $persons = $sm->get('Schoenstatt\FathersValueOptions');

        return new ViewModel([
            'library'   => $library,
            'entities'  => $entities,
            'persons'   => $persons,
        ]);
    }

    public function checkinAction()
    {
        //get the parameter
        $id = $this->getLibraryId();

        $sm = $this->getServiceLocator();
        $form = new CheckinForm();

        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                /** @var LibraryTable $table */
                $table = $this->getSionTable();
                if (!($return = $table->checkinWithinLibraryBooks($id, $data['withinLibraryIds']))) {
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                } else {
                    $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )
                        ->addMessage ('Books successfully checked in.' );
                    $this->redirect ()->toRoute ('checkouts/library', ['library_id' => $id]);
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

    public function massCheckoutAction()
    {
        //get the parameter
        $libraryId = $this->getLibraryId();

        $sm = $this->getServiceLocator();
        $form = new MassCheckoutForm();
        $personValueOptions = $sm->get('Books\FathersObjects');

        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            //add value options for validation purposes
            /** @var Select $personIdSelect */
            $personIdSelect = $form->get('checkout')->getTargetElement()->get('personId');
            $personIdSelect->setValueOptions($sm->get('Schoenstatt\FathersValueOptions'));
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                /** @var LibraryTable $table */
                $table = $this->getSionTable();
                $badValues = [];
                foreach ($data['checkout'] as $checkout) {
                    //only look over a checkout record if it has both books and a personId defined
                    if (is_null($checkout['personId']) || empty($checkout['withinLibraryIds'])) {
                        continue;
                    }
                    if (true !== ($return = $table->checkoutWithinLibraryBooks($libraryId, $checkout))) {
                        $badValues = array_merge($badValues, $return);
                    }
                }
                if (!empty($badValues)) {
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )
                        ->addMessage (sprintf("There was a problem checking out one or more of the books: (%s) Any other books have been checked out. Please try again.",
                            implode(', ', $badValues)));
                } else {
                    $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )
                    ->addMessage ('Books successfully checked out.' );
                    $this->redirect ()->toRoute ('checkouts/library/current', ['library_id' => $libraryId]);
                }
            } else {
                //eliminate value options to use the javascript options; this reduces page size
                $personIdSelect->setValueOptions([]);
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        }
        return new ViewModel([
            'libraryId'             => $libraryId,
            'form'                  => $form,
            'personValueOptions'    => $personValueOptions,
        ]);
    }

    /**
     * Get the library id. If not found, send the user back to the libraries index and give them a flash message
     * @return number
     */
    protected function getLibraryId()
    {
        $id = ( int ) $this->params ()->fromRoute ( 'library_id' );
        if (!$id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Library not found.');
            $this->redirect ()->toRoute ( 'libraries');
        }
        return $id;
    }
}
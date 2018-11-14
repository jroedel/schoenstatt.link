<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Zend\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Books\Model\LibraryTable;
use Zend\View\Model\ViewModel;
use JTranslate\Controller\Plugin\NowMessenger;
use Books\Form\CheckinForm;
use Books\Form\MassCheckoutForm;
use Zend\Form\Element\Select;
use Books\Model\LibraryOptions;
use Schoenstatt\Service\PatresGateway;
use Schoenstatt\Model\SchoenstattTable;
use BjyAuthorize\Exception\UnAuthorizedException;

class CheckoutsController extends SionController
{
    public function createAction()
    {
        $libraryId = $this->getLibraryId();
        $resourceId = 'library_'.$libraryId;
        if (!$this->isAllowed($resourceId, 'checkout')) {
            throw new UnAuthorizedException();
        }
        $view = parent::createAction();
        if ($view instanceof \Zend\Stdlib\ResponseInterface) {
            return $view;
        }
        if (isset($view)) {
            //@todo In the SionController function, I should automatically retrieve all route params and add them to the view
            $view->setVariable('libraryId', $libraryId);
            return $view;
        }
    }

    /**
     * This function will be called by the SionController::createAction and passed
     * already validated data from CheckoutForm
     * Foreach book:
     *  1. Check each book to make sure it exists, and if one or more aren't available, warn user
     *  2. If it's checked out, check it in first
     *  3. If it's still not available, don't do anything.
     * @param mixed[] $data
     */
    public function createCheckouts($data)
    {
        $libraryId = $this->getLibraryId();

        /** @var LibraryTable $table */
        $table = $this->getSionTable();
        $bookLookup = $table->getLibraryBookLookup($libraryId);
        $badValues = [];
        foreach ($data['withinLibraryIds'] as $value) {
            if (!key_exists($value, $bookLookup)) {
                $badValues[] = $value;
                continue;
            }
        }
        if (!empty($badValues)) {
            $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)
                ->addMessage('The following book id\'s are invalid: '.implode(', ', $badValues).' Please try again.');
            return;
        }

        $library = $table->getLibrary($libraryId);
        /** @var LibraryOptions $libraryOptions */
        $libraryOptions = $library['options'];
        if ($libraryOptions->checkoutPersonListKind == 'patres-sion') {
            //@todo first we should make sure the person exists, and if not create him
            /** @var PatresGateway $patresGateway */
            $patresGateway = $this->services[PatresGateway::class];
            if (false === $personData = $patresGateway->getSchoenstattPersonFromPatresPersonId($data['personId'], true, ['isBorrower' => true])) {
                throw new \Exception('The person selected was not found.');
            }
            $data['personId'] = $personData['personId'];
        }

        //checkout books
        if (true !== $badValues = $table->checkoutWithinLibraryBooks($libraryId, $data)) {
            $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)
            ->addMessage(sprintf(
                "There was a problem checking out one of the books: (%s) Any other books have been checked out. Please try again.",
                implode(', ', $badValues)
            ));
            return;
        }

        //if all went well redirect to the borrower's page
        $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
            ->addMessage('Books successfully checked out.');
        $this->redirect()->toRoute('borrowers/borrower', ['person_id' => $data['personId']]);
    }

    public function libraryAction()
    {
        //get the parameter
        $libraryId = $this->getLibraryId();
        $resourceId = 'library_'.$libraryId;
        if (!$this->isAllowed($resourceId, 'administrate')) {
            throw new UnAuthorizedException();
        }

        $subset = $this->params()->fromRoute('subset', 'all');
        //get the checkouts
        /** @var LibraryTable $table */
        $table = $this->getSionTable();
        $entities = $table->getCheckoutsForLibrary($libraryId, $subset);
        $library = $table->getLibrary($libraryId);

        /** @var SchoenstattTable $schTable */
        $schTable = $this->services[SchoenstattTable::class];
        $persons = $schTable->getPersons();

        return new ViewModel([
            'library'   => $library,
            'subset'    => $subset,
            'entities'  => $entities,
            'persons'   => $persons,
        ]);
    }

    public function checkinAction()
    {
        //get the parameter
        $libraryId = $this->getLibraryId();
        $resourceId = 'library_'.$libraryId;
        if (!$this->isAllowed($resourceId, 'administrate')) {
            throw new UnAuthorizedException();
        }

        $form = new CheckinForm();

        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost()->toArray();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                /** @var LibraryTable $table */
                $table = $this->getSionTable();
                if (!($return = $table->checkinWithinLibraryBooks($libraryId, $data['withinLibraryIds']))) {
                    $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)->addMessage('Error in form submission, please review.');
                } else {
                    $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                        ->addMessage('Books successfully checked in.');
                    $this->redirect()->toRoute('checkouts/library', ['library_id' => $libraryId]);
                }
            } else {
                $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)->addMessage('Error in form submission, please review.');
            }
        }
        return new ViewModel([
            'libraryId' => $libraryId,
            'form'      => $form,
        ]);
    }

    public function massCheckoutAction()
    {
        //get the parameter
        $libraryId = $this->getLibraryId();
        $resourceId = 'library_'.$libraryId;
        if (!$this->isAllowed($resourceId, 'administrate')) {
            throw new UnAuthorizedException();
        }

        $form = new MassCheckoutForm();
        $personValueOptions = $this->services['Books\FathersObjects'];

        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost()->toArray();
            //add value options for validation purposes
            /** @var Select $personIdSelect */
            $personIdSelect = $form->get('checkout')->getTargetElement()->get('personId');
            $personIdSelect->setValueOptions($this->services['Schoenstatt\FathersValueOptions']);
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
                    $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)
                        ->addMessage(sprintf(
                            "There was a problem checking out one or more of the books: (%s) Any other books have been checked out. Please try again.",
                            implode(', ', $badValues)
                        ));
                } else {
                    $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                    ->addMessage('Books successfully checked out.');
                    $this->redirect()->toRoute('checkouts/library/current', ['library_id' => $libraryId]);
                }
            } else {
                //eliminate value options to use the javascript options; this reduces page size
                $personIdSelect->setValueOptions([]);
                $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)->addMessage('Error in form submission, please review.');
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
        $id = ( int ) $this->params()->fromRoute('library_id');
        if (!$id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Library not found.');
            $this->redirect()->toRoute('libraries');
        }
        return $id;
    }
}

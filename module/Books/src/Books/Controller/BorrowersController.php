<?php
namespace Books\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Books\Model\LibraryTable;
use Zend\View\Model\ViewModel;

class BorrowersController extends AbstractActionController
{
    /**
     * @todo start using the person table to look up person information. The same person
     *      should be able to be used as an author, movement role, or a borrower
     * @return \Zend\View\Model\ViewModel
     */
    public function showAction()
    {
        //get the parameter
        $id = ( int ) $this->params ()->fromRoute ( 'person_id' );
        if (!$id) {
            $this->getOutOfHere();
        }

        $sm = $this->getServiceLocator();

        $persons = $sm->get ( 'Schoenstatt\FathersValueOptions' );
        if (!key_exists($id, $persons)) {
            $this->getOutOfHere();
        }
        //get the checkouts
        /** @var LibraryTable $table */
        $table = $sm->get('Books\Model\LibraryTable');
        $entities = $table->getCheckoutsForPerson($id);

        if (empty($entities)) {
            $this->getOutOfHere();
        }

        $person = [
            'fullFriendlyName' => $persons[$id],
        ];

        return new ViewModel([
            'entities'  => $entities,
            'person'    => $person,
        ]);
    }

    protected function getOutOfHere()
    {
        $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
        ->addMessage('Person not found.');
        $this->redirect ()->toRoute ( 'libraries');
    }
}
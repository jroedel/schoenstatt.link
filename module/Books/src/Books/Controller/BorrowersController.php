<?php
namespace Books\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Patres\Model\PatresTable;
use Books\Model\LibraryTable;
use Zend\View\Model\ViewModel;

class BorrowersController extends AbstractActionController
{
    public function showAction()
    {
        //get the parameter
        $id = ( int ) $this->params ()->fromRoute ( 'person_id' );
        if (!$id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Person not found.');
            $this->redirect ()->toRoute ( 'libraries');
        }

        $sm = $this->getServiceLocator();

        //get the checkouts
        /** @var LibraryTable $table */
        $table = $sm->get('Library\Model\LibraryTable');
        $entities = $table->getCheckoutsForPerson($id);

        //@todo factor this out
        /** @var PatresTable $table */
        $patresTable = $sm->get('Patres\Model\PatresTable');
        $person = $patresTable->getPerson($id);

        return new ViewModel([
            'entities'  => $entities,
            'person'    => $person,
        ]);
    }
}
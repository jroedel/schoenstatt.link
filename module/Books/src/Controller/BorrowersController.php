<?php
namespace Books\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Books\Model\LibraryTable;
use Laminas\View\Model\ViewModel;
use Schoenstatt\Model\SchoenstattTable;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;

class BorrowersController extends AbstractActionController
{
    /** @var SchoenstattTable $schoenstattTable */
    protected $schoenstattTable;

    /** @var LibraryTable $libraryTable */
    protected $libraryTable;

    public function __construct(SchoenstattTable $schoenstattTable, LibraryTable $libraryTable)
    {
        $this->schoenstattTable = $schoenstattTable;
        $this->libraryTable = $libraryTable;
    }

    /**
     * @todo start using the person table to look up person information. The same person
     *      should be able to be used as an author, movement role, or a borrower
     * @return \Laminas\View\Model\ViewModel
     */
    public function showAction()
    {
        //get the parameter
        $id = (int) $this->params()->fromRoute('person_id');
        if (! $id) {
            $this->getOutOfHere();
        }

        /** @var SchoenstattTable $schTable */
        $schTable = $this->schoenstattTable;
        $person = $schTable->getSimplePerson($id);

        //get the checkouts
        /** @var LibraryTable $table */
        $table = $this->libraryTable;
        $entities = $table->getCheckoutsForPerson($id);

        if (empty($entities)) {
            $this->getOutOfHere();
        }

        return new ViewModel([
            'entities'  => $entities,
            'person'    => $person,
        ]);
    }

    protected function getOutOfHere()
    {
        $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
        ->addMessage('Person not found.');
        return $this->redirect()->toRoute('libraries');
    }
}

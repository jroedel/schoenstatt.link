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
            //`return` was missing: the redirect was built and thrown away, so a request
            //with no person_id carried on into the rest of the action with $id = 0.
            return $this->getOutOfHere();
        }

        /** @var SchoenstattTable $schTable */
        $schTable = $this->schoenstattTable;
        $person = $schTable->getSimplePerson($id);

        //get the checkouts
        /** @var LibraryTable $table */
        $table = $this->libraryTable;
        $entities = $table->getCheckoutsForPerson($id);

        //Show only the libraries this viewer actually administrates.
        //
        //The route guard says roles => ['lib_user'], which reads like a restriction and
        //is not one: lib_user is is_default = 1, so it is granted to every authenticated
        //account and the guard means no more than "signed in". Until this filter, any
        //account holder could read any borrower's complete loan history across every
        //library on the site — a person's reading, which is not public information.
        //
        //The per-library resource is the real permission, and it is what the rest of the
        //module already uses (CheckoutsController, LibrariesController::sendBookNotices).
        //Filtering rather than refusing outright is deliberate: a librarian responsible
        //for one library should see that library's loans on this page, not a 403 caused
        //by a book the person borrowed somewhere else.
        //
        //Borrowers themselves do not come here at all any more. They get a scoped link to
        ///library/my-books, which needs no account — see Books\Model\BorrowerTokenTable.
        if (is_array($entities)) {
            foreach ($entities as $checkoutId => $checkout) {
                $libraryId = $checkout['book']['libraryId'] ?? null;
                if (null === $libraryId || ! $this->isAllowed('library_' . $libraryId, 'administrate')) {
                    unset($entities[$checkoutId]);
                }
            }
        }

        if (empty($entities)) {
            //Nothing this viewer may see, whether because the person has no loans or
            //because none of them are theirs to look at. One answer for both, so the
            //page cannot be used to discover which libraries a stranger borrows from.
            return $this->getOutOfHere();
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

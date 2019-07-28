<?php
namespace Books\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Books\Model\LibraryTable;
use Zend\View\Model\ViewModel;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Service\PatresGateway;
use Carbon\Carbon;
use Zend\Mvc\Plugin\FlashMessenger\FlashMessenger;

class BorrowersController extends AbstractActionController
{
    /** @var SchoenstattTable $schoenstattTable */
    protected $schoenstattTable;
    
    /** @var LibraryTable $libraryTable */
    protected $libraryTable;
    
    /** @var PatresGateway $patresGateway */
    protected $patresGateway;
    
    public function __construct(SchoenstattTable $schoenstattTable, LibraryTable $libraryTable, PatresGateway $patresGateway)
    {
        $this->schoenstattTable = $schoenstattTable;
        $this->libraryTable = $libraryTable;
        $this->patresGateway = $patresGateway;
    }
    
    /**
     * @todo start using the person table to look up person information. The same person
     *      should be able to be used as an author, movement role, or a borrower
     * @return \Zend\View\Model\ViewModel
     */
    public function showAction()
    {
        //get the parameter
        $id = ( int ) $this->params()->fromRoute('person_id');
        if (!$id) {
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

    /**
     * At the beginning of the project, we were using the Patres personId instead of the
     * SchoenstattTable personId, this action fixes those Ids.
     * @return \Zend\View\Model\ViewModel
     */
    public function fixPersonIdAction()
    {
        //already fixed, we will disable to prevent problems
        return;
        /** @var SchoenstattTable $schTable */
        $schTable = $this->schoenstattTable;
        /** @var PatresGateway $gateway */
        $gateway = $this->patresGateway;
        /** @var LibraryTable $table */
        $table = $this->libraryTable;

        $lastCheckoutIdToUpdate = 148;
        $limitDate = Carbon::create(2017, 8, 14, 14, 00, 00);

        $changes = [];
        $checkouts = $table->getCheckouts();
        $patresPersons = $gateway->getPersonList();
        $schPersons = $schTable->getUnlinkedPersons();
        $patresToSchPersonMap = [];
        foreach ($schPersons as $schPersonId => $schPerson) {
            if ($schPerson['dataSource'] == 'patres-sion' && is_numeric($schPerson['dataSourceId']) &&
                key_exists($schPerson['dataSourceId'], $patresPersons)
            ) {
                $patresToSchPersonMap[$schPerson['dataSourceId']] = $schPersonId;
            }
        }
        foreach ($checkouts as $checkoutId => $checkout) {
            if ($checkoutId > $lastCheckoutIdToUpdate || $checkout['updatedOn'] > $limitDate) {
                continue;
            }
            if (is_null($checkout['personId'])) {
//                 $schTable->deleteEntity('checkout', $checkoutId);
//                 var_dump($checkout);
                continue;
            }
            if (!key_exists($checkout['personId'], $patresToSchPersonMap)) {
                if (false === $schPersonId = $gateway->importRemotePerson($checkout['personId'], true, ['isBorrower' => true])) {
//                     var_dump("Error importing $checkoutId");
                    continue;
                }
                $patresToSchPersonMap[$checkout['personId']] = $schPersonId;
            }
            $newPersonId = $patresToSchPersonMap[$checkout['personId']];
            $changes[$checkoutId] = [
                'oldPersonId' => $checkout['personId'],
                'newPersonId' => $newPersonId,
                'result' => $table->updateEntity('checkout', $checkoutId, [
                    'personId' => $newPersonId,
                ], [], false),
            ];
        }
        return new ViewModel([
            'changes' => $changes,
        ]);
    }

    protected function getOutOfHere()
    {
        $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
        ->addMessage('Person not found.');
        return $this->redirect()->toRoute('libraries');
    }
}

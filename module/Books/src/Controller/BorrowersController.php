<?php
namespace Books\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Books\Model\LibraryTable;
use Zend\View\Model\ViewModel;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Service\PatresGateway;
use Carbon\Carbon;

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
        /** @var SchoenstattTable $schTable */
        $schTable = $sm->get('Schoenstatt\Model\SchoenstattTable');
        $person = $schTable->getSimplePerson($id);

        //get the checkouts
        /** @var LibraryTable $table */
        $table = $sm->get('Books\Model\LibraryTable');
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
        $sm = $this->getServiceLocator();
        /** @var SchoenstattTable $schTable */
        $schTable = $sm->get('Schoenstatt\Model\SchoenstattTable');
        /** @var PatresGateway $gateway */
        $gateway = $sm->get('PatresGateway');
        /** @var LibraryTable $table */
        $table = $sm->get('Books\Model\LibraryTable');

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
                var_dump($checkout);
                continue;
            }
            if (!key_exists($checkout['personId'], $patresToSchPersonMap)) {
                if (false === $schPersonId = $gateway->importRemotePerson($checkout['personId'], true, ['isBorrower' => true])) {
                    var_dump("Error importing $checkoutId");
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
        $this->redirect ()->toRoute ( 'libraries');
    }
}
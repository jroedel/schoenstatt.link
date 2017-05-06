<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Schoenstatt\Controller;

use Zend\Mvc\Controller\Plugin\FlashMessenger;

use Schoenstatt\Model\SchoenstattTable;
use JTranslate\Controller\Plugin\NowMessenger;
use Zend\View\Model\JsonModel;
use SionModel\Controller\SionController;

class AssociationsController extends SionController
{
    public function __construct()
    {
        parent::__construct('association');
    }

    public function showAction()
    {
        $view = parent::showAction();
        //set nationalOrganizations
        $association = $view->getVariable('entity');
        if ($association['kind'] == 'sch-national-movement' && !is_null($association['country'])) {
            $table = $this->getSionTable();
            $nationalOrganizations = $table->getNationalAssociations($association['country']);
            if (key_exists($association['associationId'], $nationalOrganizations)) {
                unset($nationalOrganizations[$association['associationId']]);
            }
            $association['nationalOrganizations'] = $nationalOrganizations;
            $view->setVariable('entity', $association);
        }

        return $view;
    }

    /**
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::createAction()
     */
    public function createAction()
    {
        $view = parent::createAction();
        //check if we were passed a valid country param and set it in the form
        $countryParam = $this->params()->fromQuery('country');
        if (!$this->getRequest()->isPost() && !is_null($countryParam) &&
            is_string($countryParam) && strlen($countryParam) == 2
        ) {
            $form = $view->getVariable('form');
            $countries = $form->get('country')->getValueOptions();
            if  (key_exists($countryParam, $countries)) {
                $form->get('country')->setValue($countryParam);
                $view->setVariable('form', $form);
            }
        }
        return $view;
    }

    public function createAssociation($data)
    {
        $entity = $this->getEntity();
        $table = $this->getSionTable();
        if (!($newId = $table->createEntity($entity, $data))) {
            $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
        } else {
            //This is the most important:
            $table->createAssociatedRoles($newId, $data['kind']);
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )
            ->addMessage ( ucwords($entity).' successfully created.' );
            $this->redirectAfterCreate((int) $newId);
        }
    }

    public function importAction()
    {
        return; //disable to prevent duplicate records being inserted
        $toImport = [
            ['name' => 'Schoenstatt Movement of Argentina', 'country' => 'AR', 'publicNotes' => 'Formally constituted', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Australia', 'country' => 'AU', 'publicNotes' => null, 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Brazil', 'country' => 'BR', 'publicNotes' => 'Formally constituted', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Burundi', 'country' => 'BI', 'publicNotes' => null, 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Chile', 'country' => 'CL', 'publicNotes' => 'Formally constituted', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Germany', 'country' => 'DE', 'publicNotes' => 'Formally constituted', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Ecuador', 'country' => 'EC', 'publicNotes' => null, 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of India-Kerala', 'country' => 'IN', 'publicNotes' => 'No national presidium', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of India-Tamil Nadu', 'country' => 'IN', 'publicNotes' => 'No national presidium', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Mexico', 'country' => 'MX', 'publicNotes' => 'No national presidium', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Austria', 'country' => 'AT', 'publicNotes' => null, 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Paraguay', 'country' => 'PT', 'publicNotes' => null, 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Poland', 'country' => 'PL', 'publicNotes' => null, 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Portugal', 'country' => 'PT', 'publicNotes' => 'National presidium works, but is not yet approved by the general presidium', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Switzerland', 'country' => 'CH', 'publicNotes' => 'Formally constituted', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Spain', 'country' => 'ES', 'publicNotes' => null, 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Czech Republic', 'country' => 'CZ', 'publicNotes' => null, 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of USA-North', 'country' => 'US', 'publicNotes' => 'A regional presidium', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Colombia', 'country' => 'CO', 'publicNotes' => null, 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Cuba', 'country' => 'CU', 'publicNotes' => null, 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Peru', 'country' => 'PE', 'publicNotes' => null, 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of South Africa', 'country' => 'ZA', 'publicNotes' => null, 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of USA-South', 'country' => 'US', 'publicNotes' => null, 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of England', 'country' => 'GB', 'publicNotes' => null, 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Italy', 'country' => 'IT', 'publicNotes' => 'Not official, it was a creation through the general presidium, that hasn\'t continued since the time of Fr. Ludovico Tedeschi.', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Croatia', 'country' => 'HR', 'publicNotes' => null, 'kind' => 'sch-national-movement',],
        ];

        $sm = $this->getServiceLocator ();
        /** @var SchoenstattTable $table */
        $table = $sm->get ( 'Schoenstatt\Model\SchoenstattTable' );
        foreach ($toImport as $data) {
            $newId = $table->createEntity('association', $data);
            $table->createAssociatedRoles($newId, $data['kind']);
        }
        return new JsonModel(['imported' => $toImport]);
    }
}

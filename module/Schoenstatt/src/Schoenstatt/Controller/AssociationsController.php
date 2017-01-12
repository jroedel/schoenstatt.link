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

use Zend\View\Model\ViewModel;
use Zend\Mvc\Controller\AbstractActionController;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Form\AssociationForm;
use JTranslate\Controller\Plugin\NowMessenger;

class AssociationsController extends AbstractActionController
{
    public function indexAction()
    {
        //heirarchize the array

        $sm = $this->getServiceLocator();
        /** @var SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
        $associations = $table->getAssociations();
        return new ViewModel([
            'entities'      => $associations,
        ]);
    }

    /**
     * @todo this
     * @param mixed[] $associations
     */
    protected function heirarchizeAssociations($associations)
    {

    }

    public function showAction()
    {
        $id = (Int)$this->params()->fromRoute('association_id');
        //var_dump($id);
        if (!$id) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Association not found.');
            return $this->redirect()->toRoute('associations');
        }
        $sm = $this->getServiceLocator();
        /** @var SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
        $association = $table->getAssociation($id);

//         $table->registerVisit(PatresTable::ENTITY_GENERATION, $id);

        return new ViewModel([
            'entity'        => $association,
//             'suggestForm'   => $sm->get('SionModel\Form\SuggestForm'),
        ]);
    }

    public function editAction()
    {
        $id = (Int)$this->params()->fromRoute('association_id');
        //var_dump($id);
        if (!$id) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Association not found.');
            return $this->redirect()->toRoute('associations');
        }
        $sm = $this->getServiceLocator();
        /** @var SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
        $association = $table->getAssociation($id);
        if (!$association) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Association not found.');
            return $this->redirect()->toRoute('associations');
        }

        $form = $sm->get('Schoenstatt\Form\AssociationForm');
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($data ['associationId'] != $id) { // make sure the user is trying to update the right event
                $this->flashMessenger()
                ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('Association not found.');
                return $this->redirect()->toRoute('associations');
            }
            if ($form->isValid()) {
                $data = $form->getData();
                try {
                    $result = $table->updateEntity('association', $id, $data);

                    $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Association successfully updated.' );
//                     $this->redirect()->toRoute ( 'associations/association', array('association_id' => $association['associationId']) );
                } catch (\Exception $e) {
                    //@todo this message should be logged
                    var_dump($e);
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.');
                }
            } else {
                    var_dump('invalidform');
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        } else {
            $form->setData($association);
        }
        return array (
            'form' => $form,
            'entity' => $association,
        );
    }

    public function createAction()
    {
        $sm = $this->getServiceLocator ();
        /** @var SchoenstattTable $table **/
        $table = $sm->get ( 'Schoenstatt\Model\SchoenstattTable' );

        /** @var AssociationForm $form */
        $form = $sm->get('Schoenstatt\Form\AssociationForm');
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                if (!($newId = $table->createEntity('association', $data))) {
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                } else {
                    $table->createAssociatedRoles($newId, $data['kind']);
                    $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Association successfully created.' );
                    $this->redirect ()->toRoute ( 'associations/association', array('association_id' => $newId) );
                }
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        }
        return [
            'form' => $form,
        ];
    }

    /**
     * @todo check resource-level permissions
     */
    public function deleteAction()
    {
        $request = $this->getRequest ();
        $sm = $this->getServiceLocator ();
        /** @var PatresTable $table */
        $table = $sm->get ( 'Schoenstatt\Model\SchoenstattTable' );
        $id = ( int ) $this->params ()->fromRoute ( 'living_situation_id' );
        $livingSituation = $table->getLivingSituation($id);
        if (is_null($livingSituation)) {
            $this->getResponse()->setStatusCode(401);
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )
            ->addMessage ( 'The living situation you\'re trying to delete doesn\'t exists. ' );
            $this->redirect()->toUrl($this->url()->fromRoute('home'));
        }
        $form = new DeleteLivingSituationForm();
        if ( $request->isPost ()) {
            $data = $request->getPost();
            $form->setData($data);
            if ($form->isValid() && $form->getData()['livingSituationId'] == $id) {
                $result = $table->deleteLivingSituation($id);
                $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )
                ->addMessage ( 'Living situation successfully deleted. '.$result );
                $this->redirect()->toUrl($this->url()->fromRoute('fathers/father',
                    ['person_id' => $livingSituation['personId']]));
            }
        } else {
            $form->setData($livingSituation);
        }

        return new ViewModel ([
            'form' => $form,
            'livingSituation' => $livingSituation,
            'livingSituationId' => $id,
        ] );
    }
}

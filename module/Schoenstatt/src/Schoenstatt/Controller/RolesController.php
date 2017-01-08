<?php
namespace Schoenstatt\Controller;

use Zend\Mvc\Controller\Plugin\FlashMessenger;
use Zend\View\Model\ViewModel;
use Zend\Mvc\Controller\AbstractActionController;
use Schoenstatt\Form\RoleForm;
use JTranslate\Controller\Plugin\NowMessenger;
use Schoenstatt\Model\SchoenstattTable;

class RolesController extends AbstractActionController
{
    public function indexAction()
    {
        $sm = $this->getServiceLocator();
        /** @var \Schoenstatt\Model\SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
        $entities = $table->getRoles();

        return new ViewModel([
            'entities' => $entities,
        ]);
    }

    public function createAction()
    {
        $sm = $this->getServiceLocator ();
        /** @var SchoenstattTable $table **/
        $table = $sm->get ( 'Schoenstatt\Model\SchoenstattTable' );

        /** @var RoleForm $form */
        $form = $sm->get('Schoenstatt\Form\RoleForm');
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                if (!($newId = $table->createEntity('role', $data))) {
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                } else {
                    $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Role successfully created.' );
//                     $this->redirect ()->toRoute ( 'roles/role', array('role_id' => $newId) );
                }
            } else {
                print_r(array_keys($form->getInputFilter()->getInvalidInput()));
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        }
        return [
            'form' => $form,
        ];
    }

    public function editAction()
    {
        $id = (Int)$this->params()->fromRoute('role_id');
        if (!$id) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Role not found.');
            return $this->redirect()->toRoute('roles');
        }
        $sm = $this->getServiceLocator();
        /** @var \Schoenstatt\Model\SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
        $entity = $table->getRole($id);
        if (!$entity) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Role not found.');
            return $this->redirect()->toRoute('roles');
        }

        /** @var RoleForm $form */
        $form = $sm->get('Schoenstatt\Form\RoleForm');
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($data ['roleId'] != $id) { // make sure the user is trying to update the right event
                $this->flashMessenger()
                    ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                    ->addMessage('Role not found.');
                return $this->redirect()->toRoute('roles');
            }
            if ($form->isValid()) {
                $data = $form->getData();
                $result = $table->updateEntity('role', $id, $data);
                $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Role successfully updated.' );
                $this->redirect()->toRoute ( 'roles' );
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        } else {
            $form->setData($entity);
        }
        return [
            'form' => $form,
            'entity' => $entity,
        ];
    }
}

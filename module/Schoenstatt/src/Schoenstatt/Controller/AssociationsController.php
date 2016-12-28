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
use Patres\Form\GenerationForm;
use Schoenstatt\Form\AssociationForm;
use JTranslate\Controller\Plugin\NowMessenger;

class AssociationsController extends AbstractActionController
{
    public function indexAction()
    {
        //heirarchize the array
        $associations = [
            1 => [
                'associationId'             => 1,
                'name'                      => 'Schoenstatt Fathers',
                'parent'                    => null,
                'heirarchyLevel'            => 1,
                'kind'                      => 'schoenstatt-institute',
                'isNameTranslateable'       => true,
                'isActive'                  => true,
            ],
            2 => [
                'associationId'             => 2,
                'name'                      => 'Schoenstatt Movement of USA',
                'parent'                    => null,
                'heirarchyLevel'            => 1,
                'kind'                      => 'Kind',
                'isNameTranslateable'       => true,
                'isActive'                  => true,
            ],
            3 => [
                'associationId'             => 3,
                'name'                      => 'Schoenstatt Movement of Austin',
                'parent'                    => 2,
                'heirarchyLevel'            => 2,
                'kind'                      => 'sch-diocesan-movement',
                'isNameTranslateable'       => true,
                'isActive'                  => true,
            ],
        ];
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
            ->addMessage('Generation not found.');
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
        $id = (Int)$this->params()->fromRoute('generation_id');
        //var_dump($id);
        if (!$id) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Generation not found.');
            return $this->redirect()->toRoute('courses');
        }
        $sm = $this->getServiceLocator();
        /** @var SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
        $generation = $table->getSimpleGeneration($id);
        if (!$generation) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Generation not found.');
            return $this->redirect()->toRoute('courses');
        }

        $form = new GenerationForm();
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($data ['generationId'] != $id) { // make sure the user is trying to update the right event
                $this->flashMessenger()
                ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('Generation not found.');
                return $this->redirect()->toRoute('courses');
            }
            if ($form->isValid()) {
                $data = $form->getData();
                try {
                    $result = $table->updateEntity('generation', $id, $data);

                    $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Generation successfully updated.' );
                    $this->redirect()->toRoute ( 'generations/generation', array('generation_id' => $generation['generationId']) );
                } catch (\Exception $e) {
                    //@todo this message should be logged
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.');
                }
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        } else {
            $form->setData($generation);
        }
        return array (
            'form' => $form,
            'entity' => $generation,
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
                try {
                    if (!($newId = $table->createEntity('association', $data))) {
                        $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                    } else {
                        $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Association successfully created.' );
                        $this->redirect ()->toRoute ( 'associations/association', array('association_id' => $newId) );
                    }
                } catch (\Exception $e) {
                    //var_dump($e);
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                }
            } else {
                //var_dump($form);
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

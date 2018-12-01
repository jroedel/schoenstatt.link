<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Schoenstatt\Controller;

use Zend\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Zend\View\Model\ViewModel;
use Schoenstatt\Form\PersonForm;
use JTranslate\Controller\Plugin\NowMessenger;
use Schoenstatt\Form\SearchForm;
use SionModel\Controller\SionController;

class PersonsController extends SionController
{
    /**
     * @return \Zend\View\Model\ViewModel
     */
    public function searchAction()
    {
        //make sure we get clean parameters
        $params = $this->params()->fromQuery();
        /** @var SearchForm $form */
        $form = new SearchForm();
        $form->setData($params);
        $persons = null;
//         $showPhotos = false;
        if ($form->isValid()) {
            $data = $form->getData();
//             $showPhotos = $data['showPhotos'];
//             $data['exMembers'] = false;
//             unset($data['showPhotos']);
            if (!empty($data)) {
                /** @var \Schoenstatt\Model\SchoenstattTable $table */
                $table = $this->getSionTable();
                $persons = $table->searchPersons($data);
            }
        }
        if (is_array($persons) && empty($persons)) {
            $this->nowMessenger()->addMessage("No results found.", NowMessenger::NAMESPACE_INFO);
        }
        return new ViewModel([
            'persons'       => $persons,
            'form'          => $form,
        ]);
    }

    public function showAction()
    {
        $id = (Int)$this->params()->fromRoute('person_id');
        //var_dump($id);
        if (!$id) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Person not found.');
            return $this->redirect()->toRoute('persons');
        }
        /** @var \Schoenstatt\Model\SchoenstattTable $table */
        $table = $this->getSionTable();
        $person = $table->getPerson($id);
        if (!$person) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Person not found.');
            return $this->redirect()->toRoute('persons');
        }

        /** @var MobileDetect $mobileDetect */
//         $mobileDetect = $this->mobileDetect(); //Retrieve "\Mobile_Detect" object

//         $deviceType = $mobileDetect->isAndroidOS() ? 'android' :
//             $mobileDetect->isiOS() ? 'ios' : 'default'; //android, ios, default
        $deviceType = 'default';
        $this->addUserNamesToUrlList($person, $deviceType); //$person is ByRef

        $table->registerVisit('person', $person['personId']);
        return new ViewModel([
            'person'        => $person,
            'deviceType'    => $deviceType,
//             'suggestForm'   => $sm->get('Schoenstatt\Form\SuggestForm'),
        ]);
    }

    /**
     * At this point, the form has been validated, but we want to make sure they set
     * either the first or last name. If not, send the user the form back.
     * @param mixed[] $data
     * @param PersonForm $form
     * @return \Zend\View\Model\ViewModel|null
     */
    public function createPerson($data, $form)
    {
        if (!$data['firstName'] && !$data['lastName']) {
            $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)
                ->addMessage('Either a first name or a last name is required.');
            return new ViewModel([
                'form' => $form,
            ]);
        }

        $table = $this->getSionTable();
        $entity = $this->getEntity();
        if (!($newId = $table->createEntity($entity, $data))) {
            $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)
                ->addMessage('Error in form submission, please review.');
        } else {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
            ->addMessage(ucwords($entity).' successfully created.');
            $this->redirectAfterCreate((int) $newId);
        }
    }

    /**
     * @todo DRY this up to SionModel
     *
     * @param mixed[] $person
     * @param string $deviceType
     */
    protected function addUserNamesToUrlList(&$person, $deviceType)
    {
        $config = $this->config;
        $urlConfig = $config['schoenstatt']['url_map'];

        //first check the existing urls to fill in logo info
        foreach ($person['urls'] as $urlsKey => $values) {
            if (isset($values['logo'])) {
                continue;
            }
            foreach ($urlConfig as $key => $configValues) {
                if ($values['label'] == $configValues['label'] &&
                    isset($configValues['logo'])
                ) {
                    $person['urls'][$urlsKey]['logo'] = $configValues['logo'];
                    break;
                }
            }
        }
        foreach ($urlConfig as $key => $values) {
            if (isset($values[$deviceType]) &&
                isset($values['userKey']) &&
                !is_null($person[$values['userKey']])) {
                $url = [
                    'label' => $values['label'],
                    'url'   => sprintf($values[$deviceType], $person[$values['userKey']]),
                ];
                if (isset($values['logo'])) {
                    $url['logo'] = $values['logo'];
                }
                $person['urls'][] = $url;
            }
        }
        return;
    }
}

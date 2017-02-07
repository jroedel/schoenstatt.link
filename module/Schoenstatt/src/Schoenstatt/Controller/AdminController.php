<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Schoenstatt\Controller;

use Zend\View\Model\ViewModel;
use Zend\Mvc\Controller\AbstractActionController;
use Patres\Form\ModerateGenericForm;
use JTranslate\Model\TranslationsTable;
use JTranslate\Controller\Plugin\NowMessenger;
use Patres\Mailing\Mailer;
use Schoenstatt\Form\PersonForm;
use Zend\Http\Client;
use Zend\Json\Json;
use Schoenstatt\Model\SchoenstattTable;

class AdminController extends AbstractActionController
{
    protected $personInputFilter;

    public function indexAction()
    {
        $pages = [
            'persons/create'            => "Add new person",
            'sion-model/view-changes'   => "View Changes",
            'admin/import-father'       => "Import Schoenstatt Father",
            'juser'                     => "User Management",
            'jtranslate'                => "Manage Translations",
            'sion-model/data-problems'  => "Data problems",
        ];
        $badges = [];
        $sm = $this->getServiceLocator();

        /** @var TranslationsTable $translations */
        $translations = $sm->get('JTranslate\Model\TranslationsTable');

        $badges['jtranslate'] = (string) $translations->getOutstandingTranslationCount();
        
        /** @var ProblemService $problemService */
        $problemService = $sm->get('SionModel\Service\ProblemService');
        $problems = $problemService->getCurrentProblems();
        $badges['sion-model/data-problems'] = count($problems);

        return new ViewModel([
            'pages' => $pages,
            'badges' => $badges,
        ]);
    }

    public function importFatherAction()
    {
        $sm = $this->getServiceLocator();
        $form = $sm->get('Schoenstatt\Form\ImportFatherForm');
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($form->isValid()) { //here the sent personId will be checked against the haystack
                /** @var \Schoenstatt\Model\SchoenstattTable $table */
                $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
                $personId = $form->getData()['personId'];
                $personData = $this->getPersonInfo($personId);
                $personData['dataSource'] = 'patres-sion';
                $personData['dataSourceId'] = $personId;
                unset($personData['personId']); //to make sure that we don't try setting that as the primary key
                if (0 !== count($table->searchPersons(['dataSource' => 'patres-sion', 'dataSourceId' => $personId], false, true)))
                {
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Person already exists in the database.' );
                } else {
                    $result = $table->createEntity('person', $personData);
                    //prime the form a new request
                    $form = $sm->get('Schoenstatt\Form\ImportFatherForm');
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Person successfully imported.' );
                }
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        }
        return new ViewModel([
            'form'  => $form,
        ]);
    }

    /**
     * Retrieve and validate person info from Patres database
     * @param unknown $id
     * @throws \Exception
     */
    protected function getPersonInfo($id)
    {
        $config = $this->getServiceLocator()->get('Schoenstatt\Config');
        if (!isset($config['patres_api_key'])) {
            throw new \Exception('No \'patres_api_key\' set to retrieve data from Patres Sion.');
        }
        $key = $config['patres_api_key'];
        $getPersonUrl = sprintf($config['patres_api_get_person_uri'], $id);
        $client = new Client();
        $client->setMethod('get');
        $client->setUri($getPersonUrl);
        $client->setParameterGet(['key' => $key]);
        $response = $client->send();

        if (200 != $response->getStatusCode()) {
            throw new \Exception('Request for information on father \''.$id.'\' failed. Status code: '.$response->getStatusCode());
        }
        $data = Json::decode($response->getBody(), Json::TYPE_ARRAY);
        if (!isset($data['data'])) {
            throw new \Exception('Request for information on father \''.$id.'\' failed. No information returned.');
        }
        $person = $data['data'];
        $person['personTags'] = 'priest';
        $person['lifeCommunity'] = 1;
        //validate
        $inputFilter = $this->getPersonInputFilter();
        $inputFilter->setData($person);
        return $inputFilter->getValues();
    }

    /**
     * Get a fresh InputFilter to test person data
     * @return \Zend\InputFilter\InputFilterInterface
     */
    protected function getPersonInputFilter()
    {
        if (is_null($this->personInputFilter)) {
            /**
             * @var PersonForm $form
             */
            $form = $this->getServiceLocator()->get('Schoenstatt\Form\PersonForm');
            $this->personInputFilter = $form->getInputFilter();
        }
        return clone $this->personInputFilter;
    }

    public function testEmailsAction()
    {
        return;
        $sm = $this->getServiceLocator();
        /** @var \Patres\Model\PatresTable $table */
        $table = $sm->get('Patres\Model\PatresTable');

        //send an email to admin
//         $suggestion = $table->getLastSuggestion();
        $suggestion = $table->getSuggestions()[12];
        /** @var Mailer $mailer **/
        $mailer = $sm->get('Patres\Mailing\Mailer');
        $mailer->sendReviewedSuggestionNotice($suggestion);
        return new ViewModel([
            'suggestion' => $suggestion,
        ]);
    }

    public function testSuggestionsAction()
    {
        $sm = $this->getServiceLocator();
        /** @var \Patres\Model\PatresTable $table */
        $table = $sm->get('Patres\Model\PatresTable');

        $suggestions = $table->getSuggestions();
        return new ViewModel([
            'suggestions' => $suggestions,
        ]);
    }

    public function reviewPhoneNumbersAction()
    {
        $sm = $this->getServiceLocator();
        /** @var \Patres\Model\PatresTable $table */
        $table = $sm->get('Patres\Model\PatresTable');
        $phones = $table->getAllPersonPhoneNumbers();

        return new ViewModel([
            'phones' => $phones,
        ]);
    }

    public function livingSituationBootstrapAction()
    {
        $sm = $this->getServiceLocator();
        /** @var \Patres\Model\PatresTable $table */
        $table = $sm->get('Patres\Model\PatresTable');
        $persons = $table->createFirstLivingSituations(true); //this must be hard-coded true to affect the db
        $columns = [
            'personId' => 'ID',
            'fullName' => 'Name',
            'responsibleTerritoryId' => 'Responsible Territory',
            'filiationId' => 'Filiation',
            'houseId' => 'House',
            'status' => 'Status',
            'persStatus' => 'PersStatus',
            'glSpez' => 'GlSpez',
            'gruppePers' => 'GruppePers'
        ];
        return new ViewModel([
            'persons' => $persons,
            'columns' => $columns,
        ]);
    }

    /**
     * @todo I'm not sure that this correctly handles DENY!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
     * @return \Zend\View\Model\ViewModel
     */
    public function moderateAction()
    {
        $sm = $this->getServiceLocator();
        /** @var \Patres\Model\PatresTable $table */
        $table = $sm->get('Patres\Model\PatresTable');

        $form = new ModerateGenericForm();
        $request = $this->getRequest();
        $continueModeration = false;
        if ($request->isPost()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                try {
                    $table->updateSuggestion($data);

                    //send an email to user
                    /** @var Mailer $mailer **/
                    $mailer = $sm->get('Patres\Mailing\Mailer');
                    $suggestion = $table->getSuggestion($data['suggestionId']);
                    $mailer->sendReviewedSuggestionNotice($suggestion);
                } catch (\Exception $e) {
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error updating suggestion.' );
                    $continueModeration = true;
                }
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error updating suggestion.' );
                $continueModeration = true;
            }
        }
        $suggestions = $table->getSuggestions();
        return new ViewModel([
            'suggestions' => $suggestions,
            'form' => $form,
            'continueModeration' => $continueModeration
        ]);
    }
}

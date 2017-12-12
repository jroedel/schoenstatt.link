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
use JTranslate\Model\TranslationsTable;
use JTranslate\Controller\Plugin\NowMessenger;
use Schoenstatt\Model\SchoenstattTable;
// use Patres\Form\ModerateGenericForm;
// use Patres\Mailing\Mailer;
// use Schoenstatt\Form\PersonForm;
// use Schoenstatt\Model\SchoenstattTable;

class AdminController extends AbstractActionController
{
    protected $personInputFilter;

    public function indexAction()
    {
        $pages = [
            'persons/create'            => "Add new person",
            'associations/create'       => "Add new association",
            'assignments/create'        => "Add new assignment",
            'roles/create'              => "Add new role",
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
                $personId = $form->getData()['personId'];
                /** @var \Schoenstatt\Service\PatresGateway $patresGateway */
                $patresGateway = $sm->get('PatresGateway');
                if (false === $patresGateway->importRemotePerson($personId))
                {
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Person already exists in the database.' );
                } else {
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

    public function maintenanceAction()
    {
        $sm = $this->getServiceLocator();
        /** @var SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');

        //remove erroneous priest tag from seminarians (so far, all priests should have priestDate)
        $persons = $table->getUnlinkedPersons();
        $simulate = (bool)$this->params()->fromQuery('simulate', true);
        $changes = [];
        foreach ($persons as $personId => $object) {
            $hasPriestTag = in_array('priest', $object['personTags']);
            if ($hasPriestTag && !isset($object['priestDate'])) {
                $personTags = $object['personTags'];
                foreach ($personTags as $key => $value) {
                    if ('priest' === $value) {
                        unset($personTags[$key]);
                        break;
                    }
                }
                if (!$simulate) {
                    $table->updateEntity('person', $personId, ['personTags'=>$personTags]);
                }
                $changes[] = $personId;
            }
        }

        return new ViewModel([
            'changes' => $changes,
            'simulate'  => $simulate,
        ]);
    }

}

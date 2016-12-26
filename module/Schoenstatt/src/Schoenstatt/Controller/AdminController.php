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
use SionModel\Service\ProblemService;

class AdminController extends AbstractActionController
{
    public function indexAction()
    {
        $pages = [
            'persons/create'        => "Add new person",
//             'admin/review-phone-numbers' => "Review phone numbers",
//             'roles'                 => "Manage Roles",
//             'admin/view-searches'   => "View Searches",
//             'admin/view-changes'    => "View Changes",
//             'samuser'               => "User Management",
//             'admin/moderate'        => "Review Suggestions",
//             'admin/fix-flags'       => "Fix Flag Problems",
            'jtranslate'            => "Manage Translations",
//             'admin/data-problems'   => "Data problems",
        ];
        $badges = [];
        $sm = $this->getServiceLocator();
        /** @var \Patres\Model\PatresTable $table */
//         $table = $sm->get('Schoenstatt\Model\SchoenstattTable');

        /** @var TranslationsTable $translations */
        $translations = $sm->get('JTranslate\Model\TranslationsTable');

//         $suggestionCount = $table->getSuggestionCount();
//         $badges['admin/moderate'] = $suggestionCount ? ' '.$suggestionCount : " 0";
//         $countries = $table->fixPersonCountries(true);
//         $badges['admin/fix-flags'] = !is_null($countries) ? ' '.count($countries) : " 0"; //circumvent TwbBundle problem
        $badges['jtranslate'] = (string) $translations->getOutstandingTranslationCount();

        return new ViewModel([
            'pages' => $pages,
            'badges' => $badges,
        ]);
    }

    /**
     * @todo Add a way to count the amount of errors
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function dataProblemsAction()
    {
        $sm = $this->getServiceLocator();
        /** @var ProblemService $table */
        $table = $sm->get('SionModel\Service\ProblemService');

        $problems = $table->getCurrentProblems();

        return new ViewModel([
            'problems' => $problems,
        ]);
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

    public function viewSearchesAction()
    {
        $sm = $this->getServiceLocator();
        /** @var \Patres\Model\PatresTable $table */
        $table = $sm->get('Patres\Model\PatresTable');
        $sql = "SELECT s . * , u.display_name, f.FilName, f.Country, k.KursName, g.GenName, h.HausName, c.LandName_en, geb.GebName
FROM  `a_data_searches` s
INNER JOIN user u ON u.user_id = s.search_user
LEFT JOIN a_data_filiale f ON f.FilID = s.search_filiation
LEFT JOIN a_data_kurs k ON k.KursID = s.search_course
LEFT JOIN a_data_generation g ON g.GenID = s.search_generation
LEFT JOIN a_data_haus h ON h.HausID = s.search_house
LEFT JOIN a_data_land c ON c.LandID = s.search_country
LEFT JOIN a_data_gebiet geb ON geb.GebID = s.search_territory
ORDER BY s.search_id DESC
LIMIT 150";
        $results = $table->fetchSome(null, $sql, null, true);

        return new ViewModel([
                'searches' => $results,
        ]);
    }

    /**
     * @todo Pass a lookup table that tells which columns can be replaced with which link entities
     * example 'noviceMaster' => 'person', 'novitiateFiliation' => 'filiation'
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function viewChangesAction()
    {
        $sm = $this->getServiceLocator();
        /** @var \Patres\Model\PatresTable $table */
        $table = $sm->get('Patres\Model\PatresTable');
        $results = $table->getChanges();

        return new ViewModel([
            'changes' => $results,
        ]);
    }

    public function fixFlagsAction()
    {
        $sm = $this->getServiceLocator();
        /** @var \Patres\Model\PatresTable $table */
        $table = $sm->get('Patres\Model\PatresTable');
        $request = $this->getRequest();
        $simulate = !$request->isPost();
        $changes = $table->fixPersonCountries($simulate);
        return new ViewModel([
            'simulated' => $simulate,
            'changes' => $changes,
        ]);
    }
}

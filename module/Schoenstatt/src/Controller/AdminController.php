<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Schoenstatt\Controller;

use Laminas\View\Model\ViewModel;
use Laminas\Mvc\Controller\AbstractActionController;
use JTranslate\Model\TranslationsTable;
use JTranslate\Controller\Plugin\NowMessenger;
use JTranslate\I18n\TranslatableMessage;
use Schoenstatt\Service\PatresGateway;
use SionModel\Service\ProblemService;
use Schoenstatt\Form\ImportFatherForm;

// use Patres\Form\ModerateGenericForm;
// use Patres\Mailing\Mailer;
// use Schoenstatt\Form\PersonForm;
// use Schoenstatt\Model\SchoenstattTable;

class AdminController extends AbstractActionController
{
    protected $personInputFilter;

    protected $translationsTable;
    protected $problemService;
    protected $importFatherForm;
    /** @var \Schoenstatt\Service\PatresGateway $patresGateway */
    protected $patresGateway;

    // SchoenstattTable went with maintenanceAction() on 2026-08-17; it was that action's
    // dependency and nothing else read it. LazyControllerFactory resolves constructor
    // arguments by reflection, so dropping one needs no factory change.
    public function __construct(
        TranslationsTable $translationsTable,
        ProblemService $problemService,
        ImportFatherForm $importFatherForm,
        PatresGateway $patresGateway
    ) {
        $this->translationsTable = $translationsTable;
        $this->problemService = $problemService;
        $this->importFatherForm = $importFatherForm;
        $this->patresGateway = $patresGateway;
    }

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

        /** @var TranslationsTable $translations */
        $translations = $this->translationsTable;

        $badges['jtranslate'] = (string) $translations->getOutstandingTranslationCount();

        /** @var ProblemService $problemService */
        $problemService = $this->problemService;
        $problems = $problemService->getCurrentProblems();
        $badges['sion-model/data-problems'] = count($problems);

        return new ViewModel([
            'pages' => $pages,
            'badges' => $badges,
        ]);
    }

    public function importFatherAction()
    {
        $form = $this->importFatherForm;
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost()->toArray();
            $form->setData($data);
            if ($form->isValid()) { //here the sent personId will be checked against the haystack
                $personId = $form->getData()['personId'];
                //Three outcomes, not two. Until 2026-08-17 this reported `false` as
                //"Person already exists in the database." — which it never meant: false
                //comes back only when the record from Patres fails the person input
                //filter, and existing records are updated rather than refused. So the
                //one message an administrator ever got about a failed import named the
                //wrong cause and offered nothing to act on. The overwrite was the
                //opposite problem: it succeeded silently under "successfully imported".
                $overwroteExisting = false;
                $result = $this->patresGateway->importRemotePerson($personId, [], $overwroteExisting);
                if (false === $result) {
                    $fields = array_keys($this->patresGateway->getLastRemotePersonMessages());
                    $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)
                        ->addMessage(new TranslatableMessage(
                            'The record for this person in Patres could not be imported because it '
                            . 'is not valid. Fields at fault: %s. Ask Patres to correct it, then try again.',
                            [$fields === [] ? '(none reported)' : implode(', ', $fields)]
                        ));
                } elseif ($overwroteExisting) {
                    $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_SUCCESS)
                        ->addMessage('This person had already been imported. Their record has been '
                            . 'refreshed from Patres, replacing what was stored here.');
                } else {
                    //$form = $sm->get('Schoenstatt\Form\ImportFatherForm'); why was this here?
                    $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_SUCCESS)
                        ->addMessage('Person successfully imported.');
                }
            } else {
                $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)
                    ->addMessage('Error in form submission, please review.');
            }
        }
        return new ViewModel([
            'form'  => $form,
        ]);
    }

    // maintenanceAction() lived here until 2026-08-17. See the note where its route was,
    // in module/Schoenstatt/config/module.config.php: it stripped a correct `priest` tag
    // from five people whose ordination date is simply not recorded.
}

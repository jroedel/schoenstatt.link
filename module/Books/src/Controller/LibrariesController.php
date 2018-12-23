<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Books\Form\SearchForm;
use Zend\View\Model\ViewModel;
use JTranslate\Controller\Plugin\NowMessenger;
use SionModel\Problem\EntityProblem;
use SionModel\Service\ProblemService;
use Zend\View\Model\JsonModel;
use Books\Form\InactivationForm;
use Zend\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Schoenstatt\Model\SchoenstattTable;
use Books\Model\PublicationsTable;
use Books\Mailing\BooksMailer;
use BjyAuthorize\Exception\UnAuthorizedException;
use JTranslate\Model\TranslationsTable;

class LibrariesController extends SionController
{
    public function showAction()
    {
        $view = parent::showAction();
        if ($view instanceof \Zend\Stdlib\ResponseInterface) {
            return $view;
        }
        $entityObject = $view->getVariable('entity');
        $params = $this->params()->fromQuery();
        if (isset($entityObject['libraryId'])) {
            $params['libraryId'] = $entityObject['libraryId'];
        }
        $form = $this->services[SearchForm::class];
        $form->setData($params);
        $books = null;
        $borrowers = [];

        /** @var LibraryTable $table */
        $table = $this->getSionTable();

        if ($form->isValid()) {
            $data = $form->getData();
            foreach ($data as $key => $value) {
                if (!isset($value)) {
                    unset($data[$key]);
                }
            }
            if (count($data) > 1) {
//                 $data['category'] = ['Documentos', 'Dogmática General'];
//                 $data['isActive'] = false;
                $books = $table->searchBooks($data, ['maxResults' => 200]);
                $borrowers = $this->services['Books\BorrowersValueOptions'];
            }
        }

        if (is_array($books) && empty($books)) {
            $this->nowMessenger()->addMessage("No results found.", NowMessenger::NAMESPACE_INFO);
        }

        $view->setVariable('publications', $this->getPublications());
        $view->setVariable('books', $books);
        $view->setVariable('borrowers', $borrowers);
        $view->setVariable('form', $form);

        return $view;
    }

    public function labelManagementAction()
    {
        $resourceId = 'library_'.$this->getLibraryId();
        if (!$this->isAllowed($resourceId, 'administrate')) {
            throw new UnAuthorizedException();
        }
    }

    public function batchOperationsAction()
    {
        $resourceId = 'library_'.$this->getLibraryId();
        if (!$this->isAllowed($resourceId, 'administrate')) {
            throw new UnAuthorizedException();
        }
    }

    public function getBookListJsonAction()
    {
        $resourceId = 'library_'.$this->getLibraryId();
        if (!$this->isAllowed($resourceId, 'administrate')) {
            throw new UnAuthorizedException();
        }
        /** @var LibraryTable $table */
        $table = $this->getSionTable();
        $table->setLibraryId($this->getEntityIdParam('show'));
        $books = $table->getLibraryBooksStatuses();

        /** @var SchoenstattTable $schTable */
        $schTable = $this->services[SchoenstattTable::class];
        $persons = $schTable->getUnlinkedPersons();
        foreach ($books as $bookId => $book) {
            if (isset($book['checkedOutBy']) && key_exists($book['checkedOutBy'], $persons)) {
                $books[$bookId]['checkedOutBy'] = $persons[$book['checkedOutBy']]['fullFriendlyName'];
            }
        }
        return new JsonModel(['books' => $books]);
    }

    public function adminAction()
    {
        $view = $this->showAction();
        $resourceId = 'library_'.$this->getLibraryId();
        if (!$this->isAllowed($resourceId, 'administrate')) {
            throw new UnAuthorizedException();
        }
        $config = $this->config['books'];
        $pages = $config['admin_pages'];
        /** @var LibraryTable $table */
        $table = $this->getSionTable();

        /** @var TranslationsTable $translations */
        $translations = $this->services[TranslationsTable::class];

        if (isset($pages['admin/moderate'])) {
//             $suggestionCount = $table->getSuggestionCount();
//             $pages['admin/moderate']['badges'] = [$suggestionCount ? ' '.$suggestionCount : " 0"];
        }

        if (isset($pages['jtranslate'])) {
            $pages['jtranslate']['badges'] = [(string) $translations->getOutstandingTranslationCount()];
        }

        if (isset($pages['admin/website-status'])) {
            $pages['admin/website-status']['badges'] = [count($this->getKnownIssues())];
        }

        if (isset($pages['libraries/library/data-problems'])) {
            $problemCounts = $this->getProblemCounts();
            $pages['libraries/library/data-problems']['badges'] = $problemCounts;
        }

        if (isset($pages['library-imports/library'])) {
            $importCount = $table->getLibraryImports();
            $pages['library-imports/library']['badges'] = [count($importCount)];
        }
        if (isset($pages['sion-model/auto-fix-data-problems'])) {
            /** @var ProblemService $problemService */
            $problemService = $this->services[ProblemService::class];
            $autoFixProblems = $problemService->autoFixProblems();
            $pages['sion-model/auto-fix-data-problems']['badges'] = [count($autoFixProblems)];
        }
        $view->setVariables([
            'pages' => $pages,
        ]);
        return $view;
    }
    
    public function dataProblemsAction()
    {
        /** @var LibraryTable $table */
        $table = $this->getSionTable();
        $libraryId = $this->getLibraryId();
        $object = $table->getSimpleLibrary($libraryId);
        $problems = array_merge($table->getLibraryProblems($object), $table->getLibraryBookProblems($libraryId));
        $view = new ViewModel([
            'problems' => $problems,
        ]);
        $view->setTemplate('sion-model/sion-model/data-problems');
        return $view;
    }

    /**
     * Query data problems from the ProblemService and count them according to severity
     * @return number[]
     */
    protected function getProblemCounts()
    {
        /** @var LibraryTable $table */
        $table = $this->getSionTable();
        $libraryId = $this->getLibraryId();
        $object = $table->getSimpleLibrary($libraryId);
        $problems = array_merge($table->getLibraryProblems($object), $table->getLibraryBookProblems($libraryId));
        
        $problemCounts = [
            EntityProblem::SEVERITY_ERROR => 0,
            EntityProblem::SEVERITY_WARNING => 0,
            EntityProblem::SEVERITY_INFO => 0,
        ];
        foreach ($problems as $problem) {
            $severity = $problem->getSeverity();
            if (isset($problemCounts[$severity])) {
                $problemCounts[$severity]++;
            }
        }

        foreach ($problemCounts as $severity => $value) {
            if ($value === 0) {
                unset($problemCounts[$severity]);
            }
        }
        return $problemCounts;
    }

    public function searchAction()
    {
        $params = $this->params()->fromQuery();
        $form = $this->services[SearchForm::class];
        $form->setData($params);
        $books = null;
        $borrowers = [];

        if ($form->isValid()) {
            $notAllowed = [];
            $data = $form->getData();

            if (!empty($data)) {
                /** @var LibraryTable $table */
                $table = $this->getSionTable();
//                 $data['notAllowed'] = $notAllowed;
                $data['maxResults'] = 200;
//                 var_dump($data);
                $books = $table->searchBooks($data);
                $libraries = $this->transformBookQueryIntoLibraries($books);
                $borrowers = $this->services['Books\BorrowersValueOptions'];
            }
        }

        if (is_array($books) && empty($books)) {
            $this->nowMessenger()->addMessage("No results found.", NowMessenger::NAMESPACE_INFO);
        }

        return new ViewModel([
            'publications'  => $this->getPublications(),
            'entities'  => $libraries,
            'borrowers' => $borrowers,
            'form'      => $form,
        ]);
    }

    public function inactivateBooksAction()
    {
        $resourceId = 'library_'.$this->getLibraryId();
        if (!$this->isAllowed($resourceId, 'administrate')) {
            throw new UnAuthorizedException();
        }
        //get the parameter
        $libraryId = $this->getLibraryId();

        $form = new InactivationForm();

        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost()->toArray();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                /** @var LibraryTable $table */
                $table = $this->getSionTable();
                try {
                    if (is_array($badValues = $table->inactivateWithinLibraryBooks($libraryId, $data))) {
                        $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)
                        ->addMessage(sprintf(
                            "There was a problem with one or more of the books: (%s) Please try again.",
                            implode(', ', $badValues)
                        ));
                    } else {
                        $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                        ->addMessage('Books successfully inactivated.');
                        $this->redirect()->toRoute('libraries/library/admin', ['library_id' => $libraryId]);
                    }
                } catch (\Exception $e) {
                    $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)
                    ->addMessage($e->getMessage());
                }
            } else {
                $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)->addMessage('Error in form submission, please review.');
            }
        }
        return new ViewModel([
            'libraryId'             => $libraryId,
            'form'                  => $form,
        ]);
    }

    public function bookListAction()
    {
        $resourceId = 'library_'.$this->getLibraryId();
        if (!$this->isAllowed($resourceId, 'show')) {
            throw new UnAuthorizedException();
        }
        /** @var LibraryTable $table */
        $table = $this->getSionTable();
        $books = $table->getBooks();
        return new ViewModel([
            'objects' => $books,
        ]);
    }

    /**
     * Send notices to users
     * @return \Zend\View\Model\ViewModel
     */
    public function sendBookNoticesAction()
    {
        $key = $this->params()->fromQuery('key', null);
        $config = $this->config;
        $apiKeys = [];
        if (isset($config['schoenstatt']) && isset($config['schoenstatt']['api_keys']) &&
            is_array($config['schoenstatt']['api_keys'])
        ) {
            $apiKeys = $config['schoenstatt']['api_keys'];
        }
        $resourceId = 'library_'.$this->getLibraryId();
        if (!$this->isAllowed($resourceId, 'administrate') && !in_array($key, $apiKeys)) {
            throw new UnAuthorizedException();
        }
        $libraryId = $this->getLibraryId();
        $simulate = (bool)$this->params()->fromQuery('simulate', true);
        $borrowerSubset = $this->params()->fromQuery('borrowers', []);
        $sendOnlyToBorrowersWithOverdueBooks = (bool)$this->params()->fromQuery('onlyOverdueBorrowers', true);

        $table = $this->getSionTable();
        $library = $table->getSimpleLibrary($libraryId);
        /** @var BooksMailer $mailer */
        $mailer = $this->services[BooksMailer::class];
        $borrowers = $mailer->sendBookNotices($libraryId, $sendOnlyToBorrowersWithOverdueBooks, $simulate, $borrowerSubset);

        return new ViewModel([
            'queryParams'   => $this->params()->fromQuery(),
            'simulate' => $simulate,
            'borrowers' => $borrowers,
            'library'   => $library,
        ]);
    }

    public function transformBookQueryIntoLibraries($books)
    {
        $entities = [];
        foreach ($books as $bookId => $book) {
            if (!isset($entities[$book['libraryId']])) {
                $entities[$book['libraryId']] = $book['library'];
            }
            $entities[$book['libraryId']]['books'][] = $book;
        }
        return $entities;
    }

    protected function getPublications()
    {
        /** @var PublicationsTable $table */
        $table = $this->services[PublicationsTable::class];
        $publications = $table->getUnlinkedPublications();
        return $publications;
    }

    /**
     * Get the library id. If not found, send the user back to the libraries index and give them a flash message
     * @return number
     */
    protected function getLibraryId()
    {
        $id = ( int ) $this->params()->fromRoute('library_id');
        if (!$id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Library not found.');
            $this->redirect()->toRoute('libraries');
        }
        return $id;
    }
}

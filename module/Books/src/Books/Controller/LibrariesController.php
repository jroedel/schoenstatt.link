<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Books\Form\SearchForm;
use Zend\View\Model\ViewModel;
use JTranslate\Controller\Plugin\NowMessenger;
use Books\Model\LibraryTable;
use SionModel\Problem\EntityProblem;
use SionModel\Service\ProblemService;
use Zend\View\Model\JsonModel;
use Books\Form\InactivationForm;
use Zend\Mvc\Controller\Plugin\FlashMessenger;
use Schoenstatt\Model\SchoenstattTable;
use Books\Model\PublicationsTable;

class LibrariesController extends SionController
{
    public function __construct()
    {
        return parent::__construct('library');
    }

    public function showAction()
    {
        $view = parent::showAction();
        $entityObject = $view->getVariable('entity');
        $params = $this->params()->fromQuery();
        if (isset($entityObject['libraryId'])) {
            $params['libraryId'] = $entityObject['libraryId'];
        }
        $sm = $this->getServiceLocator();
        $form = $sm->get('Books\Form\SearchForm');
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
                $borrowers = $sm->get('Books\BorrowersValueOptions');
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

    }

    public function batchOperationsAction()
    {

    }

    public function getBookListJsonAction()
    {
        $sm = $this->getServiceLocator();
        /** @var LibraryTable $table */
        $table = $sm->get('Books\Model\LibraryTable');
        $table->setLibraryId($this->getEntityIdParam('show'));
        $books = $table->getLibraryBooksStatuses();

        /** @var SchoenstattTable $schTable */
        $schTable = $sm->get('Schoenstatt\Model\SchoenstattTable');
        $persons = $schTable->getUnlinkedPersons();
        foreach ($books as $bookId => $book) {
            if (!is_null($book['checkedOutBy']) && key_exists($book['checkedOutBy'], $persons)) {
                $books[$bookId]['checkedOutBy'] = $persons[$book['checkedOutBy']]['fullFriendlyName'];
            }
        }
        return new JsonModel(['books' => $books]);
    }

    public function adminAction()
    {
        $view = $this->showAction();
        $sm = $this->getServiceLocator();
        $config = $sm->get('Books\Config');
        $pages = $config['admin_pages'];
        /** @var LibraryTable $table */
        $table = $sm->get('Books\Model\LibraryTable');

        /** @var TranslationsTable $translations */
        $translations = $sm->get('JTranslate\Model\TranslationsTable');

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

        if (isset($pages['sion-model/data-problems'])) {
            $problemCounts = $this->getProblemCounts();
            $pages['sion-model/data-problems']['badges'] = $problemCounts;
        }

        if (isset($pages['library-imports/library'])) {
            $importCount = $table->getLibraryImports();
            $pages['library-imports/library']['badges'] = [count($importCount)];
        }

        if (isset($pages['sion-model/auto-fix-data-problems'])) {
            /** @var ProblemService $problemService */
            $problemService = $sm->get('SionModel\Service\ProblemService');
            $problems = $problemService->getCurrentProblems();
            $autoFixProblems = $problemService->autoFixProblems();
            $pages['sion-model/auto-fix-data-problems']['badges'] = [count($autoFixProblems)];
        }
        $view->setVariables([
            'pages' => $pages,
        ]);
        return $view;
    }

    /**
     * Query data problems from the ProblemService and count them according to severity
     * @return number[]
     */
    protected function getProblemCounts()
    {
        $sm = $this->getServiceLocator();
        /** @var ProblemService $problemService */
        $problemService = $sm->get('SionModel\Service\ProblemService');
        $problems = $problemService->getCurrentProblems();
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
                unset ($problemCounts[$severity]);
            }
        }
        return $problemCounts;
    }

    public function searchAction()
    {
        $sm = $this->getServiceLocator();
        $params = $this->params()->fromQuery();
        $form = $sm->get('Books\Form\SearchForm');
        $form->setData($params);
        $books = null;
        $borrowers = [];

        if ($form->isValid()) {
            $notAllowed = [];
//             if (!$this->isAllowed('book_teo', 'read')) {
//                 $notAllowed[] = '\'DigitaSión Teo\'';
//                 $notAllowed[] = '\'DigitaSión Fil\'';
//             }
//             if (!$this->isAllowed('book_sch', 'read')) {
//                 $notAllowed[] = '\'DigitaSión Sch\'';
//             }
            $data = $form->getData();

            if (!empty($data)) {
                /** @var LibraryTable $table */
                $table = $sm->get('Books\Model\LibraryTable');
//                 $data['notAllowed'] = $notAllowed;
                $data['maxResults'] = 200;
//                 var_dump($data);
                $books = $table->searchBooks($data);
                $libraries = $this->transformBookQueryIntoLibraries($books);
                $borrowers = $sm->get('Books\BorrowersValueOptions');
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
        //get the parameter
        $libraryId = $this->getLibraryId();

        $sm = $this->getServiceLocator();
        $form = new InactivationForm();

        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                /** @var LibraryTable $table */
                $table = $this->getSionTable();
                try {
                    if (is_array($badValues = $table->inactivateWithinLibraryBooks($libraryId, $data))) {
                        $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )
                        ->addMessage (sprintf("There was a problem with one or more of the books: (%s) Please try again.",
                            implode(', ', $badValues)));
                    } else {
                        $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )
                        ->addMessage ('Books successfully inactivated.' );
                        $this->redirect ()->toRoute ('libraries/library/admin', ['library_id' => $libraryId]);
                    }
                } catch (\Exception $e) {
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )
                    ->addMessage ($e->getMessage());
                }
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        }
        return new ViewModel([
            'libraryId'             => $libraryId,
            'form'                  => $form,
        ]);
    }

    public function bookListAction()
    {
        /** @var LibraryTable $table */
        $table = $this->getSionTable();
        $books = $table->getBooks();
        return new ViewModel([
            'objects' => $books,
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
        $table = $this->getServiceLocator()->get('Books\Model\PublicationsTable');
        $publications = $table->getUnlinkedPublications();
        return $publications;
    }

    /**
     * Get the library id. If not found, send the user back to the libraries index and give them a flash message
     * @return number
     */
    protected function getLibraryId()
    {
        $id = ( int ) $this->params ()->fromRoute ( 'library_id' );
        if (!$id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Library not found.');
            $this->redirect ()->toRoute ( 'libraries');
        }
        return $id;
    }
}

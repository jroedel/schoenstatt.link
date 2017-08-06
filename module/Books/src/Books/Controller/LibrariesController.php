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
        if (!is_null($entityObject['libraryId'])) {
            $params['libraryId'] = $entityObject['libraryId'];
        }
        $form = new SearchForm();
        $form->setData($params);
        $books = null;

        $table = $this->getSionTable();

        if ($form->isValid()) {
            $data = $form->getData();
            foreach ($data as $key => $value) {
                if (is_null($value)) {
                    unset($data[$key]);
                }
            }

            if (count($data) > 1) {
                $data['maxResults'] = 200;
                $books = $table->searchBooks($data);
            }
        }

        if (is_array($books) && empty($books)) {
            $this->nowMessenger()->addMessage("No results found.", NowMessenger::NAMESPACE_INFO);
        }
        $view->setVariable('books', $books);
        $view->setVariable('form', $form);

        return $view;
    }

    public function getBookListJsonAction()
    {
        $sm = $this->getServiceLocator();
        /** @var LibraryTable $table */
        $table = $sm->get('Books\Model\LibraryTable');
        $table->setLibraryId($this->getEntityIdParam('show'));
        $books = $table->getLibraryBooksStatuses();
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

        if (key_exists('admin/moderate', $pages)) {
//             $suggestionCount = $table->getSuggestionCount();
//             $pages['admin/moderate']['badges'] = [$suggestionCount ? ' '.$suggestionCount : " 0"];
        }

        if (key_exists('jtranslate', $pages)) {
            $pages['jtranslate']['badges'] = [(string) $translations->getOutstandingTranslationCount()];
        }

        if (key_exists('admin/website-status', $pages)) {
            $pages['admin/website-status']['badges'] = [count($this->getKnownIssues())];
        }

        if (key_exists('sion-model/data-problems', $pages)) {
            $problemCounts = $this->getProblemCounts();
            $pages['sion-model/data-problems']['badges'] = $problemCounts;
        }

        if (key_exists('library-imports/library', $pages)) {
            $importCount = $table->getLibraryImports();
            $pages['library-imports/library']['badges'] = [count($importCount)];
        }

        if (key_exists('sion-model/auto-fix-data-problems', $pages)) {
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
            if (key_exists($severity, $problemCounts)) {
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
        $params = $this->params()->fromQuery();
        $form = new SearchForm();
        $form->setData($params);
        $books = null;

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
                $sm = $this->getServiceLocator();
                /** @var LibraryTable $table */
                $table = $sm->get('Books\Model\LibraryTable');
//                 $data['notAllowed'] = $notAllowed;
                $data['maxResults'] = 200;
                $books = $table->searchBooks($data);
                $libraries = $this->transformBookQueryIntoLibraries($books);
            }
        }

        if (is_array($books) && empty($books)) {
            $this->nowMessenger()->addMessage("No results found.", NowMessenger::NAMESPACE_INFO);
        }

        return new ViewModel([
            'entities'  => $libraries,
            'form'      => $form,
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
            if (!key_exists($book['libraryId'], $entities)) {
                $entities[$book['libraryId']] = $book['library'];
            }
            $entities[$book['libraryId']]['books'][] = $book;
        }
        return $entities;
    }
}
<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use Books\Form\TextSearchForm;
use Zend\View\Model\ViewModel;
use JTranslate\Controller\Plugin\NowMessenger;
use Zend\Db\Sql\Predicate\PredicateSet;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Predicate\Like;
use Zend\Db\Sql\Predicate\Operator;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
use Schoenstatt\Model\SchoenstattTable;

class TextsController extends SionController
{
    public function searchAction()
    {
        $params = $this->params()->fromQuery();
        $form = new TextSearchForm();
        $form->setData($params);
        $objects = null;
        $search = null;
        if ($form->isValid()) {
            $data = $form->getData();
            
            if (!empty($data)) {
                /** @var \Books\Model\PublicationsTable $table */
                $table = $this->getSionTable();
//                 $options = [];
//                 $options['maxResults'] = self::MAX_SEARCH_RESULTS;
//                 if (!isset($data['showEditionsSeparately']) || $data['showEditionsSeparately'] != '1') {
//                     $options['noSubEditions'] = true;
//                 }
                
                $fieldMap = $this->getEntitySpecification()->updateColumns;
                $where = new PredicateSet();
                if (isset($data['search'])) {
                    $search = $data['search'];
                    $searchLike = sprintf("%%%s%%", $search);
                    $searchClause = new Predicate();
                    $searchClause->addPredicates([
                        new Like($fieldMap['title'], $searchLike),
                        new Like($fieldMap['markdownText'], $searchLike),
                        new Like($fieldMap['publicNotes'], $searchLike),
                        new Operator($fieldMap['textId'], Operator::OPERATOR_EQUAL_TO, $search),
                    ], PredicateSet::OP_OR);
                    $where->addPredicate($searchClause);
                }
                $objects = $table->queryObjects('text', $where);
//                 if (is_array($objects) && count($objects) == self::MAX_SEARCH_RESULTS) {
//                     $this->nowMessenger()->addMessage("More than the max number of publications match your search. Only the first 300 results shown.", NowMessenger::NAMESPACE_INFO);
//                 }
            }
        }
        
        if (is_array($objects) && empty($objects)) {
            $this->nowMessenger()->addMessage("No results found.", NowMessenger::NAMESPACE_INFO);
        }
        
        $view = new ViewModel([
            'objects'  => $objects,
            'form'      => $form,
            'search'    => $search,
        ]);
        $view->setTemplate('books/texts/index');
        return $view;
    }
    
    public function importJkTextsAction()
    {
        $table = $this->getSionTable();
        $table->importJkTexts(false);
    }
    
    public function getEntityObject($id)
    {
        $object = $this->getSionTable()->getObject('text', $id);
        return $this->object[$id] = $object;
    }
    
    /**
     * Makes sure this function returns the textId if passed a site-wide id
     *
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::getEntityIdParam()
     */
    protected function getEntityIdParam($action = 'show', $default = null)
    {
        static $swValidator;
        static $swFilter;
        $id = $this->params()->fromRoute('sw_id');
        if (isset($id)) {
            if (!isset($swValidator)) {
                $swValidator = new SchoenstattLinkIdentifier('text');
            }
            if (!$swValidator->isValid($id)) {
                throw new \Exception('Invalid site-wide id');
            }
            if (!isset($swFilter)) {
                $swFilter = new \Schoenstatt\Filter\SchoenstattLinkIdentifier('text');
            }
            $id = $swFilter->filter($id);
        } else {
            throw new \Exception('Invalid text id');
        }
        return $id;
    }
    
    /**
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::redirectAfterEdit()
     */
    public function redirectAfterEdit($id, $data = [], $form = null, $updatedObject = [])
    {
        return $this->redirect()->toRoute('text', ['sw_id' => $updatedObject['identifier'], 'slug' => $updatedObject['slug']]);
    }
    
    /**
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::redirectAfterCreate()
     */
    public function redirectAfterCreate($newId, $data = [], $form = null)
    {
        $swFilter = new ToSchoenstattLinkIdentifier('text');
        $identifier = $swFilter->filter($newId);
        $slug = SchoenstattTable::getSlug($data['title']);
        return $this->redirect()->toRoute('text', ['sw_id' => $identifier, 'slug' => $slug]);
    }
    
    /**
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::redirectAfterDelete()
     */
    protected function redirectAfterDelete($actionWasSuccessful = true)
    {
        return $this->redirect()->toRoute('texts');
    }
}

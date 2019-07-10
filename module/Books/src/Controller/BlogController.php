<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Zend\Stdlib\ResponseInterface;
use Books\Model\EventTextTable;
use Zend\Mvc\Plugin\FlashMessenger\FlashMessenger;

class BlogController extends SionController
{
    public function showAction()
    {
        $slug = $this->params()->fromRoute('slug');
        if (!isset($slug)) {
            $id = $this->getEntityIdParam('show');
            $entityObject = $this->getEntityObject($id);
            return $this->redirect()->toRoute('blog/blog-post', ['text_id' => $entityObject['textId'], 'slug' => $entityObject['slug']]);
        }
        $view = parent::showAction();
        if ($view instanceof ResponseInterface) {
            return $view;
        }
        $object = $view->getVariable('entity');
        if ($object['kind'] !== EventTextTable::TEXT_KIND_BLOG) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_INFO)
                ->addMessage('Blog entry not found');
            return $this->redirect()->toRoute('blog');
        }
        $schema = EventTextTable::getBlogPostSchema($object);
        $view->setVariable('schema', $schema);
        $view->setTemplate('books/blog/show');
        return $view;
    }
    
    public function saveDraftAction()
    {
        //the idea here is to create a new text (kind=blog-draft)
        //every minute or so we could in theory get a lost post back
    }
    
    public function getEntityObject($id)
    {
        $object = $this->getSionTable()->getObject('text', $id);
        return $this->object[$id] = $object;
    }
    
    /**
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::redirectAfterEdit()
     */
    public function redirectAfterEdit($id, $data = [], $form = null)
    {
        return $this->redirect()->toRoute('blog/blog-post', ['text_id' => $id]);
    }
    
    /**
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::redirectAfterCreate()
     */
    public function redirectAfterCreate($newId, $data = [], $form = null)
    {
        return $this->redirect()->toRoute('blog/blog-post', ['text_id' => $newId]);
    }
    
    /**
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::redirectAfterDelete()
     */
    protected function redirectAfterDelete($actionWasSuccessful = true)
    {
        return $this->redirect()->toRoute('blog');
    }
}

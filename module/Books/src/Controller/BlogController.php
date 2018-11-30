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
        $object = $this->getSionTable()->getText($id);
        return $this->object[$id] = $object;
    }
}

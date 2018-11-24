<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Zend\Stdlib\ResponseInterface;
use Books\Model\EventTextTable;
use Zend\Mvc\Plugin\FlashMessenger\FlashMessenger;
use SionModel\Form\CommentForm;

class BlogController extends SionController
{
//     public function createAction()
//     {
//         $libraryId = $this->params()->fromRoute('library_id');
//         $resourceId = 'library_'.$libraryId;
//         if (!$this->isAllowed($resourceId, 'administrate')) {
//             throw new UnAuthorizedException();
//         }
//         $view = parent::createAction();
//         if ($view instanceof \Zend\Stdlib\ResponseInterface) {
//             return $view;
//         }
//         $view->setVariable('libraryId', $libraryId);
//         return $view;
//     }
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
        $commentForm = new CommentForm();
        $commentUrl = $this->url()->fromRoute('comments/create', [
            'kind' => \SionModel\Db\Model\PredicatesTable::COMMENT_KIND_COMMENT,
            'entity' => 'text',
            'entity_id' => $object['textId'],
        ]);
        $commentForm->setAttribute('action', $commentUrl);
        $view->setVariable('commentForm', $commentForm);
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

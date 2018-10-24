<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use BjyAuthorize\Exception\UnAuthorizedException;

class CollectionsController extends SionController
{
    public function createAction()
    {
        $libraryId = $this->params()->fromRoute('library_id');
        $resourceId = 'library_'.$libraryId;
        if (!$this->isAllowed($resourceId, 'administrate')) {
            throw new UnAuthorizedException();
        }
        $view = parent::createAction();
        $view->setVariable('libraryId', $libraryId);
        return $view;
    }
}

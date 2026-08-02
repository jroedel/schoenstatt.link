<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use BjyAuthorize\Exception\UnAuthorizedException;
use Laminas\View\Model\ViewModel;

class CollectionsController extends SionController
{
    public function indexAction()
    {
        $libraryId = $this->params()->fromRoute('library_id');
        $table = $this->getSionTable();
        $collections = $table->queryObjects('collection', ['libraryId' => $libraryId]);
        return new ViewModel([
            'libraryId' => $libraryId,
            'objects' => $collections,
        ]);
    }

    public function createAction()
    {
        $libraryId = $this->params()->fromRoute('library_id');
        $resourceId = 'library_' . $libraryId;
        if (! $this->isAllowed($resourceId, 'administrate')) {
            throw new UnAuthorizedException();
        }
        $view = parent::createAction();
        if ($view instanceof \Laminas\Stdlib\ResponseInterface) {
            return $view;
        }
        $view->setVariable('libraryId', $libraryId);
        return $view;
    }
}

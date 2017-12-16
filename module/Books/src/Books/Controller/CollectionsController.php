<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use BjyAuthorize\Exception\UnAuthorizedException;

class CollectionsController extends SionController
{
    public function __construct()
    {
        return parent::__construct('collection');
    }

    public function createAction()
    {
        $libraryId = $this->params ()->fromRoute ( 'library_id' );
        $resourceId = 'library_'.$libraryId;
        if (!$this->isAllowed($resourceId, 'administrate')) {
            throw new UnAuthorizedException();
        }
        $view = parent::createAction();
        $view->setVariable('libraryId', $libraryId);
        return $view;
    }

//     public function editAction()
//     {
//         $view = parent::editAction();
//         $object = $view->getVariable('entity');
//         if (isset($object) && isset($object['libraryId'])) {

//         }
//         $libraryId = $this->params ()->fromRoute ( 'library_id' );
//         $resourceId = 'library_'.$object['libraryId'];
//         if (!$this->isAllowed($resourceId, 'administrate')) {
//             throw new UnAuthorizedException();
//         }
//         return $view;
//     }

//     /**
//      * This function is called after a successful entity creation to redirect the user.
//      * May be overwritten by a child Controller to add functionality.
//      * @param int $newId
//      * @throws \Exception
//      */
//     public function redirectAfterCreate($newId)
//     {
//         $sm = $this->getServiceLocator ();
//         /** @var SionTable $table **/
//         $table = $this->getSionTable();
//         $entity = $this->getEntity();
//         $entitySpec = $this->getEntitySpecification();

//         //check if user has the redirect route set
//         if (isset($entitySpec->createActionRedirectRoute)) {
//             if (!isset($entitySpec->createActionRedirectRouteKeyField) ||
//                 $entitySpec->createActionRedirectRouteKeyField == $entitySpec->entityKeyField ||
//                 !isset($entitySpec->createActionRedirectRouteKey)
//             ) {
//                 $this->redirect ()->toRoute ($entitySpec->createActionRedirectRoute,
//                     isset($entitySpec->createActionRedirectRouteKey) ?
//                     [$entitySpec->createActionRedirectRouteKey => $newId] : []);
//             } else {
//                 $entityObj = $table->getObject($entity, $newId);
//                 if (!isset($entityObj[$entitySpec->createActionRedirectRouteKeyField])) {
//                     throw new \Exception('create_action_redirect_route_key_field is misconfigured for entity \''.$entity.'\'');
//                 }
//                 $this->redirect ()->toRoute ($entitySpec->createActionRedirectRoute,
//                     [$entitySpec->createActionRedirectRouteKey => $entityObj[$entitySpec->createActionRedirectRouteKeyField]]);
//             }
//         } else {
//             $this->redirect ()->toRoute ($this->getDefaultRedirectRoute());
//         }
//     }
}

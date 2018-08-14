<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Zend\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Books\Model\LibraryTable;
use Zend\View\Model\ViewModel;
use JTranslate\Controller\Plugin\NowMessenger;
use Books\Form\CheckinForm;
use Books\Form\MassCheckoutForm;
use Zend\Form\Element\Select;
use Books\Model\LibraryOptions;
use Schoenstatt\Service\PatresGateway;
use Schoenstatt\Model\SchoenstattTable;
use BjyAuthorize\Exception\UnAuthorizedException;
use Books\Form\EventsSearchForm;
use Books\Model\EventTextTable;

class EventsController extends SionController
{
    const MAX_SEARCH_RESULTS = 150;
    
    public function searchAction()
    {
        $params = $this->params()->fromQuery();
        $form = $this->services[EventsSearchForm::class];
        $form->setData($params);
        $entities = null;
        
        if ($form->isValid()) {
            $data = $form->getData();
            
            if (!empty($data)) {
                /** @var EventTextTable $table */
                $table = $this->getSionTable();
                $data['maxResults'] = self::MAX_SEARCH_RESULTS;
                $entities = $table->searchEvents($data);
                if (is_array($entities) && count($entities) == self::MAX_SEARCH_RESULTS) {
                    $this->nowMessenger()->addMessage("More than the max number of publications match your search. Only the first 300 results shown.", NowMessenger::NAMESPACE_INFO);
                }
            }
    }
    
    if (is_array($entities) && empty($entities)) {
        $this->nowMessenger()->addMessage("No results found.", NowMessenger::NAMESPACE_INFO);
    }
    
    return new ViewModel([
        'entities'  => $entities,
        'form'      => $form,
    ]);
}
}
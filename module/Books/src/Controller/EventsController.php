<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Zend\View\Model\ViewModel;
use JTranslate\Controller\Plugin\NowMessenger;
use Books\Form\EventsSearchForm;

class EventsController extends SionController
{
    const MAX_SEARCH_RESULTS = 150;
    
    public function searchAction()
    {
        $params = $this->params()->fromQuery();
        //@todo revise that the search form is properly using field names
        $form = $this->services[EventsSearchForm::class];
        $form->setData($params);
        $entities = null;
        
        if ($form->isValid()) {
            $data = $form->getData();
            
            if (!empty($data)) {
                /** @var \Books\Model\EventTextTable $table */
                $table = $this->getSionTable();
                $options = ['maxResults' => self::MAX_SEARCH_RESULTS];
                $entities = $table->queryObjects('event', $data, $options);
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

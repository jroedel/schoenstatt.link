<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Zend\View\Model\ViewModel;
use JTranslate\Controller\Plugin\NowMessenger;
use Books\Form\EventsSearchForm;
use Books\Model\EventTextTable;
use Books\Filter\KentenichPeriodFromDate;

class EventsController extends SionController
{
    const MAX_SEARCH_RESULTS = 150;
    
    public function indexAction()
    {
        $table = $this->getSionTable();
        $objects = $table->queryObjects('event');
        $periods = $this->groupEventsByEpochAndYear($objects);
        
        return new ViewModel([
            'objects' => $objects,
            'periods' => $periods,
        ]);
    }
    
    public function groupEventsByEpochAndYear(array $eventObjects)
    {
        $periods = [];
        /** @var EventTextTable $table */
        $table = $this->getSionTable();
        $periodFilter = new KentenichPeriodFromDate();
        foreach ($eventObjects as $object) {
            $period = $periodFilter->filter($object['startDate']);
            if (!isset($periods[$period])) {
                $periods[$period] = [];
            }
            $year = $object['startDate']->format('Y');
            if (!isset($periods[$period][$year])) {
                $periods[$period][$year] = [];
            }
            $periods[$period][$year][$object['eventId']] = $object;
        }
        return $periods;
    }
    
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

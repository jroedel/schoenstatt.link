<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Zend\View\Model\ViewModel;
use Zend\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Spatie\SchemaOrg\BaseType;
use Zend\Json\Json;
use Zend\Navigation\Navigation;
use SionModel\Service\EntitiesService;
use SionModel\Db\Model\PredicatesTable;
use SionModel\Db\Model\SionTable;

class DictionaryController extends SionController
{
    public function inLanguageAction()
    {
        $inLanguage = $this->params()->fromRoute('inLanguage');
        
        /** @var \Books\Model\DictionaryTable $table */
        $table = $this->getSionTable();
        
        $availableLanguages = $table->getAvailableDictionaryLanguages();
        if (!in_array($inLanguage, $availableLanguages, true)) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)->addMessage('Language not found');
            return $this->redirect()->toRoute('dictionary');
        }
        $inLanguageName = $table->getLanguageName($inLanguage);
        
        $dictionarySchema = $table->getDictionarySchema($inLanguage);
        $objects = $table->queryObjects('dictionary-entry', ['locale' => 'es_ES', 'isActive' => true]);
        $schemata = Json::encode($this->combineSchema($dictionarySchema, $objects));
        
        $this->prepNavigation();
        return new ViewModel([
            'inLanguage' => $inLanguage,
            'inLanguageName' => $inLanguageName,
            'schemata' => $schemata,
            'objects' => $objects,
        ]);
    }
    
    /**
     * Prepare the schema to print out in the view
     * @param BaseType $dictionarySchema
     * @param mixed[] $entryObjects
     * @return mixed[]
     */
    protected function combineSchema(BaseType $dictionarySchema, $entryObjects = [])
    {
        $schemata = [$dictionarySchema->toArray()];
        foreach ($entryObjects as $object) {
            if (isset($object['schema']) && $object['schema'] instanceof BaseType) {
                $schemata[] = $object['schema']->toArray();
            }
        }
        return $schemata;
    }
    
    public function redirectAfterEdit($id, $data = [], $form = null)
    {
        $inLanguage = \Locale::getPrimaryLanguage($data['locale']);
        if (isset($inLanguage)) {
            return $this->redirect()->toRoute('dictionary/inLanguage', ['inLanguage' => $inLanguage]);
        }
        return $this->redirect()->toRoute('dictionary');
    }
    
    public function redirectAfterCreate($newId, $data = [], $form = null)
    {
        return $this->redirectAfterEdit($newId, $data, $form);
    }
    
    protected function prepNavigation()
    {
        /** @var Navigation $nav */
        $nav = $this->services[Navigation::class];
        
        $table = $this->getSionTable();
        $availableLanguages = $table->getAvailableDictionaryLanguages();
        $dictionaryPage = $nav->findOneBy('route', 'dictionary');
        
        foreach ($availableLanguages as $inLanguage) {
            $url = $this->url()->fromRoute('dictionary/inLanguage', ['inLanguage' => $inLanguage]);
            $dictionaryPage->addPage([
                'label' => $table->getLanguageName($inLanguage),
                'uri' => $url,
                'id'    => 'dict_'.$inLanguage,
            ]);
        }
    }
}

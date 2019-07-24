<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Zend\View\Model\ViewModel;
use Zend\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Spatie\SchemaOrg\BaseType;
use Zend\Json\Json;

class DictionaryController extends SionController
{
    public function inLanguageAction()
    {
        $inLanguage = $this->params()->fromRoute('inLanguage');
        
        /** @var \Books\Model\DictionaryTable $table */
        $table = $this->getSionTable();
        
        $availableLanguages = $table->getAvailableDictionaryLanguages();
        $dictionary = null;
        $isInLanguageAvailable = false;
        foreach ($availableLanguages as $dict) {
            if ($inLanguage === $dict['inLanguage']) {
                $dictionary = $dict;
                $isInLanguageAvailable = true;
                break;
            }
        }
        if (!$isInLanguageAvailable) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)->addMessage('Language not found');
            return $this->redirect()->toRoute('dictionary');
        }
        $inLanguageName = $table->getLanguageName($inLanguage);
        
        $dictionarySchema = $table->getDictionarySchema($inLanguage);
        $objects = $table->queryObjects('dictionary-entry', ['locale' => $dictionary['locale'], 'isActive' => true]);
        $schemata = Json::encode($this->combineSchema($dictionarySchema, $objects));
        
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
}

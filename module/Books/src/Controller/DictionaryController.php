<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Zend\View\Model\ViewModel;

class DictionaryController extends SionController
{
    public function inLanguageAction()
    {
        /** @var \Books\Model\DictionaryTable $table */
        $table = $this->getSionTable();
        $objects = $table->queryObjects('dictionary-entry', ['locale' => 'es_ES', 'isActive' => true]);
        return new ViewModel([
            'objects' => $objects,
        ]);
    }
}

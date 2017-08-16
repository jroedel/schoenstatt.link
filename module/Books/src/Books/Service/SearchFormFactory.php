<?php
namespace Books\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Books\Model\LibraryTable;
use Books\Form\SearchForm;

/**
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class SearchFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
		/** @var LibraryTable $table **/
		$table= $serviceLocator->get ( 'Books\Model\LibraryTable' );

        $form = new SearchForm();
        $collections =  $table->getCollectionValueOptions();
        $form->get('collectionId')->setValueOptions($collections);
		return $form;
    }
}

<?php
namespace Books\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Books\Model\LibraryTable;
use Books\Form\ImportForm;

/**
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class ImportFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /** @var LibraryTable $table **/
		$table = $serviceLocator->get ( 'Books\Model\LibraryTable' );

		$libraries = $table->getLibraryValueOptions();
        $form = new ImportForm();
        //@todo filter out the libraries the user doesn't have access to edit
        $form->get('libraryId')->setValueOptions($libraries);
		return $form;
    }

}

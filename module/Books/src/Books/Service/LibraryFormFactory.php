<?php
namespace Books\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Books\Form\LibraryForm;
use Schoenstatt\Model\SchoenstattTable;
use Books\Model\LibraryTable;

/**
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class LibraryFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
		/** @var SchoenstattTable $schoenstattTable **/
		$schoenstattTable= $serviceLocator->get ( 'Schoenstatt\Model\SchoenstattTable' );

		$persons = $schoenstattTable->getPersonValueOptions();
		$config = $serviceLocator->get('Books\Config');

        $form = new LibraryForm();
        $form->get('filiationId')->setValueOptions($config['library_filiation_options']);
        $form->get('contactPersonId')->setValueOptions($persons);
        $form->get('mainShowDisplay')->setValueOptions(LibraryTable::MAIN_SHOW_DISPLAY_VALUE_OPTIONS);
		$form->get('defaultCheckoutPersonId')->setValueOptions($persons);
		return $form;
    }
}

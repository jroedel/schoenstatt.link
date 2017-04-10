<?php
namespace Books\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Books\Model\LibraryTable;
use Books\Form\CheckoutForm;
use Zend\Mvc\Application;
use Patres\Model\PatresTable;

/**
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class CheckoutFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /** @var LibraryTable $table **/
		$table = $serviceLocator->get ( 'Books\Model\LibraryTable' );

		/**
		 * @var Application $application
		 */
		$application = $serviceLocator->get('Application');
		$routeMatch = $application->getMvcEvent()->getRouteMatch();
		$libraryId = $routeMatch->getParam('library_id', null);

		$form = new CheckoutForm();
		$books = $table->getLibraryBookValueOptions(['libraryId' => $libraryId, 'isAvailable' => true]);
		$form->get('bookIds')->setValueOptions($books);

		//@todo factor PatresTable out
		/** @var PatresTable $patresTable **/
		$patresTable = $serviceLocator->get ( 'Patres\Model\PatresTable' );
        $persons = $patresTable->getPersonValueOptions();
        $form->get('personId')->setValueOptions($persons);

		return $form;
    }
}

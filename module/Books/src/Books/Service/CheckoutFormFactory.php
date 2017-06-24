<?php
namespace Books\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Books\Model\LibraryTable;
use Books\Form\CheckoutForm;
use Zend\Mvc\Application;

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
		//Don't require the book to be available, we'll just close the old checkout and check it out again
// 		$books = $table->getLibraryBookValueOptions(['libraryId' => $libraryId],// 'isAvailable' => true],
// 	        ['labelOption' => LibraryTable::BOOK_VALUE_OPTIONS_LABEL_ID]);
// 		$form->get('bookIds')->setValueOptions($books);

		$persons = $serviceLocator->get ( 'Schoenstatt\FathersValueOptions' );
        $form->get('personId')->setValueOptions($persons);

		return $form;
    }
}

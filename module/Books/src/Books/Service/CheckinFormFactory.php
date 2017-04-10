<?php
namespace Books\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Books\Model\LibraryTable;
use Zend\Mvc\Application;
use Books\Form\CheckinForm;

/**
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class CheckinFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return CreateTimelineEventForm
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

		$form = new CheckinForm();
		$books = $table->getLibraryBookValueOptions(['libraryId' => $libraryId, 'isCheckedOut' => true]);
		$form->get('bookIds')->setValueOptions($books);

		return $form;
    }
}

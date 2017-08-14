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
		$libraries = $table->getUnlinkedLibraries();
        if (!key_exists($libraryId, $libraries)) {
            throw new \Exception('Library not found');
        }
        $libraryOptions = $libraries[$libraryId]['options'];

        $schConfig = $serviceLocator->get ( 'Schoenstatt\Config' );
        if (!isset($schConfig['person_value_options_providers'])) {
            throw new \Exception('No person_value_options_providers set');
        }
        $providers = $schConfig['person_value_options_providers'];
        if (!isset($providers[$libraryOptions->checkoutPersonListKind]) ||
            !isset($providers[$libraryOptions->checkoutPersonListKind]['target']) ||
            !$serviceLocator->has($providers[$libraryOptions->checkoutPersonListKind]['target'])
        ) {
            throw new \Exception('Improper checkout person list kind configuration');
        }
		$form = new CheckoutForm();

		$persons = $serviceLocator->get ($providers[$libraryOptions->checkoutPersonListKind]['target']);
        $form->get('personId')->setValueOptions($persons);

		return $form;
    }
}

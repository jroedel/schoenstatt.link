<?php
namespace Books\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Books\Model\LibraryTable;

/**
 * Factory responsible of priming the LibraryTable service
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class LibraryTableServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $dbAdapter = $serviceLocator->get('Zend\Db\Adapter\Adapter');

		$config = $serviceLocator->get ( 'Books\Config' );
		/** @var  User $userService **/
		$userService = $serviceLocator->get('zfcuser_user_service');
		$user = $userService->getAuthService()->getIdentity();
		$actingUserId = $user ? $user->id : null;

		$table = new LibraryTable( $dbAdapter, $serviceLocator, $actingUserId, $config);

		/** @var \Zend\Mvc\Router\RouteMatch $routeMatch */
		$routeMatch = $serviceLocator->get('Application')->getMvcEvent()->getRouteMatch();
		$libraryId = $routeMatch->getParam('library_id');
		if (isset($libraryId)) {
            $table->setLibraryId($libraryId);
		}
		return $table;
    }
}

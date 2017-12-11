<?php
namespace Books\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Books\Mailing\BooksMailer;

/**
 * Factory responsible of priming the Mailer service
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class BooksMailerFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $table = $serviceLocator->get ( 'Books\Model\LibraryTable' );
        /** @var SchoenstattTable $schTable */
        $schTable = $serviceLocator->get('Schoenstatt\Model\SchoenstattTable');
		$translator = $serviceLocator->get ( 'translator' );
		$mailService = $serviceLocator->get ( 'acmailer.mailservice.default' );
		$config = $serviceLocator->get ( 'Config' );

		$mailer = new BooksMailer( $mailService, $translator, $config, $table, $schTable);
		return $mailer;
    }
}

<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Mailing\BooksMailer;

/**
 * Factory responsible of priming the Mailer service
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class BooksMailerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $table = $container->get('Books\Model\LibraryTable');
        /** @var SchoenstattTable $schTable */
        $schTable = $container->get('Schoenstatt\Model\SchoenstattTable');
        $translator = $container->get('translator');
        $mailService = $container->get('acmailer.mailservice.default');
        $config = $container->get('Config');

        $mailer = new BooksMailer($mailService, $translator, $config, $table, $schTable);
        return $mailer;
    }
}

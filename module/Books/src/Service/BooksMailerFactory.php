<?php
namespace Books\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Mailing\BooksMailer;
use Books\Model\LibraryTable;
use Schoenstatt\Model\SchoenstattTable;

/**
 * Factory responsible of priming the Mailer service
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class BooksMailerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $table = $container->get(LibraryTable::class);
        /** @var SchoenstattTable $schTable */
        $schTable = $container->get(SchoenstattTable::class);
        $translator = $container->get('translator');
        //the shared application transport, built from `smtp_options` by
        //SionModel\Service\MailTransportFactory
        $transport = $container->get('SionModel\MailTransport');
        $renderer = $container->get('ViewRenderer');
        $config = $container->get('Config');

        $mailer = new BooksMailer($transport, $renderer, $translator, $config, $table, $schTable);
        return $mailer;
    }
}

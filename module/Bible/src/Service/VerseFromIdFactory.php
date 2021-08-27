<?php
namespace Bible\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Bible\View\Helper\VerseFromId;
use Bible\Model\BibleTable;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class VerseFromIdFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $parentLocator = $container->getServiceLocator();
        /** @var BibleTable $table */
        $table = $parentLocator->get(BibleTable::class);
        $books = $table->getObjects('bible-book');
        $viewHelper = new VerseFromId($books);

        return $viewHelper;
    }
}

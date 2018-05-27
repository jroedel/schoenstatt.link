<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\View\Helper\FormatPublication;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class FormatPublicationFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $parentLocator = $container->getServiceLocator();
        $entityService = $parentLocator->get('SionModel\Service\EntitiesService');

        /** @var \Books\Model\PublicationsTable $table */
        $table = $parentLocator->get('Books\Model\PublicationsTable');
        $valueOptions = $table->getAuthorsValueOptions();

        $viewHelper = new FormatPublication($entityService, true);
        $viewHelper->setAuthorValueOptions($valueOptions);

        return $viewHelper;
    }
}

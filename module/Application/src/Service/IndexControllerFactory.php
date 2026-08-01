<?php
/**
 * BjyAuthorize Module (https://github.com/bjyoungblood/BjyAuthorize)
 *
 * @link https://github.com/bjyoungblood/BjyAuthorize for the canonical source repository
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Application\Authentication\Adapter\JsonPost;
use Application\Controller\IndexController;
use Laminas\Navigation\Navigation;
use Books\Model\PublicationsTable;
use Schoenstatt\Model\SchoenstattTable;
use Books\Model\EventTextTable;
use Books\Model\DictionaryTable;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $navigation = $container->get(Navigation::class);
        $publicationsTable = $container->get(PublicationsTable::class);
        $schoenstattTable = $container->get(SchoenstattTable::class);
        $eventTextTable = $container->get(EventTextTable::class);
        $dictionaryTable = $container->get(DictionaryTable::class);
        $helperPluginManager = $container->get('ViewHelperManager');
        $config = $container->get('Config');
        $controller = new IndexController(
            $navigation,
            $publicationsTable,
            $schoenstattTable,
            $eventTextTable,
            $dictionaryTable,
            $helperPluginManager,
            $config
        );
        return $controller;
    }

    /**
     * {@inheritDoc}
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return $this($serviceLocator, JsonPost::class);
    }
}

<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

/**
 * Factory responsible of constructing the central collection of AssociationKind specs
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class AssociationKindsServiceFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        if (!isset($config['schoenstatt']['association_kinds'])) {
            throw new \Exception('No association configuration defined!');
        }

        $kindsConfig = $config['schoenstatt']['association_kinds'];
        $plugins = $container->get('ViewHelperManager');
        $translateViewHelper = $plugins->get('translate');
        $translator = $translateViewHelper->getTranslator();
        $kindsService = new AssociationKindsService($kindsConfig, $translator, $config);
        return $kindsService;
    }
}

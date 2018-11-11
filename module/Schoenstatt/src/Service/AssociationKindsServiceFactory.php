<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Zend\I18n\View\Helper\Translate;

/**
 * Factory responsible of constructing the central collection of AssociationKind specs
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
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
        $config = $container->get('Schoenstatt\Config');
        if (!key_exists('association_kinds', $config)) {
            throw new \Exception('No association configuration defined!');
        }

        $kindsConfig = $config['association_kinds'];
        $plugins = $container->get('ViewHelperManager');
        $translateViewHelper = $plugins->get('translate');
        $translator = $translateViewHelper->getTranslator();
        $kindsService = new AssociationKindsService($kindsConfig, $translator);
        return $kindsService;
    }
}

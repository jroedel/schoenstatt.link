<?php
namespace Schoenstatt\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Laminas\Translator\TranslatorInterface;

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
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('Config');
        if (! isset($config['schoenstatt']['association_kinds'])) {
            throw new \Exception('No association configuration defined!');
        }

        $kindsConfig = $config['schoenstatt']['association_kinds'];
        //the one translator, asked for directly. This reached it through the `translate`
        //view helper until laminas-view was removed; the helper only ever handed back what
        //the container had put into it.
        $translator = $container->get(TranslatorInterface::class);
        $kindsService = new AssociationKindsService($kindsConfig, $translator, $config);
        return $kindsService;
    }
}

<?php
namespace Schoenstatt\Service;

use Psr\Container\ContainerInterface;
use SionModel\I18n\TranslatesMessages;

/**
 * Factory responsible of constructing the central collection of AssociationKind specs
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class AssociationKindsServiceFactory
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
        $translator = $container->get(TranslatesMessages::class);
        $kindsService = new AssociationKindsService($kindsConfig, $translator, $config);
        return $kindsService;
    }
}

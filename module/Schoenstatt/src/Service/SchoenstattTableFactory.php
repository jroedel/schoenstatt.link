<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Schoenstatt\Model\SchoenstattTable;
use JTranslate\Model\CountriesInfo;
use Zend\Db\Adapter\Adapter;

/**
 * Factory responsible of priming the SchoenstattTable service
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class SchoenstattTableFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $dbAdapter = $container->get(Adapter::class);

        $config = $container->get('Config');

        /** @var  User $userService **/
        $userService = $container->get('zfcuser_user_service');
        $user = $userService->getAuthService()->getIdentity();
        $actingUserId = $user ? $user->id : null;

        /** @var \JTranslate\Model\CountriesInfo */
        $countriesInfo = $container->get(CountriesInfo::class);
        
        $plugins = $container->get('ViewHelperManager');
        $translateViewHelper = $plugins->get('translate');
        $translator = $translateViewHelper->getTranslator();
        
        $table = new SchoenstattTable(
            $dbAdapter,
            $container,
            $actingUserId,
            $config,
            $countriesInfo,
            $translator
        );
        return $table;
    }
}

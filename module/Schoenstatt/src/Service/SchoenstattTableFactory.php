<?php
namespace Schoenstatt\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Schoenstatt\Model\SchoenstattTable;
use JTranslate\Model\CountriesInfo;
use Laminas\Db\Adapter\Adapter;
use JTranslate\Model\TranslationsTable;

/**
 * Factory responsible of priming the SchoenstattTable service
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
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

        $user = $container->get('JUser\AuthService')->getIdentity();
        $actingUserId = $user ? $user->id : null;

        /** @var \JTranslate\Model\CountriesInfo */
        $countriesInfo = $container->get(CountriesInfo::class);

        $plugins = $container->get('ViewHelperManager');
        $translateViewHelper = $plugins->get('translate');
        $translator = $translateViewHelper->getTranslator();
        $translationsTable = $container->get(TranslationsTable::class);

        $table = new SchoenstattTable(
            $dbAdapter,
            $container,
            $actingUserId,
            $config,
            $countriesInfo,
            $translator,
            $translationsTable
        );
        return $table;
    }
}

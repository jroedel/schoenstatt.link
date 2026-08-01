<?php
namespace Bible\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use ZfSnapGeoip\Service\Geoip;
use Bible\Provider\Rule\SpecialCountryRuleProvider;

class SpecialCountryRuleProviderFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var Geoip $geoip */
        $geoip = $container->get(Geoip::class);
        $config = $container->get('Config');

        $provider = new SpecialCountryRuleProvider($geoip, $config);
        return $provider;
    }
}

<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use JTranslate\Model\CountriesInfo;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class CountryValueOptionsFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
		/** @var CountriesInfo $countries */
		$countries = $serviceLocator->get ( 'CountriesInfo' );
		$countryNames = $countries->getTranslatedCountryNames(\Locale::getPrimaryLanguage(\Locale::getDefault()));
		asort($countryNames);

		return $countryNames;
    }
}

<?php
namespace Books\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Matriphe\ISO639\ISO639;

/**
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class LanguagesValueOptionsFactory extends ISO639 implements FactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
		$languages = $this->getLanguageValueOptions();
		$languages['xx'] = '(none)';
		return $languages;
    }

    /**
     * Fetch value options for a Select form element
     * @param string $labelsInOwnLanguage
     * @return string[]
     */
    protected function getLanguageValueOptions($labelsInOwnLanguage = false)
    {
        $valueOptions = [];
        $labelField = $labelsInOwnLanguage ? 5 : 4;
        foreach ($this->languages as $languageRow) {
            $valueOptions[$languageRow[0]] = $languageRow[$labelField];
        }
        return $valueOptions;
    }
}

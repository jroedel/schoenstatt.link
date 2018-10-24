<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Matriphe\ISO639\ISO639;

/**
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class LanguagesValueOptionsFactory extends ISO639 implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
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

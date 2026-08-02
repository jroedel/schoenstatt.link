<?php
namespace Books\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Form\PublicationsSearchForm;
use SionModel\I18n\LanguageSupport;

/**
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class PublicationsSearchFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $languageInfo = new LanguageSupport();
        $lang = \Locale::getPrimaryLanguage(\Locale::getDefault());
        $languages = $languageInfo->getLanguageNames($lang);

        $form = new PublicationsSearchForm();
        $form->get('inLanguage')->setValueOptions($languages);
        return $form;
    }
}

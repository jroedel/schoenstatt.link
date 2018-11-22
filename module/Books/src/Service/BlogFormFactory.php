<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Form\BookForm;

/**
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class BlogFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var LibraryTable $table **/
        $table = $container->get('Books\Model\LibraryTable');

        $languages = $this->getLanguageValueOptions();
        $keywords = $table->getKeywordsValueOptions();

        $form = new BookForm();
        $form->get('inLanguage')->setValueOptions($languages);
        $form->get('keywords')->setValueOptions($keywords);
        return $form;
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

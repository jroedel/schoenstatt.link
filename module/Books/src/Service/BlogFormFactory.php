<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Model\EventTextTable;
use Books\Form\BlogForm;

/**
 * @author Jeff Ro <webmaster@schoenstatt.link>
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
        /** @var EventTextTable $table **/
        $table = $container->get(EventTextTable::class);

        $keywords = $table->getTextTagsOptions(EventTextTable::TEXT_KIND_BLOG);

        $form = new BlogForm();
        $form->get('tags')->setValueOptions($keywords);
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

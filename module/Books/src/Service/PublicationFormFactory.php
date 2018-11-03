<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Form\PublicationForm;
use Books\Model\PublicationsTable;
use Matriphe\ISO639\ISO639;

/**
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class PublicationFormFactory extends ISO639 implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var PublicationsTable $table **/
        $table = $container->get(PublicationsTable::class);

        $authors = $table->getAuthorsValueOptions();
        $authorsOnlyPersons = $table->getAuthorsNoAssociationsValueOptions();
        $publishers = $table->getPublishersValueOptions();
        $config = $container->get('Books\Config');

        $languages = $this->getLanguageValueOptions();//$config['language_value_options'];

        $bookFormats = $config['book_format_type_value_options'];
        $urlLabels = $config['url_label_value_options'];
        $keywords = $table->getKeywordsValueOptions();
//      $adminTags = $table->getPersonAdminTags();
        $editions = $table->getEditionValueOptions(true);
        $allEditions = $table->getEditionValueOptions(false);

        $categories = $table->getCategoryValueOptions();
        $resourceIdValueOptions = $config['publication_resource_id_options'];
        $resourceIdValue = $config['publication_resource_id_default_option'];

        $form = new PublicationForm();
        $form->get('inLanguage')->setValueOptions($languages);
        $form->get('mainPublicationId')->setValueOptions($editions);
        $form->get('translatedFromPublicationId')->setValueOptions($allEditions);
        $form->get('authorsAll')->setValueOptions($authors);
        $form->get('editorsAll')->setValueOptions($authors);
        $form->get('translatorsAll')->setValueOptions($authorsOnlyPersons);
//         $form->get('illustratorsAll')->setValueOptions($authorsOnlyPersons);
        $form->get('keywords')->setValueOptions($keywords);
        $form->get('publisher')->setValueOptions($publishers);
        $form->get('bookFormatType')->setValueOptions($bookFormats);
        $form->get('url1Label')->setValueOptions($urlLabels);
        $form->get('url2Label')->setValueOptions($urlLabels);
        $form->get('url3Label')->setValueOptions($urlLabels);
        $form->get('categoryId')->setValueOptions($categories);
        $form->get('resourceId')->setValueOptions($resourceIdValueOptions)
            ->setValue($resourceIdValue);

//      $form->get('adminTags')->setValueOptions($adminTags);
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

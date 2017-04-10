<?php
namespace Books\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Books\Form\PublicationForm;
use Books\Model\PublicationsTable;
use Matriphe\ISO639\ISO639;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class PublicationFormFactory extends ISO639 implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return CreateTimelineEventForm
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /** @var PublicationsTable $table **/
		$table = $serviceLocator->get ( 'Books\Model\PublicationsTable' );
		/**
		 * @var Application $application
		 */
		$application = $serviceLocator->get('Application');
		$routeMatch = $application->getMvcEvent()->getRouteMatch();
		$libraryId = $routeMatch->getParam('library_id', null);
		if (!is_null($libraryId)) {
		    $table->setLibraryId($libraryId);
		}

		$authors = $table->getAuthorsValueOptions();
		$publishers = $table->getPublishersValueOptions();
		$config = $serviceLocator->get('Books\Config');

		$languages = $this->getLanguageValueOptions();//$config['language_value_options'];

		$bookFormats = $config['book_format_type_value_options'];
		$urlLabels = $config['url_label_value_options'];
		$keywords = $table->getKeywordsValueOptions();
// 		$personTags = $serviceLocator->get ( 'Schoenstatt\PersonTagsValueOptions' );
// 		$adminTags = $table->getPersonAdminTags();

		$form = new PublicationForm();
		$form->get('inLanguage')->setValueOptions($languages);
		$form->get('authors')->setValueOptions($authors);
		$form->get('keywords')->setValueOptions($keywords);
		$form->get('publisher')->setValueOptions($publishers);
		$form->get('bookFormatType')->setValueOptions($bookFormats);
// 		$form->get('publishingPlace')->setValueOptions($publishingPlaces);
		$form->get('url1Label')->setValueOptions($urlLabels);
		$form->get('url2Label')->setValueOptions($urlLabels);
		$form->get('url3Label')->setValueOptions($urlLabels);
// 		$form->get('adminTags')->setValueOptions($adminTags);
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

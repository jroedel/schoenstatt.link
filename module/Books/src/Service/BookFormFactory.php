<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Matriphe\ISO639\ISO639;
use Books\Model\LibraryTable;
use Books\Form\BookForm;
use Books\Model\PublicationsTable;

/**
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class BookFormFactory extends ISO639 implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var LibraryTable $table **/
		$table = $container->get ( 'Books\Model\LibraryTable' );

		/** @var \Zend\Router\RouteMatch $routeMatch */
		$routeMatch = $container->get('Application')->getMvcEvent()->getRouteMatch();
		$libraryId = $routeMatch->getParam('library_id');
		if (!isset($libraryId)) {
		    $bookId = $routeMatch->getParam('book_id');
		    $object = $table->getSimpleBook($bookId);
		    $libraryId = isset($object['libraryId']) ? $object['libraryId'] : null;
		}
		if (isset($libraryId)) {
		    $table->setLibraryId($libraryId);
		} else {
		    throw new \Exception('A BookForm instance can only be formed with reference to a particular library.');
		}
		$libraryOptions = $table->getLibraryOptions();

		$authors = $table->getAuthorsValueOptions($libraryId);
		$collections = $table->getCollectionValueOptions($libraryId);
		$publishers = $table->getPublishersValueOptions();
		$config = $container->get('Books\Config');

		$languages = $this->getLanguageValueOptions();
		$keywords = $table->getKeywordsValueOptions();
		$adminTags = $table->getAdminKeywordsValueOptions();
        $categories = $table->getCategoryValueOptions();

        /** @var PublicationsTable */
        $publicationsTable = $container->get('Books\Model\PublicationsTable');
        $allEditions = $publicationsTable->getEditionValueOptions(false);

        $form = new BookForm();
        $form->get('inLanguage')->setValueOptions($languages);
        $form->get('publicationId')->setValueOptions($allEditions);
        $form->get('authors')->setValueOptions($authors);
        $form->get('collectionId')->setValueOptions($collections);
		$form->get('keywords')->setValueOptions($keywords);
		$form->get('publisher')->setValueOptions($publishers);
		$form->get('category')->setValueOptions($categories);
		$form->get('adminTags')->setValueOptions($adminTags);

		$form->setLibraryOptions($libraryOptions);
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

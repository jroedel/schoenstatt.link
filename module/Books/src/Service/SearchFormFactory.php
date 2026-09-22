<?php
namespace Books\Service;

use Psr\Container\ContainerInterface;
use Books\Form\SearchForm;

/**
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class SearchFormFactory
{
    /**
     * Create an object
     *
     * The 'collectionId' value options are deliberately left empty here. They are
     * library-specific, and this form is built when the controller is constructed,
     * before a route match is available. The consuming action is responsible for
     * populating them once it knows which library is being viewed.
     * @see \Books\Controller\LibrariesController::showAction()
     *
     * What is passed instead is the id of every collection there is, as a fallback
     * domain for the input filter. It must not become the element's value options:
     * this form renders on a library page, and a collection picker offering another
     * library's collections is a worse bug than the one being fixed. Note that
     * LibraryTable resolves without a route match — if that ever stops being true
     * this factory 500s every action of the controller it is injected into, long
     * before any route is matched.
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var \Books\Model\LibraryTable $table */
        $table = $container->get(\Books\Model\LibraryTable::class);

        //Every collection, not this library's — deliberately, because there is no library yet.
        //getObjects() needs no libraryId and is entity-cached, so this does not add a query per
        //request; the action's own narrower list replaces it a moment later either way.
        return new SearchForm(null, array_keys($table->getObjects('collection')));
    }
}

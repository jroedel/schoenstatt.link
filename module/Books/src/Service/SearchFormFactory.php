<?php
namespace Books\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Form\SearchForm;

/**
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class SearchFormFactory implements FactoryInterface
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
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new SearchForm();
    }
}

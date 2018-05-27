<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Form\PublicationsSearchForm;

/**
 * @author Jeff Roedel <webmaster@schoenstatt.link>
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
		$languages = $container->get('Books\LanguagesValueOptions');

		$form = new PublicationsSearchForm();
		$form->get('inLanguage')->setValueOptions($languages);
		return $form;
    }
}

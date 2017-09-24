<?php
namespace Books\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Books\Form\PublicationsSearchForm;

/**
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class PublicationsSearchFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
		$languages = $serviceLocator->get('Books\LanguagesValueOptions');

		$form = new PublicationsSearchForm();
		$form->get('inLanguage')->setValueOptions($languages);
		return $form;
    }
}

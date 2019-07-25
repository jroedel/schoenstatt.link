<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Model\MusicTable;
use SionModel\I18n\LanguageSupport;
use Books\Form\CompositionForm;

/**
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class CompositionFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var MusicTable $table **/
        $table = $container->get(MusicTable::class);


        $languageSupport = $container->get(LanguageSupport::class);
        $languages = $languageSupport->getLanguageValueOptions();
        $tags = $table->getTagsValueOptions();
        $compositions = $table->getCompositionValueOptions();
        $authors = $table->getAuthorTextValueOptions();

        $form = new CompositionForm();
        $form->get('inLanguage')->setValueOptions($languages);
        $form->get('derivedFromCompositionId')->setValueOptions($compositions);
        $form->get('composersAll')->setValueOptions($authors);
        $form->get('lyricistsAll')->setValueOptions($authors);
        $form->get('tags')->setValueOptions($tags);
        return $form;
    }
}

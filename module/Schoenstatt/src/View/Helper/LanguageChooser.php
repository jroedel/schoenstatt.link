<?php
// Schoenstatt/View/Helper/LanguageChooser.php

namespace Schoenstatt\View\Helper;

use Zend\View\Helper\AbstractHelper;

class LanguageChooser extends AbstractHelper
{
    protected $languageChooser = [
        'en' => ['en', 'es', 'de', 'pt', 'fr', 'it'],
        'es' => ['es', 'en', 'pt', 'it', 'de', 'fr'],
        'de' => ['de', 'en', 'es', 'pt', 'fr', 'it'],
        'pt' => ['pt', 'es', 'de', 'en', 'it', 'fr'],
        'fr' => ['fr', 'en', 'de', 'es', 'it', 'pt'],
        'it' => ['it', 'es', 'es', 'pt', 'it', 'de'],
    ];

    /**
     *
     * @param string[] $availableLanguages
     */
    public function __invoke($availableLanguages)
    {
        if (count($availableLanguages) === 0) {
            return null;
        }
        $theLanguage = \Locale::getPrimaryLanguage(\Locale::getDefault());
        if (! key_exists($theLanguage, $this->languageChooser)) {
            if (key_exists($theLanguage, $availableLanguages)) {
                return $theLanguage;
            }
            return $availableLanguages[0];
        }
        foreach ($this->languageChooser[$theLanguage] as $value) {
            if (in_array($value, $availableLanguages)) {
                return $value;
            }
        }
        return $availableLanguages[0];
    }
}

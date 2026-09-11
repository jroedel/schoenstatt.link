<?php
// SionModel/View/Helper/FormatUrlObject.php

namespace Books\View\Helper;

use Laminas\Translator\TranslatorInterface;
use SionModel\Uri\Http;
use SionModel\View\Helper\FormatUrlObject;

class FormatPublicationUrlObject extends FormatUrlObject
{
    /**
     * The parent became a plain class when the view helpers were ported, so `$this->view`
     * is gone and the translator arrives here instead. Null renders the label in its source
     * language, which is what a catalog miss gives anyway.
     */
    public function __construct(private readonly ?TranslatorInterface $translator = null)
    {
    }

    public function __invoke($url, $openInNewTab = true)
    {
        if (is_array($url) && key_exists('label', $url) && ! is_null($url['label']) &&
            in_array($url['label'], ['Borrow', 'Purchase', 'Download'])
        ) {
            $urlObject = new Http($url['url']);
            $phrase      = $url['label'] . " at %s";
            $labelFormat = null === $this->translator ? $phrase : $this->translator->translate($phrase);
            $label = sprintf($labelFormat, $urlObject->getHost());
        } else {
            return parent::__invoke($url, $openInNewTab);
        }
        if ($openInNewTab) {
            $format = "<a href=\"%s\" target=\"_blank\">%s</a>";
        } else {
            $format = "<a href=\"%s\">%s</a>";
        }
        $return = sprintf($format, $url['url'], $label);
        return $return;
    }
}

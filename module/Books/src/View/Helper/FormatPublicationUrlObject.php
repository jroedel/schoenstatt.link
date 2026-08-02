<?php
// SionModel/View/Helper/FormatUrlObject.php

namespace Books\View\Helper;

use SionModel\View\Helper\FormatUrlObject;
use Laminas\Uri\Http;

class FormatPublicationUrlObject extends FormatUrlObject
{
    public function __invoke($url, $openInNewTab = true)
    {
        if (is_array($url) && key_exists('label', $url) && ! is_null($url['label']) &&
            in_array($url['label'], ['Borrow', 'Purchase', 'Download'])
        ) {
            $urlObject = new Http($url['url']);
            $labelFormat = $this->view->translate($url['label'] . " at %s");
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

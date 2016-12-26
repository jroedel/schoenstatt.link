<?php
// Schoenstatt/View/Helper/ClipboardButton.php

namespace Schoenstatt\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Zend\Math\Rand;

class ClipboardButton extends AbstractHelper
{
    protected $buttonIds = array();
    protected $scriptIncluded = false;
	public function __construct()
	{
	}

	public function __invoke()
	{
	    return $this;
	}

    public function clipboardWidget($label, $content, $buttonText)
    {
        if (!$this->scriptIncluded) {
            $this->view->headScript()->appendFile($this->view->basePath() . '/'. $this->view->asset('js/clipboard.min.js'));
            $this->scriptIncluded = true;
        }
        $randVal = Rand::getInteger(10000, 99999);
        $textareaId = 'clipboard'.$randVal;
        $buttonId = 'button'.$randVal;
        $this->buttonIds[] = array(
            'textarea'  => $textareaId,
            'button'    => $buttonId,
        );

        $return = "<div class=\"form-group\" style=\"display: none;\">
	<label for=\"$textareaId\">$label</label>
    <textarea id=\"$textareaId\" rows=\"3\" class=\"form-control\">$content</textarea>
</div>
<button class=\"btn btn-default btn-clipboard\" id=\"$buttonId\" data-clipboard-target=\"#$textareaId\">$buttonText</button>";
        echo $return;
    }

    public function writeScript()
    {
        if (empty($this->buttonIds)) {
            return '';
        }
        $return = "<script>
$(function() {
var \$emailParent = $(\"#emails\").parent();
var clipboard = new Clipboard('.btn-clipboard'); ";
        foreach ($this->buttonIds as $value) {
            $return.=sprintf("$(\"#%s\").click(function() { $(\"#%s\").parent().show();});", $value['button'], $value['textarea']);
        }
        $return.="});</script>";
        return $return;
    }
}

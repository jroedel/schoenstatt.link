<?php
namespace Books\View\Helper;

use Zend\View\Helper\AbstractHelper;

/**
 * File Size View Helper
 *
 * @category Helper
 * @author   Chuck "MANCHUCK" Reeves <chuck@manchuck.com>
 */
class FileSize extends AbstractHelper
{
    protected $count = 0;
    public function __invoke($value)
    {
        $value = abs((int) $value);
        if ($value < 1) {
            return 'n/a';
        }
        $sizes = array('Bytes', 'KB', 'MB', 'GB', 'TB');
        $power = floor(log($value) / log(1024));
        return sprintf(
            '%01.1f %s',
            $value / pow(1024, $power),
            $sizes[$power]
        );
    }
}

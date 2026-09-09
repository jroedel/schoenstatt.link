<?php
namespace Books\View\Helper;


/**
 * File Size View Helper
 *
 * @category Helper
 * @author   Chuck "MANCHUCK" Reeves <chuck@manchuck.com>
 */
class FileSize
{
    protected $count = 0;
    public function __invoke($value)
    {
        $value = abs((int) $value);
        if ($value < 1) {
            return 'n/a';
        }
        $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($value) / log(1024));
        return sprintf(
            '%01.1f %s',
            $value / pow(1024, $power),
            $sizes[$power]
        );
    }
}

<?php
namespace Books\Filter;

use SionModel\Filter\AbstractFilter;
use SionModel\Filter\ToAscii;

class FourCharUCase extends AbstractFilter
{
    protected $toAscii;

    /**
     * Output a four character string. First we transliterate the string to ascii,
     * then we send it to uppercase and select only numbers and capital letters.
     * Spaces are padded on the end if we have less than 4 chars
     * {@inheritDoc}
     * @see \SionModel\Filter\FilterInterface::filter()
     */
    public function filter($value)
    {
        if (! isset($value) || ! is_string($value)) {
            return '    ';
        }
        if (! isset($this->toAscii)) {
            $this->toAscii = new ToAscii();
        }
        $value = $this->toAscii->filter($value);
        $value = strtoupper($value);
        $split = str_split($value);

        $i = 0;
        $result = "";
        while (strlen($result) < 4 && $i < count($split)) {
            $ord = ord($split[$i]);
            if (($ord >= 48 && $ord <= 57)
                || ($ord >= 65 && $ord <= 90)
            ) {
                $result .= chr($ord);
            }
            $i++;
        }

        //fill spaces at the end
        while (strlen($result) < 4) {
            $result .= " ";
        }
        return $result;
    }

    protected function str_split_unicode($str, $length = 1)
    {
        if (mb_strlen($str, 'UTF-8') === strlen($str)) {
            return str_split($str, $length);
        }

        $tmp = preg_split('~~u', $str, -1, PREG_SPLIT_NO_EMPTY);
        if ($length > 1) {
            $chunks = array_chunk($tmp, $length);
            foreach ($chunks as $i => $chunk) {
                $chunks[$i] = join('', (array) $chunk);
            }
            $tmp = $chunks;
        }
        return $tmp;
    }
}

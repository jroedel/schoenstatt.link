<?php
namespace Books\Filter;

use Laminas\Filter\AbstractFilter;

class Printf extends AbstractFilter
{
    /**
     * @see https://stackoverflow.com/a/446599
     * {@inheritDoc}
     * @see \Laminas\Filter\FilterInterface::filter()
     */
    public function filter($value)
    {
        $matches = explode(' ', $value);
        $validInput = true;

        foreach ($matches as $m) {
            // Check if a slice contains %$[number] as it indicates a sprintf format
            if (preg_match('/[%\d\$]+/', $m) > 0) {
                // Match found. Now check if its a valid sprintf format
                if ($validInput === false || preg_match('/^%(?:\d+\$)?[dfsu]$/u', $m) === 0) {   // no match found
                    $validInput = false;
                    break; // Invalid sprintf format found. Abort
                }
            }
        }
        return $validInput;
    }
}

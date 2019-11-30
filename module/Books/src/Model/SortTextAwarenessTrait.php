<?php
namespace Books\Model;

use Books\Filter\SortText;

trait SortTextAwarenessTrait
{
    /**
     * A format string to be passed to sprintf to generate each book's sortText
     * @see https://secure.php.net/manual/en/function.sprintf.php
     * @var string $sortTextFormat
     */
    public $sortTextFormat;
    /**
     * Text to be set as placeholder for call numbers; won't be translated
     * @var string $callNumberPlaceholder
     */
    public $callNumberPlaceholder;
    /**
     * Text to place below call number input field
     * @var string $callNumberHelpText
     */
    public $callNumberHelpText;
    /**
     * @var string $callNumberExplanation
     */
    public $callNumberExplanation;
    /**
     * @var bool $requireCallNumbers
     */
    public $requireCallNumbers = false;
    /**
     * @todo The regular expression to parameterize the call number, especially for use with label lines
     * @var string $callNumberRegex
     */
    public $callNumberRegex;
    /**
     * @todo Should the library enforce the call number regex while editing/importing/creating books?
     * @var bool $enforceCallNumberRegex
     */
    public $enforceCallNumberRegex = false;
    
    /**
     * Get a filter for formulating sort text strings from a book object
     * @return \Books\Filter\SortText|NULL
     */
    public function getSortTextFilter()
    {
        if (isset($this->callNumberRegex) && isset($this->sortTextFormat)) {
            return new SortText($this->callNumberRegex, $this->sortTextFormat);
        } elseif (property_exists($this, 'libraryOptions') 
            && isset($this->libraryOptions)
            && $this->libraryOptions instanceof LibraryOptions
            && $this->libraryOptions->callNumberRegex
            && $this->libraryOptions->sortTextFormat
        ) {
            $filter = new SortText($this->libraryOptions->callNumberRegex, $this->libraryOptions->sortTextFormat);
            return $filter;
        }
        var_dump("no filter for ".$this->name);
        var_dump($this->callNumberRegex);
        var_dump($this->sortTextFormat);
        return null;
    }
}

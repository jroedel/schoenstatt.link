<?php
namespace Books\Model;

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
    
    public function getSortTextFilter()
    {
        
    }
}

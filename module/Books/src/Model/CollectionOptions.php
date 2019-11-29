<?php
namespace Books\Model;

use Zend\Stdlib\ArraySerializableInterface;

class CollectionOptions implements ArraySerializableInterface
{
    use SortTextAwarenessTrait;
    
    /**
     * @var int $collectionId
     */
    public $collectionId;
    /**
     * @var int $libraryId
     */
    public $libraryId;
    /**
     * Instance of library options
     * @var LibraryOptions $library
     */
    public $libraryOptions;
    /**
     * @var string $name
     */
    public $name;
    /**
     * A short version of the name to be used in the sortTextFormat
     * @var string $abbreviation
     */
    public $abbreviation;
    /**
     * @var string $description
     */
    public $description;
    /**
     * What should be displayed on the index show page?
     * @var string $mainShowDisplay
     */
    public $mainShowDisplay;
    /**
     * The 3 label lines indicate how to format a label for the spine of a book
     * @var string $labelLine1
     */
    public $labelLine1;
    /**
     * @var string $labelLine2
     */
    public $labelLine2;
    /**
     * @var string $labelLine3
     */
    public $labelLine3;
    /**
     * How many days should a normal checkout be?
     * @var int $defaultCheckoutTimePeriodInDays
     */
    public $defaultCheckoutTimePeriodInDays;
    /**
     * The resource from which to view permissions, usually just taken from library
     * @var string $resourceId
     */
    public $resourceId;
    /**
     * @var bool $isActive
     */
    public $isActive = true;

    public function __construct(array $array)
    {
        $this->exchangeArray($array);
    }

    /**
     * Exchange internal values from provided array
     *
     * @param  array $array
     * @return void
     */
    public function exchangeArray(array $array)
    {
        /** @var LibraryOptions $libraryOptions */
        $libraryOptions = isset($array['library']) && $array['library'] instanceof LibraryOptions
            ? $array['library'] : null;
        $this->libraryOptions = !is_null($libraryOptions) ? $libraryOptions : null;
        $this->collectionId = isset($array['collectionId']) ? $array['collectionId'] : null;
        $this->libraryId = isset($array['libraryId']) ? $array['libraryId']
            : ($libraryOptions ? $libraryOptions->libraryId : null);
        $this->name = isset($array['name']) ? $array['name'] : null;
        $this->abbreviation = isset($array['abbreviation']) ? $array['abbreviation'] : null;
        $this->description = isset($array['description']) ? $array['description'] : null;
        $this->sortTextFormat = isset($array['sortTextFormat']) ? $array['sortTextFormat']
            : ($libraryOptions ? $libraryOptions->sortTextFormat : null);
        $this->callNumberHelpText = isset($array['callNumberHelpText']) ? $array['callNumberHelpText']
            : ($libraryOptions ? $libraryOptions->callNumberHelpText : null);
        $this->callNumberExplanation = isset($array['callNumberExplanation']) ? $array['callNumberExplanation']
            : ($libraryOptions ? $libraryOptions->callNumberExplanation : null);
        $this->mainShowDisplay = isset($array['mainShowDisplay']) ? $array['mainShowDisplay']
            : LibraryTable::MAIN_SHOW_DISPLAY_DEFAULT;
        $this->requireCallNumbers = isset($array['requireCallNumbers']) ? $array['requireCallNumbers']
            : ($libraryOptions ? $libraryOptions->requireCallNumbers : false);
        $this->callNumberRegex = isset($array['callNumberRegex']) ? $array['callNumberRegex']
            : ($libraryOptions ? $libraryOptions->callNumberRegex : null);
        $this->enforceCallNumberRegex= isset($array['enforceCallNumberRegex'])
            ? $array['enforceCallNumberRegex']
            : ($libraryOptions ? $libraryOptions->enforceCallNumberRegex : false);
        $this->labelLine1 = isset($array['labelLine1']) ? $array['labelLine1']
            : ($libraryOptions ? $libraryOptions->labelLine1 : null);
        $this->labelLine2 = isset($array['labelLine2']) ? $array['labelLine2']
            : ($libraryOptions ? $libraryOptions->labelLine2 : null);
        $this->labelLine3 = isset($array['labelLine3']) ? $array['labelLine3']
            : ($libraryOptions ? $libraryOptions->labelLine3 : null);
        $this->defaultCheckoutTimePeriodInDays = isset($array['defaultCheckoutTimePeriodInDays'])
            ? $array['defaultCheckoutTimePeriodInDays']
            : ($libraryOptions
                ? $libraryOptions->defaultCheckoutTimePeriodInDays
                : LibraryTable::DEFAULT_CHECKOUT_TIME_PERIOD_IN_DAYS);
        $this->resourceId = isset($array['resourceId']) ? $array['resourceId'] : null;
        $this->isActive = isset($array['isActive']) ? $array['isActive'] : true;
    }

    /**
     * Return an array representation of the object
     *
     * @return array
     */
    public function getArrayCopy()
    {
        return get_object_vars($this);
    }

    /**
     * Return a list of problem objects with the current library
     */
    public function getProblems()
    {
        //@todo getProblems()
    }
}

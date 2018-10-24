<?php
namespace Books\Model;

use Zend\Stdlib\ArraySerializableInterface;

class LibraryOptions implements ArraySerializableInterface
{
    const DEFAULT_CHECKOUT_PERSON_LIST_KIND = 'all-borrowers';
    /**
     * @var int $libraryId
     */
    public $libraryId;
    /**
     * @var string $name
     */
    public $name;
    /**
     * @var string $description
     */
    public $description;
    /**
     * @var int $filiationId
     */
    public $filiationId;
    /**
     * @var int $contactPersonId
     */
    public $contactPersonId;
    /**
     * @var string $contactEmail
     */
    public $contactEmail;
    /**
     * @todo What should be displayed on the index show page?
     * @var string $mainShowDisplay
     */
    public $mainShowDisplay;
    /**
     * @todo Does the library use collections?
     * @var bool $useCollections
     */
    public $useCollections = false;
    /**
     * @todo Allow books with no assigned collection (only applicable if useCollections is true)
     * @var bool $allowCollectionlessBooks
     */
    public $allowCollectionlessBooks = true;
    /**
     * @todo Which is the main collection?
     * Not required
     * @var int $mainCollectionId
     */
    public $mainCollectionId;
    /**
     * @var bool $requireCallNumbers
     */
    public $requireCallNumbers = false;
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
     * The ACL role to grant permission to open the checkout form
     * @var string $checkoutBooksRole
     */
    public $checkoutBooksRole;
    /**
     * The ACL role to grant permission to view the library and its books
     * @var string $viewRole
     */
    public $viewRole = 'lib_user';
    /**
     * @todo The 3 label lines indicate how to format a label for the spine of a book
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
     * @todo What text should appear on generated barcodes?
     * @var string $barcodeText
     */
    public $barcodeText;
    /**
     * @var bool $createCheckoutsIfCheckingInANonCheckedOutBook
     */
    public $createCheckoutsIfCheckingInANonCheckedOutBook = true;
    /**
     * @var int $defaultCheckoutPersonId
     */
    public $defaultCheckoutPersonId;
    /**
     * How many days should a normal checkout be?
     * @var int $defaultCheckoutTimePeriodInDays
     */
    public $defaultCheckoutTimePeriodInDays;
    /**
     * How many times can a book be renewed?
     * @var int|null
     */
    public $maximumBookRenewals;
    /**
     * Enable checkouts?
     * @var bool $defaultCheckoutTimePeriodInDays
     */
    public $enableCheckouts = false;
    /**
     * Should this library be listed without authentication?
     * @var bool $defaultCheckoutTimePeriodInDays
     */
    public $isPublicallyListed = false;
    /**
     * Which list of borrowers should we show?
     * @var string $defaultCheckoutTimePeriodInDays
     */
    public $checkoutPersonListKind = self::DEFAULT_CHECKOUT_PERSON_LIST_KIND;
    /**
     * @var bool $isActive
     */
    public $isActive = true;
    /**
     * @var int $nextWithinLibraryId
     */
    public $nextWithinLibraryId;
    /**
     * Array of collection options
     * @var CollectionOptions[] $collections
     */
    public $collections = [];

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
        $this->libraryId = isset($array['libraryId']) ? $array['libraryId'] : null;
        $this->name = isset($array['name']) ? $array['name'] : null;
        $this->description = isset($array['description']) ? $array['description'] : null;
        $this->callNumberPlaceholder = isset($array['callNumberPlaceholder'])
            ? $array['callNumberPlaceholder'] : null;
        $this->callNumberHelpText = isset($array['callNumberHelpText']) ? $array['callNumberHelpText'] : null;
        $this->callNumberExplanation = isset($array['callNumberExplanation']) ? $array['callNumberExplanation'] : null;
        $this->filiationId = isset($array['filiationId']) ? $array['filiationId'] : null;
        $this->contactPersonId = isset($array['contactPersonId']) ? $array['contactPersonId'] : null;
        $this->contactEmail = isset($array['contactEmail']) ? $array['contactEmail'] : null;
        $this->mainShowDisplay = isset($array['mainShowDisplay'])
            ? $array['mainShowDisplay'] : LibraryTable::MAIN_SHOW_DISPLAY_DEFAULT;
        $this->useCollections = isset($array['useCollections']) ? $array['useCollections'] : false;
        $this->allowCollectionlessBooks = isset($array['allowCollectionlessBooks'])
            ? (bool)$array['allowCollectionlessBooks'] : true;
        $this->mainCollectionId = isset($array['mainCollectionId']) ? (int)$array['mainCollectionId'] : null;
        $this->requireCallNumbers = isset($array['requireCallNumbers']) ? (bool)$array['requireCallNumbers'] : false;
        $this->callNumberPlaceholder = isset($array['callNumberPlaceholder'])
            ? (string)$array['callNumberPlaceholder'] : null;
        $this->callNumberRegex = isset($array['callNumberRegex']) ? $array['callNumberRegex'] : null;
        $this->enforceCallNumberRegex = isset($array['enforceCallNumberRegex'])
            ? $array['enforceCallNumberRegex'] : false;

        $this->labelLine1 = isset($array['labelLine1']) ? $array['labelLine1'] : null;
        $this->labelLine2 = isset($array['labelLine2']) ? $array['labelLine2'] : null;
        $this->labelLine3 = isset($array['labelLine3']) ? $array['labelLine3'] : null;
        $this->barcodeText = isset($array['barcodeText']) ? $array['barcodeText'] : null;
        $this->createCheckoutsIfCheckingInANonCheckedOutBook =
            isset($array['createCheckoutsIfCheckingInANonCheckedOutBook'])
                ? $array['createCheckoutsIfCheckingInANonCheckedOutBook'] : true;
        //@todo find a way to force a value here if createCheckoutsIfCheckingInANonCheckedOutBook is true
        $this->defaultCheckoutPersonId = isset($array['defaultCheckoutPersonId'])
            ? $array['defaultCheckoutPersonId'] : null;
        $this->defaultCheckoutTimePeriodInDays = isset($array['defaultCheckoutTimePeriodInDays'])
            ? $array['defaultCheckoutTimePeriodInDays'] : LibraryTable::DEFAULT_CHECKOUT_TIME_PERIOD_IN_DAYS;
        $this->enableCheckouts = isset($array['enableCheckouts']) ? (bool)$array['enableCheckouts'] : false;
        $this->isPublicallyListed = isset($array['isPublicallyListed'])
            ? (bool)$array['isPublicallyListed'] : false;
        $this->checkoutPersonListKind = isset($array['checkoutPersonListKind'])
            ? $array['checkoutPersonListKind'] : self::DEFAULT_CHECKOUT_PERSON_LIST_KIND;
        $this->isActive = isset($array['isActive']) ? $array['isActive'] : true;
        $this->nextWithinLibraryId = isset($array['nextWithinLibraryId']) ? $array['nextWithinLibraryId'] : null;
    }

    /**
     * Return an array representation of the object
     *
     * @return array
     */
    public function getArrayCopy()
    {
        //@todo getArrayCopy()
    }

    /**
     * Return a list of problem objects with the current library
     */
    public function getProblems()
    {
        //@todo getProblems()
    }
}

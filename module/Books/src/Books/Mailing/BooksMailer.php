<?php
namespace Books\Mailing;

use SionModel\Filter\ToAscii;
use SionModel\Mailing\Mailer;
use Books\Model\LibraryTable;
use Schoenstatt\Model\SchoenstattTable;

class BooksMailer extends Mailer
{
    const STATUS_NOT_SENT = 'not-sent';
    const STATUS_SUCCESSFULLY_SENT = 'success';
    const STATUS_ERROR = 'error';

    /**
     * @var LibraryTable $libraryTable
     */
    protected $libraryTable;

    /**
    * @var SchoenstattTable $schoenstattTable
    */
    protected $schoenstattTable;

    public function __construct($mailService, $translator, $config, $libraryTable, $schoenstattTable)
    {
        parent::__construct($mailService, $translator, $config, $libraryTable);
        $this->libraryTable  = $libraryTable;
        $this->schoenstattTable = $schoenstattTable;
    }

    /**
     * Send a library borrower a notice of the books checked out
     * @param int $libraryId
     * @param bool $onlyIfBorrowerHasOverdueBook
     * @param array $borrowerSubset
     * @param number $maxAttempts
     */
    public function sendBookNotices($libraryId, $onlyIfBorrowerHasOverdueBook = true, $simulate = false, array $borrowerSubset = [], $maxAttempts = 3)
    {
        $initialLocale = $this->translator->getLocale();

        $libraryTable = $this->getLibraryTable();
        $checkouts = $libraryTable->getCheckoutsForLibrary($libraryId, 'current');
        $borrowersToContact = [];
        //first loop through once to get our list of persons
        foreach ($checkouts as $checkoutId => $object) {
            if ((!$onlyIfBorrowerHasOverdueBook || LibraryTable::CHECKOUT_STATUS_OVERDUE === $object['status']) &&
                !in_array($object['personId'], $borrowersToContact)
            ) {
                $borrowersToContact[] = $object['personId'];
            }
        }
        $schTable = $this->getSchoenstattTable();
        $borrowers = $schTable->getPersons($borrowersToContact); //@todo only get selected persons
        //now loop through checkouts again to fill up the person list
        foreach ($checkouts as $checkoutId => $object) {
            if (isset($borrowers[$object['personId']])) {
                if (!isset($borrowers[$object['personId']]['checkouts'])) {
                    $borrowers[$object['personId']]['checkouts'] = [];
                    $borrowers[$object['personId']]['mailingStatus'] = self::STATUS_NOT_SENT;
                    $borrowers[$object['personId']]['hasOverdueBook'] = false;
                }
                $borrowers[$object['personId']]['checkouts'][$object['checkoutId']] = $object;
                if (LibraryTable::CHECKOUT_STATUS_OVERDUE === $object['status']) {
                    $borrowers[$object['personId']]['hasOverdueBook'] = true;
                }
            }
        }
        //@todo delete the following when getPersons is finished
        foreach ($borrowers as $personId => $object) {
            if (!isset($object['mailingStatus'])) {
                unset($borrowers[$personId]);
            } elseif ($onlyIfBorrowerHasOverdueBook && !$object['hasOverdueBook']) {
                unset($borrowers[$personId]);
            }
        }

        if ($simulate) {
            return $borrowers;
        }

        $library = $libraryTable->getSimpleLibrary($libraryId);
        if (isset($library['contactEmail'])) {
            $replyEmail = $library['contactEmail'];
        } elseif (isset($library['contactPersonId'])) {
            $person = $schTable->getSimplePerson($library['contactPersonId']);
            if (isset($person['email'])) {
                $replyEmail = $person['email'];
            }
        }
        $localizedLibraryName = [];
        $subjectBase = '%s - Overdue notice';
        $localizedSubject = [];
        $template = 'sion-model/mailing/action-email';
        $tags = 'book-checkouts|library'.$library['libraryId']; //pipe-separated
        $textDomain = 'Books';
        $paragraphPrototype = [
            'salutation' => [ //salutation
                'type' => 'content',
                'content' => 'Dear %s,',
                'isContentParameterized' => true,
                'contentParams' => [], //fill in with borrower name
            ],
            'message' => [ //message paragraph
                'type' => 'content',
                'content' => 'The following is the list of books checked out under your name for the <strong>%s</strong> library. Please take the time to renew overdue books online, or inform the librarian of any lost books.',
                'isContentParameterized' => true,
                'shouldEscape' => false,
                'contentParams' => [], //fill in with library name
            ],
            'blank' => [],//blank paragraph
            'list' => [ // checkout list
                'type' => 'partial',
                'partial' => 'books/libraries/email-book-list',
                'checkouts' => null,
            ],
            'button' => [ //button
                'type' => 'button',
                'content' => 'View on website',
                'urlArgs'   => [
                    'borrowers/borrower',
                    //fill in person_id param
                ],
            ],
            'signature' => [
                'type' => 'content',
                'content' => '—The Schoenstatt Link Team',
            ],
        ];
        $footerParagraph = [ //footer paragraph
            'type' => 'content',
            'content' => 'Follow %s on Twitter',
            'isContentParameterized' => true,
            'shouldEscape' => false,
            'contentParams' => ['<a href="https://twitter.com/SchoenstattData">@SchoenstattData</a>'],
        ];

        $mailService = $this->getMailService();
        $timeZone = new \DateTimeZone('UTC');
        $asciiFilter = new ToAscii();

//         $debugCount = 0;
        foreach ($borrowers as $personId => $object) {
//             if ($debugCount > 0) {
//                 break;
//             }
//             $debugCount++;
            $locale = isset($object['primaryLocale']) ? $object['primaryLocale'] : Locale::getDefault();
            $salutation = (isset($object['title']) ?
                $this->translator->translate($object['title'], 'Schoenstatt', $locale).' ' :
                '').$object['firstName'];
            if (!isset($localizedSubject[$locale])) {
                $localizedSubject[$locale] = sprintf($this->translator->translate($subjectBase, $textDomain, $locale),
                    $this->translator->translate($library['name'], $textDomain, $locale));
            }
            if (!isset($localizedLibraryName[$locale])) {
                $localizedLibraryName[$locale] = $this->translator->translate($library['name'], $textDomain, $locale);
            }
            $trackingToken = self::getNewTrackingToken();
            $paragraphs = $paragraphPrototype;
            $paragraphs['salutation']['contentParams'] = [$salutation];
            $paragraphs['message']['contentParams'] = [$localizedLibraryName[$locale]];
            $paragraphs['list']['checkouts'] = $object['checkouts'];
            $paragraphs['button']['urlArgs'][] = ['person_id' => $object['personId']]; //url person_id param
            $paragraphs['button']['urlArgs'][] = ['query' => ['token' => $trackingToken]];
            $mailService->setTemplate($template, [
                'locale'        => $locale,
                'paragraphs'    => $paragraphs,
                'title'         => $localizedSubject[$locale],
                'shouldTranslateTitle' => false,
                'textDomain'    => $textDomain,
                'footer'        => $footerParagraph,
            ]);

            $message = $mailService->getMessage();
            $message->setSubject($localizedSubject[$locale]);
//             $message->setTo('webmaster@schoenstatt.link', isset($object['fullFriendlyName']) ?
//                 $asciiFilter->filter($object['fullFriendlyName']) : null);
            $message->setTo($borrower['email'], isset($object['fullFriendlyName']) ?
                $asciiFilter->filter($object['fullFriendlyName']) : null);
            if (isset($replyEmail)) {
                $message->addReplyTo($replyEmail);
            }
            $body = $this::inlineEmailStyles($message->getBodyText());
            $message->setBody($body);
            $result = $mailService->send();
            $exception = null;
            if (!$result->isValid()) {
                if ($result->hasException()) {
                    $exception = $result->getException();
                } else {
                    $exception = new \Exception($result->getMessage());
                }
                $borrowers[$personId]['mailingStatus'] = self::STATUS_ERROR;
            } else {
                $borrowers[$personId]['mailingStatus'] = self::STATUS_SUCCESSFULLY_SENT;
            }
//             if (is_object($exception)) {
//                 var_dump($exception->getMessage());
//                 var_dump($exception->getTraceAsString());
//             }
            //report email
            $this->reportMailing($message, 1, 3, $exception, $locale, $template, $trackingToken, $tags);
        }

        return $borrowers;
    }

    public function getLibraryTable()
    {
        return $this->libraryTable;
    }

    /**
     *
     * @param LibraryTable $libraryTable
     * @return $this
     */
    public function setLibraryTable(LibraryTable $libraryTable)
    {
        $this->libraryTable = $libraryTable;
        return $this;
    }

    /**
     * Get the schoenstattTable value
     * @return SchoenstattTable
     */
    public function getSchoenstattTable()
    {
        return $this->schoenstattTable;
    }

    /**
     *
     * @param SchoenstattTable $schoenstattTable
     * @return self
     */
    public function setSchoenstattTable($schoenstattTable)
    {
        $this->schoenstattTable = $schoenstattTable;
        return $this;
    }
}

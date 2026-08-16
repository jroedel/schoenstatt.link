<?php
namespace Books\Mailing;

use SionModel\Filter\ToAscii;
use SionModel\Mailing\Mailer;
use Books\Model\LibraryTable;
use Schoenstatt\Model\SchoenstattTable;
use Symfony\Component\Mime\Address;
use voku\Html2Text\Html2Text;

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

    public function __construct($transport, $renderer, $translator, $config, $libraryTable, $schoenstattTable)
    {
        parent::__construct($transport, $renderer, $translator, $config, $libraryTable);
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
        $libraryTable = $this->getLibraryTable();
        $checkouts = $libraryTable->getCheckoutsForLibrary($libraryId, 'current');
        $borrowersToContact = [];
        //first loop through once to get our list of persons
        foreach ($checkouts as $checkoutId => $object) {
            if ((! $onlyIfBorrowerHasOverdueBook || LibraryTable::CHECKOUT_STATUS_OVERDUE === $object['status']) &&
                ! in_array($object['personId'], $borrowersToContact) &&
                (empty($borrowerSubset) || in_array($object['personId'], $borrowerSubset)) //make sure the person is in our subset
            ) {
                $borrowersToContact[] = $object['personId'];
            }
        }
        $schTable = $this->getSchoenstattTable();
        $borrowers = $schTable->getPersons($borrowersToContact); //@todo only get selected persons
        //now loop through checkouts again to fill up the person list
        foreach ($checkouts as $checkoutId => $object) {
            if (isset($borrowers[$object['personId']])) {
                if (! isset($borrowers[$object['personId']]['checkouts'])) {
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
            if (! isset($object['mailingStatus']) || ! in_array($personId, $borrowersToContact)) {
                unset($borrowers[$personId]);
            } elseif ($onlyIfBorrowerHasOverdueBook && ! $object['hasOverdueBook']) {
                unset($borrowers[$personId]);
            }
        }

        if ($simulate) {
            return $borrowers;
        }

        $library = $libraryTable->getObject('library', $libraryId);
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
        $tags = 'book-checkouts|library' . $library['libraryId']; //pipe-separated
        $textDomain = 'Books';
        $paragraphPrototype = [
            'salutation' => [ //salutation
                'type' => 'content',
                'content' => 'Dear %s,',
                'isContentParameterized' => true,
                'contentParams' => [], //fill in with borrower name
            ],
            'message' => [ //message paragraph
                //Says only what the reader can actually do. The previous wording
                //asked them to "renew overdue books online", and there is no online
                //renewal: LibraryTable::renewBook() exists but has no route, no
                //action, no UI and no callers, and LibraryOptions::$maximumBookRenewals
                //is unused. Replying reaches the library's contact address, which the
                //message sets as Reply-To below, so that is the one channel that works.
                'type' => 'content',
                'content' => 'The following is the list of books checked out under your name for the <strong>%s</strong> library. Please return anything you have finished with, and simply reply to this message to arrange a renewal or to report a book as lost.',
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
            //No "View on website" button. It pointed at borrowers/borrower, which is
            //guarded by lib_user — and lib_user is is_default=1, so that means "any
            //signed-in account", not "this borrower". A borrower without an account
            //(the common case) met a sign-in page, and one with an account reached a
            //librarian screen whose only control re-sends these notices. The book list
            //above is the content that was worth linking to, and it is already here.
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

        $asciiFilter = new ToAscii();

        foreach ($borrowers as $personId => $object) {
            $locale = isset($object['primaryLocale']) ? $object['primaryLocale'] : \Locale::getDefault();
            $salutation = (isset($object['title']) ?
                $this->translator->translate($object['title'], 'Schoenstatt', $locale) . ' ' :
                '') . $object['firstName'];
            if (! isset($localizedSubject[$locale])) {
                $localizedSubject[$locale] = sprintf(
                    $this->translator->translate($subjectBase, $textDomain, $locale),
                    $this->translator->translate($library['name'], $textDomain, $locale)
                );
            }
            if (! isset($localizedLibraryName[$locale])) {
                $localizedLibraryName[$locale] = $this->translator->translate($library['name'], $textDomain, $locale);
            }
            $trackingToken = self::getNewTrackingToken();
            $paragraphs = $paragraphPrototype;
            $paragraphs['salutation']['contentParams'] = [$salutation];
            $paragraphs['message']['contentParams'] = [$localizedLibraryName[$locale]];
            $paragraphs['list']['checkouts'] = $object['checkouts'];
            //$trackingToken is still generated and still recorded against the mailing
            //report below. What it no longer has is a click to observe: it used to ride
            //in the removed button's query string. Open/click analytics for these
            //notices are therefore gone — deliberately, and worth knowing before anyone
            //reads a run of zeroes as "nobody opened it".
            $html = self::inlineEmailStyles($this->renderTemplate($template, [
                'locale'        => $locale,
                'paragraphs'    => $paragraphs,
                'title'         => $localizedSubject[$locale],
                'shouldTranslateTitle' => false,
                'textDomain'    => $textDomain,
                'footer'        => $footerParagraph,
            ]));

            $message = $this->createEmail()
                ->subject($localizedSubject[$locale])
                ->html($html)
                ->text((new Html2Text($html))->getText());
            if (isset($replyEmail)) {
                $message->addReplyTo($replyEmail);
            }
            $exception = null;
            try {
                $message->to(new Address($object['email'], isset($object['fullFriendlyName']) ?
                    $asciiFilter->filter($object['fullFriendlyName']) : ''));
                $this->getTransport()->send($message);
                $borrowers[$personId]['mailingStatus'] = self::STATUS_SUCCESSFULLY_SENT;
            } catch (\Exception $exception) {
                //a bad address or a refused delivery is reported and must not
                //abort the notices still to be sent
                $borrowers[$personId]['mailingStatus'] = self::STATUS_ERROR;
            }
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

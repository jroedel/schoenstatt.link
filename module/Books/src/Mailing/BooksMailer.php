<?php
namespace Books\Mailing;

use SionModel\Filter\ToAscii;
use SionModel\Mailing\Mailer;
use Books\Model\BorrowerTokenTable;
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

    /** @var BorrowerTokenTable|null mints the scoped link each notice carries */
    protected $borrowerTokenTable;

    public function __construct(
        $transport,
        $renderer,
        $translator,
        $config,
        $libraryTable,
        $schoenstattTable,
        ?BorrowerTokenTable $borrowerTokenTable = null
    )
    {
        parent::__construct($transport, $renderer, $translator, $config, $libraryTable);
        $this->libraryTable  = $libraryTable;
        $this->schoenstattTable = $schoenstattTable;
        $this->borrowerTokenTable = $borrowerTokenTable;
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
            //A link the reader can actually act on, without an account.
            //
            //The button this replaces pointed at borrowers/borrower, guarded by
            //lib_user — and lib_user is is_default=1, so that meant "any signed-in
            //account" rather than "this borrower". A borrower without an account, the
            //common case, met a sign-in page; one with an account reached a librarian
            //screen whose only control re-sent these notices.
            //
            //`target` rather than `urlArgs`: the destination is Symfony-served, so there
            //is no laminas route to build from, and the URL must be absolute because an
            //email has no request to take a host from — least of all on CLI.
            'button' => [
                'type' => 'button',
                'content' => 'See and renew your books',
                'target' => null, //per-borrower, filled in below
            ],
            'signature' => [
                'type' => 'content',
                'content' => '—The Schoenstatt Link Team',
            ],
        ];
        //No social footer. It read "Follow @SchoenstattData on Twitter" — a network
        //renamed in 2023, advertised in a library overdue notice, where it was never
        //relevant. An overdue notice asks a small favour of someone; it should ask for
        //the one thing and stop.
        $footerParagraph = null;

        //Absolute, and from configuration rather than the request: these notices go out
        //from a console command as readily as from a web request, and a CLI process has
        //no host to infer one from.
        $baseUrl = rtrim(
            isset($this->config['sion_model']['canonical_base_url'])
                ? (string) $this->config['sion_model']['canonical_base_url']
                : '',
            '/'
        );

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
            //One token per borrower per notice. It authorises this person's checkouts at
            //this library and nothing else, so two recipients of the same run cannot see
            //each other's books, and a forwarded mail hands on no more than the sender's
            //own list.
            if (isset($this->borrowerTokenTable)) {
                $borrowerToken = $this->borrowerTokenTable->issue(
                    (int) $object['personId'],
                    (int) $library['libraryId']
                );
                $paragraphs['button']['target'] = $baseUrl . '/library/my-books?t=' . $borrowerToken;
            } else {
                //No token service (an older wiring, or a test double): send the notice
                //without the link rather than not at all. The book list is the substance.
                unset($paragraphs['button']);
            }
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

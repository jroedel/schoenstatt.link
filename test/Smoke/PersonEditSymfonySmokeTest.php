<?php

namespace SchoenstattTest\Smoke;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PDO;

/**
 * The person edit form — the tenth and last of the entity edit surface.
 *
 * ## What is specific to this page
 *
 * Four things no earlier form in the batch had: `tel` inputs (the custom
 * `SionModel\Form\Element\Phone`, four of them), a collapse block driven by a checkbox's
 * value, a `<title>` built from the record rather than fixed, and a delete-confirmation
 * modal (the second, after `assignment-edit`).
 *
 * ## And one thing that is not on the page at all
 *
 * `nameDay` is **deliberately not rendered** — the field is on its way out with its
 * database column — but it is still posted, through three hidden inputs. That is not
 * tidiness. The element is on the form's input filter, so an absent field does not mean
 * "leave it alone": `getData()` answers `nameDay => null` and `updateEntity()` writes that
 * null over the stored date. Measured 2026-08-14: 130 of the capsule's 325 persons have a
 * name day, and every one of them would have been erased the first time a moderator saved
 * their record.
 *
 * {@see testASaveDoesNotEraseTheNameDayItNeverRendered} is the assertion that pins it, and
 * it is the most important test in this file. When the column is dropped, that test and the
 * hidden inputs go together.
 *
 * ## Restoring what it touches
 *
 * `AdminNotes` is free text with nothing derived from it, snapshotted before the write and
 * put back in `tearDown`. The `sch_changes` rows a save files are left behind deliberately,
 * for the reason `Batch7EditWriteSmokeTest` records: the change log is an audit trail. Same
 * caveat therefore applies — **do not run the suite between two baseline captures.**
 */
class PersonEditSymfonySmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** A real person who has both a full name and a stored name day (1900-03-19). */
    private const ID   = 31;
    private const PATH = '/en/persons/31/edit';

    /** @var array<string, string|null>|null */
    private ?array $original = null;

    protected function emailPrefix(): string
    {
        return 'person-edit-';
    }

    protected function tearDown(): void
    {
        $this->restore();
        $this->purgeAccounts();
        $this->purgeMail();
        parent::tearDown();
    }

    public function testItIsRefusedAnonymously(): void
    {
        $response = $this->get(self::PATH);

        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString('/en/user/login?redirect=' . self::PATH, $response['redirect']);
    }

    /**
     * The three sections render, and the document completes.
     *
     * The section headings are the cheapest proof the whole partial ran: `Private Info` is
     * the last of the three, so its presence rules out a mid-render failure that a status
     * assertion would not see.
     */
    public function testAModeratorGetsTheWholeForm(): void
    {
        $body = $this->signedInBody();

        $this->assertStringContainsString('<h1>Edit Person Info</h1>', $body);
        foreach (['Contact Info', 'Personal Info', 'Private Info'] as $heading) {
            $this->assertStringContainsString('<h2>' . $heading . '</h2>', $body, "the '$heading' section is missing");
        }
        foreach (['firstName', 'lastName', 'email', 'postZip', 'adminNotes', 'security'] as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $body, "the '$field' field is missing");
        }
        $this->assertStringContainsString('</html>', $body, 'the response was truncated mid-render');
    }

    /**
     * The title carries the person's name, and specifically not their honorific.
     *
     * `recordName()` used to walk a list of candidate keys rather than asking the entity
     * spec, and for a person that list reached `title` — the honorific — before `fullName`.
     * So `/persons/494/edit` was headed "Sr.". Asserting the surname is what makes this a
     * test of the fix rather than of "some name appeared".
     */
    public function testTheTitleCarriesTheRecordsNameAndNotItsHonorific(): void
    {
        $body = $this->signedInBody();
        $name = (string) $this->column('LastName');

        $this->assertSame(
            1,
            preg_match('#<title>Edit ([^<]*) - #', $body, $match),
            'the title is not of the form "Edit <name> - <site>"'
        );
        $this->assertStringContainsString(
            $name,
            $match[1],
            'the title does not contain the person\'s surname; it may be showing the honorific'
        );
        $this->assertStringContainsString('<p class="lead">', $body);
    }

    /**
     * The four `Phone` elements render as `type="tel"`.
     *
     * `tel` was absent from the renderer's per-input-type attribute table, which meant a
     * Phone element's attributes passed through unfiltered instead of being narrowed to
     * what `Laminas\Form\View\Helper\FormTel` allows.
     */
    public function testThePhoneFieldsRenderAsTelInputs(): void
    {
        $body = $this->signedInBody();

        foreach (['cellPhone', 'phone1', 'phone2', 'phone3'] as $field) {
            $input = $this->inputNamed($body, $field);
            $this->assertSame('tel', $input->getAttribute('type'), "'$field' is not a tel input");
        }

        //cellPhone shares a hand-built form-group with its WhatsApp checkbox rather than
        //having a row of its own — the one field in the batch arranged that way.
        $this->assertStringContainsString('name="cellPhoneHasWhatsApp"', $body);
    }

    /**
     * The collapse block, and that its state follows the checkbox rather than being fixed.
     *
     * `automaticTitle` unchecked means the manual-title group is expanded; checked means it
     * is not. Both the wrapper's `in` class and the checkbox's own `aria-expanded` carry it.
     */
    public function testTheManualTitleGroupCollapsesWithTheCheckbox(): void
    {
        $body = $this->signedInBody();

        $this->assertSame(
            1,
            //`class="collapse "` with a trailing space when the group is closed — the
            //partial emits the space unconditionally and so does this, byte for byte.
            preg_match('#<div class="collapse ?(in)?" id="titleGroup" aria-expanded="(true|false)">#', $body, $match),
            'the titleGroup collapse wrapper is missing or malformed'
        );
        //the `in` class and aria-expanded must agree, or the group renders open while
        //announcing itself closed
        $this->assertSame(
            '' !== trim($match[1] ?? ''),
            'true' === $match[2],
            'the collapse class and aria-expanded disagree'
        );
        $this->assertStringContainsString('name="manualTitle"', $body);
    }

    /** The delete modal and its trigger, both inside the delete permission's gate. */
    public function testTheDeleteModalIsOfferedToSomeoneWhoMayDelete(): void
    {
        $body = $this->signedInBody();

        $this->assertStringContainsString('bs-example-modal-sm', $body, 'the delete modal is missing');
        $this->assertStringContainsString('name="delete"', $body, 'the modal trigger button is missing');
        //Read through the DOM rather than as a substring: laminas escapes `/` in an
        //attribute as `&#x2F;` and the reproduction matches it, so the raw bytes do not
        //contain the readable path.
        $document = new DOMDocument();
        @$document->loadHTML($body);
        $modalForm = (new DOMXPath($document))->query('//form[@id="entity_delete"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $modalForm, 'the delete form is missing from the modal');
        $this->assertSame(
            '/en/persons/31/delete',
            $modalForm->getAttribute('action'),
            'the modal must post to the laminas delete route'
        );
    }

    /**
     * `nameDay` is not rendered as a field, but is posted back unchanged.
     *
     * The hidden inputs must carry the *stored* month and day. Empty values here would mean
     * every save silently clears the date — which is precisely what omitting the field
     * altogether would have done.
     */
    public function testTheUnrenderedNameDayIsStillPostedBack(): void
    {
        $stored = (string) $this->column('NameDay');
        $this->assertNotSame('', $stored, 'precondition: person 31 has a stored name day');

        $body = $this->signedInBody();

        $this->assertStringNotContainsString(
            'name="nameDay[month]" class=',
            $body,
            'nameDay is rendering as a select again; this port omits it by decision'
        );
        $this->assertSame(
            substr($stored, 5, 2),
            $this->inputNamed($body, 'nameDay[month]')->getAttribute('value'),
            'the hidden nameDay month does not match the stored date'
        );
        $this->assertSame(
            substr($stored, 8, 2),
            $this->inputNamed($body, 'nameDay[day]')->getAttribute('value'),
            'the hidden nameDay day does not match the stored date'
        );
        $this->assertSame('1900', $this->inputNamed($body, 'nameDay[year]')->getAttribute('value'));
    }

    /**
     * The four patres date fields are round-tripped, which is what lets the form save.
     *
     * They are on `PersonForm` and in the person spec's `update_columns`, but no partial on
     * this site renders them. Without these hidden inputs `getData()` answers null for all
     * four, and `PriestDatePrecision`/`BishopDatePrecision` are `NOT NULL` — so the write
     * fails. Verified against both front controllers on 2026-08-14: laminas answers 500 and
     * Symfony wedges as a fatal-200, i.e. **saving a person was broken everywhere.**
     *
     * Asserting the precision values specifically, because the tempting wrong fix is to
     * default them to `'day'` — which would let the save through while the same POST's
     * `PriestDate => null` quietly erased ten persons' ordination dates.
     */
    public function testThePatresDateFieldsAreRoundTripped(): void
    {
        $body = $this->signedInBody();

        foreach (['priestDatePrecision', 'bishopDatePrecision'] as $field) {
            $this->assertSame(
                (string) $this->column(ucfirst($field)),
                $this->inputNamed($body, $field)->getAttribute('value'),
                "the hidden '$field' does not carry the stored value; a save would write null to a "
                . 'NOT NULL column'
            );
        }
        foreach (['priestDate', 'bishopDate'] as $field) {
            $this->assertSame(
                (string) $this->column(ucfirst($field)),
                $this->inputNamed($body, $field)->getAttribute('value'),
                "the hidden '$field' does not carry the stored value; a save would erase it"
            );
        }
    }

    /**
     * The assertion this file exists for: a save leaves the name day alone.
     *
     * Change one unrelated field, submit the whole form as a browser would, and read the
     * date back. If the hidden inputs were dropped, `getData()` would contribute
     * `nameDay => null` and this would come back empty — for 130 of 325 persons.
     */
    public function testASaveDoesNotEraseTheNameDayItNeverRendered(): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $this->remember(['AdminNotes', 'NameDay', 'UpdatedOn', 'UpdatedBy']);
        $before = $this->column('NameDay');
        $this->assertNotNull($before, 'precondition: person 31 has a stored name day');

        $marker = 'Smoke test ' . time();
        $form   = $this->get(self::PATH, false, $jar);
        $this->assertSame(200, $form['status']);

        $post = $this->request(
            'POST',
            self::PATH,
            [],
            false,
            $jar,
            ['adminNotes' => $marker] + $this->fieldsFromForm($form['body'])
        );

        $this->assertSame(
            302,
            $post['status'],
            'a valid submission must redirect; a 200 means the form refused it and re-rendered'
        );
        $this->assertSame($marker, $this->column('AdminNotes'), 'the change did not reach the database');
        $this->assertSame(
            $before,
            $this->column('NameDay'),
            'the save erased the name day — the hidden nameDay inputs are missing or empty'
        );
    }

    /**
     * The same failure as the name day, on two fields that had no hidden input either —
     * and no visible one, which is why nothing round-tripped them.
     *
     * `skypeUser` and `slackUser` were named in `PersonForm`'s input filter specification
     * and were elements on no form. laminas builds an input for a specification key
     * regardless, `getValues()` returns one value per input, and an unposted field's is
     * `null` — so `updateHelper()` compared `'somehandle' == null`, found them different,
     * and wrote NULL. **Every save of any person erased both columns**, which is 63 Skype
     * handles and 8 Slack ones that the person page displays and no screen could set.
     * They are elements now (2026-09-10).
     *
     * The value is written through the form rather than by SQL on purpose: `SionTable`
     * caches persons in APCu, so a row edited behind its back is not the row the second
     * render would show, and the test would pass for the wrong reason.
     */
    public function testASaveDoesNotEraseTheSocialHandlesItNowRenders(): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $this->remember(['AdminNotes', 'SkypeUser', 'SlackUser', 'UpdatedOn', 'UpdatedBy']);

        $form = $this->get(self::PATH, false, $jar);
        $this->assertSame(200, $form['status']);

        $written = $this->request('POST', self::PATH, [], false, $jar, [
            'skypeUser' => 'smoketest.skype',
            'slackUser' => 'smoketest.slack',
        ] + $this->fieldsFromForm($form['body']));
        $this->assertSame(302, $written['status'], 'the form refused a valid Skype and Slack name');
        $this->assertSame('smoketest.skype', $this->column('SkypeUser'), 'the field is not writable');
        $this->assertSame('smoketest.slack', $this->column('SlackUser'), 'the field is not writable');

        //a second, unrelated edit — the save that used to wipe them
        $again = $this->get(self::PATH, false, $jar);
        $this->assertSame(200, $again['status']);

        $marker = 'Smoke test ' . time();
        $post   = $this->request('POST', self::PATH, [], false, $jar, [
            'adminNotes' => $marker,
        ] + $this->fieldsFromForm($again['body']));

        $this->assertSame(302, $post['status']);
        $this->assertSame($marker, $this->column('AdminNotes'), 'the change did not reach the database');
        $this->assertSame(
            'smoketest.skype',
            $this->column('SkypeUser'),
            'an unrelated save erased the Skype handle — the field is not being rendered and round-tripped'
        );
        $this->assertSame('smoketest.slack', $this->column('SlackUser'), 'an unrelated save erased the Slack handle');
    }

    // -- helpers -------------------------------------------------------------

    private function signedInBody(): string
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $response = $this->get(self::PATH, false, $jar);
        $this->assertSame(200, $response['status'], 'the edit form must render for a moderator');

        return $response['body'];
    }

    private function inputNamed(string $body, string $name): DOMElement
    {
        $document = new DOMDocument();
        @$document->loadHTML($body);
        $xpath = new DOMXPath($document);

        $node = $xpath->query(sprintf('//input[@name="%s"]', $name))->item(0);
        $this->assertInstanceOf(DOMElement::class, $node, "no input named '$name' was rendered");

        return $node;
    }

    /**
     * Every field the form rendered, with its current value — a crude browser. Submitting
     * the whole form is the point: a partial POST drops every field it omits, which is the
     * very thing this file is checking does not happen to `nameDay`.
     *
     * @return array<string, string>
     */
    private function fieldsFromForm(string $body): array
    {
        $document = new DOMDocument();
        @$document->loadHTML($body);
        $xpath = new DOMXPath($document);

        $form = $xpath->query('//form[@id="edit_person"]')->item(0);
        $this->assertNotNull($form, 'the person form was not rendered');

        $fields = [];
        foreach ($xpath->query('.//input|.//textarea|.//select', $form) as $node) {
            /** @var DOMElement $node */
            $name = $node->getAttribute('name');
            if ('' === $name) {
                continue;
            }
            if ('input' === $node->nodeName) {
                if ('checkbox' === $node->getAttribute('type') && ! $node->hasAttribute('checked')) {
                    //the hidden twin already supplied the unchecked value
                    continue;
                }
                $fields[$name] = $node->getAttribute('value');
                continue;
            }
            if ('textarea' === $node->nodeName) {
                $fields[$name] = $node->textContent;
                continue;
            }
            $selected      = $xpath->query('.//option[@selected]', $node)->item(0);
            $fields[$name] = $selected instanceof DOMElement ? $selected->getAttribute('value') : '';
        }

        return $fields;
    }

    /** @param list<string> $columns */
    private function remember(array $columns): void
    {
        $statement = $this->pdo()->prepare(
            sprintf('SELECT `%s` FROM sch_persons WHERE PersonId = ?', implode('`, `', $columns))
        );
        $statement->execute([self::ID]);
        /** @var array<string, string|null>|false $row */
        $row            = $statement->fetch(PDO::FETCH_ASSOC);
        $this->original = false === $row ? null : $row;
    }

    private function restore(): void
    {
        if (null === $this->original) {
            return;
        }

        $assignments = [];
        foreach (array_keys($this->original) as $column) {
            $assignments[] = sprintf('`%s` = ?', $column);
        }
        $this->pdo()
            ->prepare(sprintf('UPDATE sch_persons SET %s WHERE PersonId = ?', implode(', ', $assignments)))
            ->execute([...array_values($this->original), self::ID]);

        $this->original = null;
    }

    private function column(string $column): ?string
    {
        $statement = $this->pdo()->prepare(
            sprintf('SELECT `%s` FROM sch_persons WHERE PersonId = ?', $column)
        );
        $statement->execute([self::ID]);
        $value = $statement->fetchColumn();

        return false === $value || null === $value ? null : (string) $value;
    }
}

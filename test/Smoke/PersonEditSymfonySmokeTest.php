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
    use FormRoundTrip;

    /** A real person with a full name and a death date. */
    private const ID   = 31;
    private const PATH = '/en/persons/31/edit';

    protected function emailPrefix(): string
    {
        return 'person-edit-';
    }

    /**
     * A save preserves every column it does not set out to change.
     *
     * This is the baseline for removing a field, and it is deliberately written so that
     * removing one does not require editing it. The payload is scraped from the rendered
     * form, so a field that stops existing simply stops being posted; the columns asserted
     * on are read from the row, so one that stops existing is dropped from the list by the
     * same edit that drops it from the schema — and until then, every one of them is
     * pinned.
     *
     * What it is really guarding is the `NOT NULL` precision columns. `DeathDatePrecision`
     * was added `NOT NULL` by db6.5, so a POST that omits it does not save a null — it
     * throws, and with display_errors off that is an empty 200 with nothing in the log.
     * The template must render or round-trip every such column; this is what says so.
     */
    public function testASavePreservesEveryDateColumnItDoesNotChange(): void
    {
        $columns = [
            'DeathDate',
            'DeathDatePrecision',
            'PersonTags',
        ];
        $present = $this->existingColumns($columns);
        self::assertNotEmpty($present, 'none of the date columns exist: is this the right table?');

        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $this->remember([...$present, 'AdminNotes', 'UpdatedOn', 'UpdatedBy']);
        $before = [];
        foreach ($present as $column) {
            $before[$column] = $this->column($column);
        }

        $response = $this->submitForm(self::PATH, $jar, ['adminNotes' => 'Smoke test ' . time()]);
        self::assertSame(302, $response['status'], 'the save did not redirect; it did not succeed');

        foreach ($present as $column) {
            self::assertSame(
                $before[$column],
                $this->column($column),
                sprintf('%s changed across a save that did not touch it', $column)
            );
        }
    }

    /**
     * Which of `$columns` the table actually has.
     *
     * Asking the schema rather than assuming it is what lets one list serve both sides of
     * a column drop: before the migration all ten are checked, after it the seven that
     * went are simply not there, and the remaining three are still pinned. A hard-coded
     * list would turn the migration into a test edit, which is how a baseline quietly
     * stops covering the thing it was written for.
     *
     * @param list<string> $columns
     * @return list<string>
     */
    private function existingColumns(array $columns): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $statement->execute([$this->table()]);
        $actual = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_intersect($columns, $actual));
    }

    protected function table(): string
    {
        return 'sch_persons';
    }

    /** @return array{0: string, 1: int} */
    protected function row(): array
    {
        return ['PersonId', self::ID];
    }

    protected function formXPath(): string
    {
        return '//form[@id="edit_person"]';
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
     * A field named in the input filter but rendered by no form is written as NULL on
     * every save. This is that failure, on two fields that had no visible element.
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
        ] + $this->fields($form['body']));
        $this->assertSame(302, $written['status'], 'the form refused a valid Skype and Slack name');
        $this->assertSame('smoketest.skype', $this->column('SkypeUser'), 'the field is not writable');
        $this->assertSame('smoketest.slack', $this->column('SlackUser'), 'the field is not writable');

        //a second, unrelated edit — the save that used to wipe them
        $again = $this->get(self::PATH, false, $jar);
        $this->assertSame(200, $again['status']);

        $marker = 'Smoke test ' . time();
        $post   = $this->request('POST', self::PATH, [], false, $jar, [
            'adminNotes' => $marker,
        ] + $this->fields($again['body']));

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

}

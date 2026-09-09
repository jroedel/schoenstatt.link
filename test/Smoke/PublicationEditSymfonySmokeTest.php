<?php

namespace SchoenstattTest\Smoke;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PDO;

/**
 * The publication edit form, the ninth and last of the edit surface.
 *
 * Batch 7 ported eight of the ten `SionController::editAction()` routes and left this one
 * out because its view was the only one that did not render rows: `fields-partial.phtml`
 * built five `form-group`s by hand around `formSelectWithoutOptions`, a helper no other
 * form used. Both are gone — the helper with laminas-i18n in 2026-09 — and
 * `BootstrapFormRenderer::selectWithoutOptions()` is what renders those five pickers now.
 *
 * ## What is specific to this page, and therefore what this file is for
 *
 * The five pickers — `authorsAll`, `editorsAll`, `translatorsAll`, `mainPublicationId`,
 * `translatedFromPublicationId` — render **only the options already chosen**, because
 * their full lists are the person and publication tables. Everything else comes from JSON
 * handed to selectize. That arrangement has a specific failure mode and it is the one
 * batch 7 met twice: a picker that renders its values wrongly does not break the page, it
 * breaks the **save**, because the browser posts what the markup contained. So the
 * assertions here are about what the selects contain, not about the page answering.
 *
 * `test/Integration/FormSelectWithoutOptionsContractTest` pins that renderer's exact bytes
 * against what the deleted helper produced, and is the finer-grained check; this is the
 * same claim over HTTP, with the real form, the real data and a real save.
 *
 * ## The write test restores what it touches
 *
 * `AdminNotes` is free text with nothing derived from it, snapshotted before the write and
 * put back in `tearDown`, the arrangement `Batch7EditWriteSmokeTest` established. The
 * `sch_changes` rows a save files are left behind deliberately, for the reason recorded
 * there: the change log is an audit trail. So the same caveat applies — **do not run the
 * suite between two baseline captures.**
 */
class PublicationEditSymfonySmokeTest extends SmokeTestCase
{
    /** A real publication with authors set, so the pickers have something to lose. */
    private const PATH = '/en/SL202186L/edit';
    private const ID   = 2186;

    /** @var array<string, string|null>|null the row's columns before this test wrote to it */
    private ?array $original = null;

    protected function emailPrefix(): string
    {
        return 'publication-edit-';
    }

    protected function tearDown(): void
    {
        $this->restore();
        $this->purgeAccounts();
        $this->purgeMail();
        parent::tearDown();
    }

    use MagicLinkSignIn;

    public function testItIsRefusedAnonymously(): void
    {
        $response = $this->get(self::PATH);

        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString('/en/user/login?redirect=' . self::PATH, $response['redirect']);
    }

    /**
     * The page renders, and renders the fields — not merely a 200.
     *
     * The distinction is the one batch 7 shipped a bug through: a form whose factory could
     * not be built answered 200 with an empty body, and a status assertion passed.
     */
    public function testAModeratorGetsTheWholeForm(): void
    {
        $body = $this->signedInBody();

        $this->assertStringContainsString('<h1>Edit publication</h1>', $body);
        foreach (['title', 'isbn', 'publisher', 'adminNotes', 'security'] as $field) {
            $this->assertStringContainsString("name=\"$field\"", $body, "the '$field' field is missing");
        }
        $this->assertStringContainsString('</html>', $body, 'the response was truncated mid-render');
    }

    /**
     * Each of the five pickers renders, carries the multiple-select `[]` where it should,
     * and — the point — ships only the options already chosen.
     *
     * The option count is the assertion that matters. `mainPublicationId`'s full list is
     * every edition in the database; if the narrowing regressed, this select alone would
     * carry thousands of options and the page would grow by megabytes. Bounded rather than
     * pinned exactly, because the row's own data may change in a future dump.
     */
    public function testThePickersShipOnlyTheChosenOptions(): void
    {
        $body = $this->signedInBody();

        //`&#x5B;&#x5D;`, not `[]`: laminas escapes attribute values with
        //Laminas\Escaper, which encodes brackets, and the reproduction matches it. The
        //raw bytes are what a browser receives, so they are what is asserted — the DOM
        //queries below decode the entities and would pass either way.
        foreach (['authorsAll', 'editorsAll', 'translatorsAll'] as $name) {
            $this->assertStringContainsString(
                'name="' . $name . '&#x5B;&#x5D;"',
                $body,
                "$name must keep its [] suffix or the browser posts only the last value"
            );
        }

        foreach (['authorsAll[]', 'editorsAll[]', 'translatorsAll[]', 'mainPublicationId',
                  'translatedFromPublicationId'] as $name) {
            $options = $this->optionsOf($body, $name);
            $this->assertLessThan(
                50,
                $options,
                "the '$name' picker rendered $options options; it is supposed to render only the "
                . 'selected ones and let selectize fetch the rest from the JSON'
            );
        }
    }

    /**
     * `authorsAll` really does carry the publication's authors, marked selected.
     *
     * The complement of the test above: "few options" would also be satisfied by *no*
     * options, which is the shape that silently blanks the field on save.
     */
    public function testTheAuthorPickerCarriesItsCurrentValue(): void
    {
        $authors = (string) $this->column('Authors');
        $this->assertNotSame('', $authors, 'precondition: publication 2186 has authors');

        $body = $this->signedInBody();

        $this->assertGreaterThan(
            0,
            substr_count($this->selectMarkup($body, 'authorsAll[]'), 'selected'),
            'the author picker rendered no selected option, so saving this form would erase the authors'
        );
    }

    /**
     * The three JSON lists selectize needs, present and parseable.
     *
     * Asserted as JSON rather than as a substring because the failure that matters is a
     * malformed blob: a `SyntaxError` in the inline script leaves every picker an ordinary
     * multi-select with no options, which looks like a styling problem and saves blanks.
     */
    public function testTheSelectizeOptionListsAreValidJson(): void
    {
        $body = $this->signedInBody();

        foreach (['publicationOptions', 'authorPersons', 'authorAssociations'] as $variable) {
            $this->assertSame(
                1,
                preg_match('/var ' . $variable . ' = (\[.*?\]);/s', $body, $match),
                "the '$variable' list is missing from the inline script"
            );
            $this->assertIsArray(
                json_decode($match[1], true),
                "the '$variable' list is not valid JSON"
            );
        }
    }

    /**
     * The delete link, which only this form of the nine offers.
     *
     * An account holding every role passes the ACL check, so the link must be there. The
     * negative direction — a moderator who may edit but not delete — is not exercised
     * here because no such role exists to sign in as; the gate itself is
     * `is_allowed('route/publication-delete')` in the template, one expression, matching
     * edit.phtml.
     */
    public function testTheDeleteLinkIsOfferedToSomeoneWhoMayDelete(): void
    {
        $this->assertStringContainsString(
            'href="/en/SL202186L/delete"',
            $this->signedInBody(),
            'the delete link is missing, or points somewhere other than publication-delete'
        );
    }

    /**
     * A save round trip: change one field, get a redirect, read it back.
     *
     * And then the part that matters more — the authors are still there. A picker that
     * rendered its values wrongly posts nothing for that field, `getData()` contributes an
     * empty array and `updateEntity()` writes it over real data. Nothing about that fails
     * loudly, which is why it is asserted here rather than assumed from the render test.
     */
    public function testASaveKeepsTheAuthorsItDidNotTouch(): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $this->remember(['AdminNotes', 'Authors', 'UpdatedOn', 'UpdatedBy']);
        $authorsBefore = $this->column('Authors');

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
            $authorsBefore,
            $this->column('Authors'),
            'saving the form erased the authors — the picker did not render its current values'
        );
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

    /** The markup of one named select, so option counting cannot stray into a neighbour. */
    private function selectMarkup(string $body, string $name): string
    {
        $document = new DOMDocument();
        @$document->loadHTML($body);
        $xpath = new DOMXPath($document);

        $select = $xpath->query(sprintf('//select[@name="%s"]', $name))->item(0);
        $this->assertNotNull($select, "no select named '$name' was rendered");

        return (string) $document->saveHTML($select);
    }

    private function optionsOf(string $body, string $name): int
    {
        return substr_count($this->selectMarkup($body, $name), '<option');
    }

    /**
     * Every input, textarea and select the form rendered, with its current value — a crude
     * browser. Submitting the whole form is the point: a partial POST drops every field it
     * omits, which no browser does and which would make "the authors survived" meaningless.
     *
     * @return array<string, string>
     */
    private function fieldsFromForm(string $body): array
    {
        $document = new DOMDocument();
        @$document->loadHTML($body);
        $xpath = new DOMXPath($document);

        $form = $xpath->query('//form[@id="publication"]')->item(0);
        $this->assertNotNull($form, 'the publication form was not rendered');

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
            sprintf('SELECT `%s` FROM sch_publications WHERE PublicationId = ?', implode('`, `', $columns))
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
            ->prepare(sprintf(
                'UPDATE sch_publications SET %s WHERE PublicationId = ?',
                implode(', ', $assignments)
            ))
            ->execute([...array_values($this->original), self::ID]);

        $this->original = null;
    }

    private function column(string $column): ?string
    {
        $statement = $this->pdo()->prepare(
            sprintf('SELECT `%s` FROM sch_publications WHERE PublicationId = ?', $column)
        );
        $statement->execute([self::ID]);
        $value = $statement->fetchColumn();

        return false === $value || null === $value ? null : (string) $value;
    }
}

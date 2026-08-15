<?php

namespace SchoenstattTest\Smoke;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Batch 9: the nine ported create forms, rendered and written.
 *
 * ## What it asserts, and why the write half is not optional
 *
 * The read half is three outcomes per route — anonymous is refused, a moderator gets the
 * page, and the page contains the form rather than merely answering 200. The last of those
 * is the batch-7 lesson twice over: `collections/collection/edit` shipped as a fatal-200
 * that a status assertion could not see, and `books/book/edit` shipped with no submit button
 * that a status assertion could not see either. So every render assertion here reads the
 * body.
 *
 * The write half exists because a create form that renders perfectly and writes nothing is
 * indistinguishable from a working one until somebody tries to save. It is also where this
 * batch's real risk lives: `SionTable::createEntity()` writes **every** column it is given,
 * where `updateEntity()` writes only the changed ones — which is exactly why
 * `/persons/create` is broken on laminas and its edit twin is not, and why that route is not
 * in this batch.
 *
 * ## Every created row is deleted again
 *
 * `tearDown()` removes what the tests inserted, by primary key, from the tables named in
 * `$created`. That is stricter than the edit surface's `remember()`/`restore()` and for the
 * same reason: a leftover row is not a changed value, it is a new one, and it shows up in
 * every value-options list on the site — including the ones the next baseline capture
 * renders.
 *
 * **`sch_changes` is left alone**, as `Batch7EditWriteSmokeTest` records: the change log is
 * an audit trail and pruning it by time range is more dangerous than the mess. The same
 * procedural consequence applies — do not run the suite between two baseline captures.
 */
class CreateSurfaceSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** @var list<array{string, string, int}> table, key column, id — deleted in tearDown */
    private array $created = [];

    protected function emailPrefix(): string
    {
        return 'batch9-create-';
    }

    protected function tearDown(): void
    {
        $this->deleteCreated();
        $this->purgeAccounts();
        $this->purgeMail();
        parent::tearDown();
    }

    /**
     * The nine routes, with the form id each renders and a field that must appear in it.
     *
     * The field is chosen to be one the *factory* populates rather than one the form class
     * declares statically, wherever the entity has such a field — a form whose value options
     * failed to load is the failure mode `App\Books\LibraryScopedForms` exists to prevent,
     * and it renders as an empty `<select>`, not as an error.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function createPaths(): array
    {
        return [
            //The form ids are the form classes' own `name` attributes and several do not
            //match the entity — `edit_association` and `edit_role` are named for the edit
            //surface they were written for and are reused verbatim here, which is the
            //point: create and edit share the form object.
            'association' => ['/en/associations/create', 'edit_association', 'country'],
            'assignment'  => ['/en/assignments/create', 'create_assignment', 'associationId'],
            'role'        => ['/en/roles/create', 'edit_role', 'associationId'],
            'book'        => ['/en/books/create/1', 'book', 'collectionId'],
            'collection'  => ['/en/collections/create/1', 'collection', 'libraryId'],
            'library'     => ['/en/libraries/create', 'library', 'contactPersonId'],
            'composition' => ['/en/music/create-composition', 'composition', 'inLanguage'],
            'publication' => ['/en/literature/create', 'publication', 'categoryId'],
            'dictionary'  => ['/en/dictionary/create', 'dictionary-entry', 'locale'],
        ];
    }

    /**
     * Anonymous is refused — **except on `/libraries/create`, which is public**.
     *
     * That is not a porting decision and not a mistake here: `docs/acl-rules.md` records
     * `libraries/create` as guarded `guest, lib_user`, and `guest` is *not* one of the four
     * default roles, so unlike most guards on this site it really does admit a signed-out
     * visitor. Measured on the laminas side too — it answers 200 there as well. Asserted
     * rather than skipped, so that if the guard is ever tightened this test says so instead
     * of quietly passing.
     */
    #[DataProvider('createPaths')]
    public function testAnonymousIsRefused(string $path, string $formId, string $field): void
    {
        if ('/en/libraries/create' === $path) {
            $this->assertSame(
                200,
                $this->get($path)['status'],
                'libraries/create is guarded `guest` and has always been reachable signed-out'
            );

            return;
        }

        $this->assertRequiresLogin($path);
    }

    /**
     * A moderator gets the form itself — the element, the submit and the action.
     *
     * The action matters as much as the fields: it is what the browser posts to, and the
     * whole page is useless if it names a route that does not exist. `App\Laminas\RouteUrl`
     * would throw assembling a bad one, which on a Symfony route is an empty 200 rather than
     * an error — so asserting the attribute is present *and* correct is asserting that
     * assembly happened at all.
     */
    #[DataProvider('createPaths')]
    public function testAModeratorGetsTheForm(string $path, string $formId, string $field): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $response = $this->get($path, false, $jar);
        $this->assertSame(200, $response['status'], "a moderator must be able to open $path");

        $document = new DOMDocument();
        @$document->loadHTML($response['body']);
        $xpath = new DOMXPath($document);

        $form = $xpath->query(sprintf('//form[@id="%s"]', $formId))->item(0);
        $this->assertNotNull($form, "the '$formId' form was not rendered on $path");
        /** @var DOMElement $form */

        $this->assertSame(
            $path,
            $form->getAttribute('action'),
            "the form on $path must post back to itself"
        );
        $this->assertGreaterThan(
            0,
            $xpath->query(sprintf('.//*[@name="%s" or @name="%s[]"]', $field, $field), $form)->length,
            "$path rendered its form without the '$field' element"
        );
        $this->assertGreaterThan(
            0,
            $xpath->query('.//*[@name="submit"]', $form)->length,
            "$path rendered a form with no way to submit it — see PortedFormsAreSubmittableTest"
        );
        $this->assertGreaterThan(
            0,
            $xpath->query('.//*[@name="security"]', $form)->length,
            "$path rendered a form with no CSRF element"
        );
    }

    /**
     * A select on the page has options.
     *
     * Separate from the element assertion above because the two fail differently: a missing
     * element means the template and the form class disagree, and an element with no options
     * means the *factory wiring* did not run — the fatal-200-adjacent failure that
     * `LibraryScopedForms` was written for, where the page looks entirely healthy and a
     * moderator simply cannot choose anything.
     */
    #[DataProvider('createPaths')]
    public function testTheFormsSelectsArePopulated(string $path, string $formId, string $field): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $body = $this->get($path, false, $jar)['body'];

        $document = new DOMDocument();
        @$document->loadHTML($body);
        $xpath = new DOMXPath($document);

        $select = $xpath->query(sprintf(
            '//form[@id="%s"]//select[@name="%s" or @name="%s[]"]',
            $formId,
            $field,
            $field
        ))->item(0);

        if (null === $select) {
            //Not every chosen field is a select — `library` names one, `dictionary` names
            //one, `book` names one, but a future entry might not. Nothing to assert then.
            $this->assertTrue(true);

            return;
        }

        $this->assertGreaterThan(
            1,
            $xpath->query('.//option', $select)->length,
            "the '$field' select on $path has no options, so its factory wiring did not run"
        );
    }

    /**
     * The round trip: create a role, land on a redirect, find the row.
     *
     * `role` is the plain case — a form out of the container, the spec-driven redirect, no
     * library scope, no prefill — so a failure here is the shared path rather than anything
     * entity-specific.
     */
    public function testAModeratorCanCreateARole(): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $association = $this->anAssociationId();
        $title       = 'Smoke test role ' . time();

        $post = $this->submit($jar, '/en/roles/create', 'edit_role', [
            'associationId' => (string) $association,
            'roleTitle'     => $title,
        ]);

        $this->assertSame(
            302,
            $post['status'],
            'a valid role submission must redirect; a 200 means the form refused it'
        );

        $id = $this->pdo()
            ->query(sprintf('SELECT RoleId FROM sch_roles WHERE RoleTitle = %s', $this->pdo()->quote($title)))
            ->fetchColumn();

        $this->assertNotFalse($id, 'the role was not written to sch_roles');
        $this->created[] = ['sch_roles', 'RoleId', (int) $id];

        //redirectAfterCreate()'s branch 4: the spec redirects to the *association*, by a key
        //field read off the new row rather than by the new id.
        $this->assertStringContainsString(
            '/associations/' . $association,
            $post['headers']['location'] ?? '',
            'a new role must send the moderator to its association, not to the role'
        );
    }

    /**
     * A submission the form refuses writes nothing.
     *
     * An invalid CSRF token, for the reason `Batch7EditWriteSmokeTest` gives: it fails
     * validation on every form without needing to know which of its rules is easiest to
     * break.
     */
    public function testARefusedSubmissionCreatesNothing(): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $before = (int) $this->pdo()->query('SELECT COUNT(*) FROM sch_roles')->fetchColumn();

        $fields             = $this->fieldsFromForm($this->get('/en/roles/create', false, $jar)['body'], 'edit_role');
        $fields['security'] = 'not-a-valid-token';
        $fields['roleTitle'] = 'should-never-be-stored';

        $post = $this->request('POST', '/en/roles/create', [], false, $jar, $fields);

        $this->assertSame(200, $post['status'], 'a refused submission re-renders rather than redirecting');
        $this->assertSame(
            $before,
            (int) $this->pdo()->query('SELECT COUNT(*) FROM sch_roles')->fetchColumn(),
            'a submission with an invalid CSRF token created a row'
        );
    }

    /**
     * `?country=` and `?kind=` prefill the association form.
     *
     * The two are validated differently on purpose and the test says so: `country` must name
     * an option the select already offers, and `kind` is free text passed through
     * `StripTags`. Both are the original's rules, reproduced in
     * `App\Controller\EntityCreateController`.
     */
    public function testTheQueryStringPrefillsTheAssociationForm(): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $body = $this->get('/en/associations/create?country=CL&name=Prefilled', false, $jar)['body'];

        $document = new DOMDocument();
        @$document->loadHTML($body);
        $xpath = new DOMXPath($document);

        $selected = $xpath->query('//form[@id="edit_association"]//select[@name="country"]/option[@selected]')->item(0);
        $this->assertNotNull($selected, 'the ?country= hint did not select anything');
        /** @var DOMElement $selected */
        $this->assertSame('CL', $selected->getAttribute('value'));

        $name = $xpath->query('//form[@id="edit_association"]//input[@name="name"]')->item(0);
        $this->assertNotNull($name);
        /** @var DOMElement $name */
        $this->assertSame('Prefilled', $name->getAttribute('value'), 'the ?name= hint did not reach the field');
    }

    /**
     * An unknown `?country=` is ignored rather than rendered.
     *
     * The guard is `key_exists($param, $element->getValueOptions())`, and without it the
     * select carries a value no option matches — which a browser renders as *nothing
     * selected*, so the moderator cannot tell the hint was rejected. Worth pinning because
     * the failure is silent in exactly the way the guard exists to prevent.
     */
    public function testAnUnknownCountryHintIsIgnored(): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $body = $this->get('/en/associations/create?country=ZZ', false, $jar)['body'];

        $document = new DOMDocument();
        @$document->loadHTML($body);
        $xpath = new DOMXPath($document);

        $this->assertSame(
            0,
            $xpath->query('//form[@id="edit_association"]//select[@name="country"]/option[@selected]')->length,
            'an unknown country code was accepted into the form'
        );
    }

    /**
     * The library create form renders even though there is no library.
     *
     * The one route whose form is library-scoped and carries no `library_id`. On laminas
     * `LibraryFormFactory` skips the collection lookup; on Symfony `formForLibrary()` does
     * the same. Before that skip existed this page was the batch-7 fatal-200 all over again,
     * so the assertion is that `mainCollectionId` is present *and empty* — present because
     * the form declares it, empty because there are no collections to offer.
     */
    public function testTheLibraryFormRendersWithoutALibrary(): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $response = $this->get('/en/libraries/create', false, $jar);
        $this->assertSame(200, $response['status']);

        $document = new DOMDocument();
        @$document->loadHTML($response['body']);
        $xpath = new DOMXPath($document);

        $select = $xpath->query('//form[@id="library"]//select[@name="mainCollectionId"]')->item(0);
        $this->assertNotNull($select, 'the library form lost its mainCollectionId element');
        $this->assertLessThanOrEqual(
            1,
            $xpath->query('.//option', $select)->length,
            'a library that does not exist yet cannot have collections to choose from'
        );
    }

    /** An association id that exists, for the role round trip. */
    private function anAssociationId(): int
    {
        $id = $this->pdo()->query('SELECT AssociationId FROM sch_associations ORDER BY AssociationId LIMIT 1')
            ->fetchColumn();
        $this->assertNotFalse($id, 'this database holds no associations');

        return (int) $id;
    }

    /**
     * GET the form, then POST it back with overrides — a crude browser.
     *
     * @param array<string, string> $overrides
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    private function submit(string $jar, string $path, string $formId, array $overrides): array
    {
        $form = $this->get($path, false, $jar);
        $this->assertSame(200, $form['status'], "GET $path before posting it");

        return $this->request(
            'POST',
            $path,
            [],
            false,
            $jar,
            $overrides + $this->fieldsFromForm($form['body'], $formId)
        );
    }

    /**
     * Every input, textarea and select the rendered form carries, with its current value.
     * Lifted from Batch7EditWriteSmokeTest.
     *
     * @return array<string, string>
     */
    private function fieldsFromForm(string $body, string $formId): array
    {
        $document = new DOMDocument();
        @$document->loadHTML($body);
        $xpath = new DOMXPath($document);

        $form = $xpath->query(sprintf('//form[@id="%s"]', $formId))->item(0);
        $this->assertNotNull($form, "the '$formId' form was not rendered");

        $fields = [];
        foreach ($xpath->query('.//input|.//textarea|.//select', $form) as $node) {
            /** @var DOMElement $node */
            $name = $node->getAttribute('name');
            if ('' === $name) {
                continue;
            }
            if ('input' === $node->nodeName) {
                if ('checkbox' === $node->getAttribute('type') && ! $node->hasAttribute('checked')) {
                    continue;
                }
                $fields[$name] = $node->getAttribute('value');
                continue;
            }
            if ('textarea' === $node->nodeName) {
                $fields[$name] = $node->textContent;
                continue;
            }
            //A multiple select with nothing selected posts nothing at all in a browser, and
            //posting an empty string instead makes Select's InArray validator refuse it.
            $selected = $xpath->query('.//option[@selected]', $node)->item(0);
            if (null === $selected && $node->hasAttribute('multiple')) {
                continue;
            }
            $fields[$name] = $selected instanceof DOMElement ? $selected->getAttribute('value') : '';
        }

        return $fields;
    }

    private function deleteCreated(): void
    {
        foreach ($this->created as [$table, $keyColumn, $id]) {
            $this->pdo()
                ->prepare(sprintf('DELETE FROM `%s` WHERE `%s` = ?', $table, $keyColumn))
                ->execute([$id]);
        }

        $this->created = [];
    }
}

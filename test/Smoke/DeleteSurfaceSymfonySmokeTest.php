<?php

namespace SchoenstattTest\Smoke;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The delete surface: the seven `SionController::deleteAction()` routes, on the Symfony
 * front controller.
 *
 * ## What this file covers and what covers the rest
 *
 * Every assertion here is **read-only**. A GET on a delete route renders a confirmation and
 * destroys nothing — which is the first thing worth asserting, and cheap insurance against a
 * future refactor moving the delete out of the POST branch.
 *
 * The destructive half lives in `AssociationDeleteSmokeTest`, which builds fixtures, posts a
 * genuine confirmation, and characterizes what the delete leaves behind. That file was
 * written the day `association-delete` became reachable, against the laminas rendering; the
 * capsule now serves the Symfony route at the same URL, so it exercises
 * `App\Controller\EntityDeleteController` end to end without a line changing. The Cancel
 * refusal is there too, next to the fixtures it needs.
 *
 * ## Why the confirmation pages use real records
 *
 * Rendering one changes nothing, so there is no reason to build eight fixtures. The ids are
 * the same records `Batch7EditSurfaceSmokeTest` edits and the port baseline captures, so one
 * row covers a show page, an edit form and a delete confirmation, and the three files fail
 * together if any of them leaves the dump.
 *
 * ## The two rows in the provider that are not ordinary
 *
 * `/roles/1/delete` renders a confirmation for a record whose projection cannot build it —
 * role 1 hangs off association 2, which `getRole()` filters, and 142 of the 1,468 rows in
 * `sch_roles` are in that state. It renders because `deleteAction()` asks `existsEntity()`,
 * a direct `SELECT`, where `editAction()` asks `getObject()`: `/roles/1/edit` answers "Role
 * not found." and `/roles/1/delete` offers to delete it. Reproduced deliberately, and
 * asserted here so that the two front controllers cannot drift apart on it.
 *
 * `/SL499999T/delete` names no text at all, and is the path that proves a fix rather than a
 * port. `text`'s `delete_action_redirect_route` was `text-delete` — the delete route itself,
 * which needs an `sw_id` — so `redirectAfterDelete()` asked the router to assemble it with no
 * parameters, got `Missing parameter "sw_id"`, and threw. **Every** exit from the action for a
 * text was a 500, including the successful one, after the row had already gone. It is `texts`
 * now, on both front controllers.
 */
class DeleteSurfaceSymfonySmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /**
     * The seven routes, plus the two branch paths — with the record each one names, so a
     * test can assert it survived.
     *
     * `association-delete` is deliberately absent from the confirmation cases: it is
     * `AssociationDeleteSmokeTest`'s subject from end to end, and duplicating it here would
     * mean two files to update when it changes. It *is* in the anonymous-refusal case,
     * because that one is about the guard and the whole surface should be swept.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: int}>
     *         label => [path, table, key column, id]
     */
    public static function confirmationCases(): array
    {
        return [
            'publication' => ['/en/SL202186L/delete', 'sch_publications', 'PublicationId', 2186],
            'text'        => ['/en/SL400003T/delete', 'texts', 'TextId', 3],
            'composition' => ['/en/SL500001C/delete', 'mus_compositions', 'CompositionId', 1],
            'person'      => ['/en/persons/494/delete', 'sch_persons', 'PersonId', 494],
            'assignment'  => ['/en/assignments/77/delete', 'sch_assignments', 'AssignmentId', 77],
            'role'        => ['/en/roles/255/delete', 'sch_roles', 'RoleId', 255],
            //See the class docblock: exists in the table, invisible to its projection.
            'unhydratable role' => ['/en/roles/1/delete', 'sch_roles', 'RoleId', 1],
        ];
    }

    /** @return array<string, array{0: string}> label => [path] */
    public static function everyRoute(): array
    {
        $paths = ['association' => '/en/SL100319A/delete'];
        foreach (self::confirmationCases() as $label => $case) {
            $paths[$label] = $case[0];
        }

        return array_map(static fn (string $path): array => [$path], $paths);
    }

    protected function emailPrefix(): string
    {
        return 'delete-surface-';
    }

    protected function tearDown(): void
    {
        $this->purgeAccounts();
        $this->purgeMail();
        parent::tearDown();
    }

    /**
     * The guard refuses every one of them anonymously.
     *
     * This is the assertion that would have caught `association-delete` answering the
     * association's public show page for five years, and it sweeps the whole surface rather
     * than the six routes the confirmation cases cover.
     */
    #[DataProvider('everyRoute')]
    public function testTheGuardRefusesAnonymously(string $path): void
    {
        $response = $this->get($path);

        $this->assertSame(302, $response['status'], "$path must not render for an anonymous visitor");
        $this->assertStringContainsString(
            '/en/user/login?redirect=' . $path,
            $response['redirect'],
            'a refused delete confirmation must send an anonymous visitor to sign in'
        );
    }

    /**
     * A moderator is shown a confirmation carrying a CSRF token, and the record survives.
     *
     * Three claims in one, because they are one behaviour: the page renders, it renders
     * something that can actually be submitted, and a GET destroys nothing. The token is the
     * part that would fail silently — a confirmation form without one renders perfectly and
     * makes every delete impossible, and the same page one cross-site request away from
     * working if the controller stopped checking it.
     */
    #[DataProvider('confirmationCases')]
    public function testAModeratorSeesAConfirmationAndTheRecordSurvives(
        string $path,
        string $table,
        string $key,
        int $id
    ): void {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $response = $this->get($path, false, $jar);

        $this->assertSame(200, $response['status'], "$path must render a confirmation for a moderator");
        $this->assertArrayHasKey(
            'security',
            $this->fieldsFromForm($response['body']),
            'the confirmation form must carry a CSRF token'
        );
        $this->assertTrue($this->exists($table, $key, $id), 'a GET must not delete anything');
    }

    /**
     * Served by the Symfony kernel, not bridged to laminas.
     *
     * The discriminator is `slm_locale`: laminas sets it on every response and a ported route
     * never does. Without this the whole file would pass against the bridge, and a route that
     * silently fell through to laminas — the failure mode `App\Sion\ReservedVerbs` exists to
     * prevent, and which really did swallow nine of these paths — would look like success.
     */
    #[DataProvider('confirmationCases')]
    public function testTheRouteIsServedBySymfony(string $path, string $table, string $key, int $id): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $response = $this->get($path, false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertStringNotContainsString(
            'slm_locale',
            $response['headers']['set-cookie'] ?? '',
            "$path must be served by the Symfony kernel, not bridged to laminas"
        );
    }

    /**
     * The Cancel button cannot submit the form.
     *
     * A `type="submit"` here is not a cosmetic regression — it is the data-loss bug this
     * batch fixed. SionModel's `DeleteEntityForm` added Cancel with no element type, and
     * `FormButton` renders a typeless element as a submit, so clicking Cancel posted the
     * form; `deleteAction()` validated the CSRF token and never looked at which button was
     * pressed. Measured on 2026-08-14 against a fixture: 302 to the entity index, row gone.
     *
     * Asserted on the rendered markup because that is where the fix lives and what a browser
     * obeys. The server-side second lock is asserted in `AssociationDeleteSmokeTest`.
     */
    #[DataProvider('confirmationCases')]
    public function testCancelIsNotASubmitButton(string $path, string $table, string $key, int $id): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $response = $this->get($path, false, $jar);
        $cancel   = $this->cancelButton($response['body']);

        $this->assertNotNull($cancel, 'the confirmation must render a cancel button');
        $this->assertSame(
            'button',
            $cancel->getAttribute('type'),
            'the cancel button is a submit again — clicking it deletes the record. See '
            . 'SionModel\Form\DeleteEntityForm.'
        );
        $this->assertTrue($this->exists($table, $key, $id), 'rendering the page must not delete anything');
    }

    /**
     * The heading names the entity, and it is the phrase laminas files.
     *
     * `'Delete ' . $entity` is assembled by concatenation, which is normally how a page
     * fills the phrase table with one row per record. Bounded here — the entity key is one of
     * seven — but the *text domain* is not automatic: a ported route that declares the wrong
     * one files a duplicate under `default` and renders English in the other four languages
     * for a phrase that was already translated. This asserts the string; the domain is what
     * `config/symfony/routes.php` declares per route.
     */
    #[DataProvider('headings')]
    public function testTheHeadingNamesTheEntity(string $path, string $heading): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $response = $this->get($path, false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString("<h1>$heading</h1>", $response['body']);
        $this->assertStringContainsString("<title>$heading - Schoenstatt Link</title>", $response['body']);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function headings(): array
    {
        return [
            'publication' => ['/en/SL202186L/delete', 'Delete publication'],
            'text'        => ['/en/SL400003T/delete', 'Delete text'],
            'composition' => ['/en/SL500001C/delete', 'Delete composition'],
            'person'      => ['/en/persons/494/delete', 'Delete person'],
            'assignment'  => ['/en/assignments/77/delete', 'Delete assignment'],
            'role'        => ['/en/roles/255/delete', 'Delete role'],
        ];
    }

    /**
     * A well-formed identifier naming nothing redirects to the entity's index with a flash —
     * it does not 500, and it does not render a confirmation for a record that is not there.
     *
     * The text case is the one that used to throw. See the class docblock.
     *
     * @param string $path  a syntactically valid URL for a record that does not exist
     * @param string $index where laminas' `redirectAfterDelete()` sends the visitor
     */
    #[DataProvider('missingRecords')]
    public function testAMissingRecordRedirectsToTheIndex(string $path, string $index): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $response = $this->get($path, false, $jar);

        $this->assertSame(
            302,
            $response['status'],
            "$path names nothing and must redirect rather than render or throw"
        );
        $this->assertStringEndsWith($index, $response['redirect']);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function missingRecords(): array
    {
        return [
            //`texts`, not `text-delete`: the whole point of the redirect fix.
            'text'        => ['/en/SL499999T/delete', '/en/texts'],
            'publication' => ['/en/SL299999L/delete', '/en/literature'],
            'composition' => ['/en/SL599999C/delete', '/en/music'],
            'association' => ['/en/SL199999A/delete', '/en/associations'],
            'person'      => ['/en/persons/99999/delete', '/en/movement'],
            'role'        => ['/en/roles/99999/delete', '/en/roles'],
            'assignment'  => ['/en/assignments/99999/delete', '/en/associations'],
        ];
    }

    /**
     * A submission with a bad CSRF token re-renders the form and deletes nothing.
     *
     * The status is 401, which is the wrong code for a failed token — 400 or 403 is right —
     * and it is asserted rather than corrected because it is what the laminas action answers
     * and this batch is a port. Filed in docs/BACKLOG.md.
     *
     * Run against a **real** record on purpose: the value of the test is that the record is
     * still there afterwards, and asserting that about a real row is a stronger claim than
     * asserting it about a fixture.
     */
    public function testASubmissionWithABadTokenIsRefusedAndDeletesNothing(): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $fields             = $this->fieldsFromForm($this->get('/en/roles/255/delete', false, $jar)['body']);
        $fields['security'] = 'not-a-valid-token';

        $response = $this->request('POST', '/en/roles/255/delete', [], false, $jar, $fields);

        $this->assertSame(401, $response['status'], 'a bad token must not be accepted');
        $this->assertTrue($this->exists('sch_roles', 'RoleId', 255), 'a refused submission deleted a real role');
    }

    // -- helpers -------------------------------------------------------------

    private function exists(string $table, string $key, int $id): bool
    {
        //Interpolated rather than bound: a table and column name cannot be a placeholder,
        //and both come from this file's own provider, never from a request.
        $statement = $this->pdo()->prepare("SELECT COUNT(*) FROM `$table` WHERE `$key` = ?");
        $statement->execute([$id]);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @return array<string, string> the confirmation form's fields, name => value */
    private function fieldsFromForm(string $body): array
    {
        $form = $this->deleteForm($body);
        $this->assertNotNull($form, 'the page rendered no form pointing at a delete URL');

        $document = $form->ownerDocument;
        $this->assertNotNull($document);
        $xpath = new DOMXPath($document);

        $fields = [];
        foreach ($xpath->query('.//input|.//textarea|.//select', $form) as $node) {
            /** @var DOMElement $node */
            $name = $node->getAttribute('name');
            if ('' !== $name) {
                $fields[$name] = $node->getAttribute('value');
            }
        }

        return $fields;
    }

    private function cancelButton(string $body): ?DOMElement
    {
        $form = $this->deleteForm($body);
        if (null === $form || null === $form->ownerDocument) {
            return null;
        }

        $node = (new DOMXPath($form->ownerDocument))->query('.//button[@name="cancel"]', $form)->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    /**
     * The confirmation form, found by its action rather than by `//form`.
     *
     * `//form` would find the navbar search box first, which is on every page and has no
     * fields this file wants — the mistake `AssociationDeleteSmokeTest` records making.
     */
    private function deleteForm(string $body): ?DOMElement
    {
        $document = new DOMDocument();
        @$document->loadHTML($body);

        $node = (new DOMXPath($document))->query('//form[contains(@action, "/delete")]')->item(0);

        return $node instanceof DOMElement ? $node : null;
    }
}

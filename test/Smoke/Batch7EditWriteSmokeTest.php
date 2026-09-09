<?php

namespace SchoenstattTest\Smoke;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The **write** half of batch 7: GET an edit form, change a field, POST it, and read the
 * value back out of the database.
 *
 * ## Why this file exists separately, and why it existed too late
 *
 * Every other assertion in the batch is a GET. The eight ported edit routes were verified
 * to *render* — three access outcomes each, form fields asserted in the body — and their
 * write path, which is the entire point of an edit form, had no coverage at all. That gap
 * is easy to miss because it looks covered: `AssociationEditSymfonySmokeTest` does a full
 * POST round trip, but against `AssociationEditController`, a hand-written class none of
 * these eight routes runs through. The shared path had a passing neighbour and nothing of
 * its own.
 *
 * It matters more here than for any earlier batch. Production has served the Symfony
 * kernel site-wide since 2026-08-11, so these are eight live write paths against the real
 * database, and the failure mode documented in `App\Sion\EntityEdit` is not a 500: a form
 * whose value options did not populate renders its fields *empty*, and saving that form
 * writes the blanks back over real data.
 *
 * ## The three chosen, and what each is here to prove
 *
 * | route | what only this one exercises |
 * |---|---|
 * | `roles/role/edit` | the plain path: shared update, spec-driven redirect (branch 2, `showRouteKey`) |
 * | `collections/collection/edit` | a form built by `App\Books\LibraryScopedForms` rather than the container, plus the per-row `library_<id>` ACL |
 * | `text-edit` | a `REDIRECT_TARGET` override, and an `sw_id` route parameter rather than a numeric one |
 *
 * Between them they cover every distinct mechanism the eight share. The other five differ
 * only in which template renders and which fields exist, which the GET tests already check.
 *
 * ## Every test restores what it touched
 *
 * These write to the capsule's copy of production data. `remember()` snapshots the row
 * before the first write and `tearDown()` puts it back, the arrangement
 * `AssociationEditSymfonySmokeTest` established — otherwise a run leaves the capsule
 * subtly different from the dump and the next baseline capture diffs against a moved
 * target.
 *
 * **What it does *not* restore is the change log.** `SionTable::updateEntity()` files rows
 * in `sch_changes`, and a run of this file leaves about seventeen of them behind. They are
 * left deliberately: the change log is an audit trail, and deleting rows from it by
 * time-range would be a more dangerous cleanup than the mess it tidies.
 *
 * The consequence is procedural and belongs with the one docs/laminas-exit.md already records
 * for translation catalogs: **do not run the test suite between two baseline captures.**
 * `/sm/view-changes` renders the newest 500 changes and is one of the captured paths, so a
 * suite run between the laminas and Symfony captures shows up as drift on a page this
 * batch never touched.
 */
class Batch7EditWriteSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** @var array<string, array<string, string|null>> table => column => value, for tearDown */
    private array $original = [];

    protected function emailPrefix(): string
    {
        return 'batch7-write-';
    }

    protected function tearDown(): void
    {
        $this->restore();
        $this->purgeAccounts();
        $this->purgeMail();
        parent::tearDown();
    }

    /**
     * path, form id, the field to change, the table, its key column, the row id, and the
     * database column that field lands in.
     *
     * The field chosen for each is free text with no uniqueness constraint and nothing
     * derived from it, so a marker value cannot break a later assertion elsewhere.
     *
     * @return array<string, array{string, string, string, string, string, int, string}>
     */
    public static function writablePaths(): array
    {
        return [
            'role' => [
                '/en/roles/255/edit', 'edit_role', 'roleTitle',
                'sch_roles', 'RoleId', 255, 'RoleTitle',
            ],
            'collection' => [
                '/en/collections/1/edit', 'collection', 'description',
                'lib_collections', 'CollectionId', 1, 'Description',
            ],
            'text' => [
                '/en/SL400003T/edit', 'text', 'title',
                'texts', 'TextId', 3, 'Title',
            ],
        ];
    }

    /**
     * The round trip. A 302 and the value in the database — both, because either alone
     * lies: a redirect proves the controller thought it saved, and a changed column
     * without the redirect would mean the visitor never learned it worked.
     */
    #[DataProvider('writablePaths')]
    public function testAModeratorCanSaveAChange(
        string $path,
        string $formId,
        string $field,
        string $table,
        string $keyColumn,
        int $id,
        string $column
    ): void {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $this->remember($table, $keyColumn, $id, [$column, 'UpdatedOn', 'UpdatedBy']);

        $marker = 'Smoke test ' . time();
        $post   = $this->submit($jar, $path, $formId, [$field => $marker]);

        $this->assertSame(
            302,
            $post['status'],
            "a valid submission to $path must redirect; a 200 means the form refused it and "
            . 're-rendered, and the body will say why'
        );
        $this->assertSame(
            $marker,
            $this->column($table, $keyColumn, $id, $column),
            "the change did not reach $table.$column"
        );
    }

    /**
     * An invalid submission re-renders and writes nothing.
     *
     * The CSRF token is what makes this cheap to provoke on every form: sending a wrong one
     * fails validation for certain, whatever fields the form has, without needing to know
     * which of its rules is easiest to break.
     */
    #[DataProvider('writablePaths')]
    public function testARefusedSubmissionWritesNothing(
        string $path,
        string $formId,
        string $field,
        string $table,
        string $keyColumn,
        int $id,
        string $column
    ): void {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $this->remember($table, $keyColumn, $id, [$column, 'UpdatedOn', 'UpdatedBy']);
        $before = $this->column($table, $keyColumn, $id, $column);

        $fields              = $this->fieldsFromForm($this->get($path, false, $jar)['body'], $formId);
        $fields['security']  = 'not-a-valid-token';
        $fields[$field]      = 'should-never-be-stored';

        $post = $this->request('POST', $path, [], false, $jar, $fields);

        $this->assertSame(200, $post['status'], 'a refused submission re-renders rather than redirecting');
        $this->assertSame(
            $before,
            $this->column($table, $keyColumn, $id, $column),
            'a submission with an invalid CSRF token wrote to the database'
        );
    }

    /**
     * Saving does not blank the columns the form does not render.
     *
     * This is the defect `App\Sion\EntityEdit` warns about, from the direction that actually
     * bites: `updateEntity()` writes what `getData()` returns, so a field the form knows
     * about but could not populate contributes an empty value and overwrites a real one. The
     * association form had exactly this bug with `eventsJson`.
     *
     * `sch_roles.ShouldAlwaysBeFilled` is the clean case: a real column that `RoleForm`
     * does not render — the form has `associationId`, `roleTitle`, `sort`,
     * `isSinglePosition`, `isMainRole`, `isMainContact` and `isActive`, and not this one.
     * Set it, save the form, and it must still be set.
     *
     * A boolean rather than free text, which is if anything the sharper probe: `getData()`
     * contributing a null for an absent field would land as `0` here, and `0` is a
     * perfectly plausible-looking value for a flag. The assertion is what tells the two
     * apart.
     */
    public function testSavingLeavesUnrenderedColumnsAlone(): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $this->remember(
            'sch_roles',
            'RoleId',
            255,
            ['RoleTitle', 'ShouldAlwaysBeFilled', 'UpdatedOn', 'UpdatedBy']
        );

        $this->pdo()->exec('UPDATE sch_roles SET ShouldAlwaysBeFilled = 1 WHERE RoleId = 255');

        $post = $this->submit($jar, '/en/roles/255/edit', 'edit_role', ['roleTitle' => 'Member']);

        $this->assertSame(302, $post['status']);
        $this->assertSame(
            '1',
            $this->column('sch_roles', 'RoleId', 255, 'ShouldAlwaysBeFilled'),
            'saving the role form erased a column the form does not render'
        );
    }

    /**
     * The custom redirect really is the custom one.
     *
     * `text` declares `REDIRECT_TARGET => 'text'`, reproducing
     * `TextsController::redirectAfterEdit()`, which sends the moderator to the text itself
     * rather than to the index the entity spec would otherwise pick. Asserting the
     * destination is what tells the two apart — both are 302s.
     */
    public function testTextRedirectsToTheTextRatherThanAnIndex(): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $this->remember('texts', 'TextId', 3, ['Title', 'UpdatedOn', 'UpdatedBy']);

        $post = $this->submit($jar, '/en/SL400003T/edit', 'text', ['title' => 'Smoke redirect ' . time()]);

        $this->assertSame(302, $post['status']);
        $this->assertStringContainsString(
            '/SL400003T',
            $post['redirect'],
            'a saved text returns to the text, not to a list'
        );
    }

    // ------------------------------------------------------------------ helpers

    /**
     * GET the form, apply the overrides to the fields it rendered, POST the whole thing
     * back.
     *
     * Submitting the *whole* form is the point, as it is in the association test: a partial
     * POST drops every field it omits, which no browser does and which would make a
     * "nothing else changed" assertion meaningless.
     *
     * @param array<string, string> $overrides
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
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
     * Every input, textarea and select the rendered form carries, with its current value — a
     * crude browser. Lifted from AssociationEditSymfonySmokeTest, with the form id as a
     * parameter because this file drives three different forms.
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
            $selected = $xpath->query('.//option[@selected]', $node)->item(0);
            $fields[$name] = $selected instanceof DOMElement ? $selected->getAttribute('value') : '';
        }

        return $fields;
    }

    /**
     * Snapshot a row's columns so tearDown can put them back. Keyed by table so one test
     * may touch more than one.
     *
     * @param list<string> $columns
     */
    private function remember(string $table, string $keyColumn, int $id, array $columns): void
    {
        $key = $table . ':' . $id;
        if (isset($this->original[$key])) {
            return;
        }

        $statement = $this->pdo()->query(sprintf(
            'SELECT `%s` FROM `%s` WHERE `%s` = %d',
            implode('`, `', $columns),
            $table,
            $keyColumn,
            $id
        ));
        /** @var array<string, string|null>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row, "row $id of $table must exist for this test to mean anything");

        $this->original[$key] = ['__table' => $table, '__key' => $keyColumn, '__id' => (string) $id] + $row;
    }

    private function restore(): void
    {
        foreach ($this->original as $row) {
            $table  = (string) $row['__table'];
            $key    = (string) $row['__key'];
            $id     = (int) $row['__id'];
            unset($row['__table'], $row['__key'], $row['__id']);

            $sets   = [];
            $values = [];
            foreach ($row as $column => $value) {
                $sets[]   = sprintf('`%s` = ?', $column);
                $values[] = $value;
            }
            $values[] = $id;

            $this->pdo()
                ->prepare(sprintf('UPDATE `%s` SET %s WHERE `%s` = ?', $table, implode(', ', $sets), $key))
                ->execute($values);
        }

        $this->original = [];
    }

    /** @return string|null */
    private function column(string $table, string $keyColumn, int $id, string $column): ?string
    {
        $statement = $this->pdo()->query(sprintf(
            'SELECT `%s` FROM `%s` WHERE `%s` = %d',
            $column,
            $table,
            $keyColumn,
            $id
        ));
        $value = $statement->fetchColumn();

        return false === $value || null === $value ? null : (string) $value;
    }
}

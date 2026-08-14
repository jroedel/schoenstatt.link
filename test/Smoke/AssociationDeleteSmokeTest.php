<?php

namespace SchoenstattTest\Smoke;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PDO;

/**
 * The association delete path, reachable for the first time.
 *
 * ## Why a route that existed for five years had never been tested
 *
 * `association-delete` declared its `sw_id` as `SL1[0-9]{4,4}A` — `SL1` plus four digits,
 * five in total — where every association identifier has six. It therefore matched nothing
 * that exists, from 2020 until 2026-08-14, and `/SL1xxxxxA/delete` was quietly answered by
 * the *show* route on both front controllers. Its four sibling delete routes
 * (`publication-`, `text-`, `event-`, `composition-delete`) all derive their constraint
 * from `SchoenstattLinkIdentifier::ENTITY_REGEXS`; only this one was written out by hand,
 * and only this one drifted. The fix derives it too.
 *
 * Correcting a constraint is a one-line change that turns a **destructive** page on, live,
 * for `sch_general_moderator`. Nothing had ever exercised it, so this file does — the
 * confirmation, the CSRF token, and what the delete actually does to the record and to the
 * rows that referenced it.
 *
 * ## Which tests touch real data and which do not
 *
 * The three read-only assertions run against a **real** association (`SL100319A`), because
 * refusing anonymously, rendering a confirmation and rejecting a bad token all leave the
 * record exactly where it was. Only the two destructive tests build a fixture, and those
 * use ids far above the live maximum of 571 so they can collide with nothing.
 *
 * ## Why the destructive tests borrow their token from another page
 *
 * A fixture inserted with SQL is invisible to the confirmation *page*.
 * `SchoenstattTable::getAssociation()` reads through `getAssociations()`, which is cached,
 * and the cache is only dropped by an application-level write — so the row exists as far
 * as the database is concerned while the view cannot find it to build a form action. The
 * cache lives in APCu under the web server's SAPI, so a test process cannot clear it, and
 * `/sm/clear-persistent-cache` deliberately demands an API key this suite does not carry.
 *
 * The POST path does not go through that cache: `deleteAction()` checks
 * `SionTable::existsEntity()`, which queries the table directly, and returns its redirect
 * before anything asks for the entity object. So these tests mint a CSRF token on the real
 * association's confirmation page and post it to the fixture's URL. That works because
 * `Laminas\Form\Element\Csrf` binds its token to the element name and the session, not to
 * the record — which is standard, and worth knowing when reading these tests rather than
 * discovering it while debugging one.
 *
 * `sch_changes` is deliberately **not** cleaned up. `SionTable::deleteEntity()` files an
 * `entryDeleted` row, and the change log is an audit trail — deleting from it by time
 * range would be a more dangerous cleanup than the mess it tidies. Same arrangement as
 * `Batch7EditWriteSmokeTest`, and the same consequence: **do not run the suite between two
 * baseline captures**, because `/sm/view-changes` is a captured path.
 *
 * ## The orphaning characterization, and why it pins rather than fixes
 *
 * `SionTable::deleteEntity()` is a bare `DELETE ... WHERE <key> = ?`. There is no cascade
 * in the code and **no foreign key in the schema** — measured 2026-08-14: zero constraints
 * reference `sch_associations`. Deleting a parent leaves its children pointing at a row
 * that is gone; deleting an association leaves its roles the same way.
 *
 * That is not a defect this change introduces, and not one it should fix alone: it is how
 * all five delete routes have always behaved, and the four reachable ones have behaved
 * that way in production for years. The capsule already carries 142 roles and one
 * association whose referent no longer exists. So this pins the behaviour — a
 * characterization test, so whoever decides what deletion *should* do to dependants finds
 * the current answer written down and fails this test on purpose. That decision is filed
 * in docs/BACKLOG.md.
 */
class AssociationDeleteSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /**
     * A real association, used only where nothing is destroyed.
     *
     * `SL100319A` is "Original Schoenstatt Shrine" — the same record
     * `ReservedVerbRoutingSmokeTest` uses, so the two files fail together if it ever
     * leaves the dump.
     */
    private const REAL_PATH = '/en/SL100319A/delete';
    private const REAL_ID   = 319;

    /** Well above the live maximum of 571, so a fixture can collide with nothing real. */
    private const FIRST_ID = 99001;

    /** Incremented per test so a destroyed fixture is never resurrected under its old id. */
    private static int $nextId = self::FIRST_ID;

    private int $parentId;
    private int $childId;
    private string $fixturePath;

    /** @var array<string, int> table => the AUTO_INCREMENT value found before this test ran */
    private array $autoIncrement = [];

    protected function emailPrefix(): string
    {
        return 'association-delete-';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->parentId = self::$nextId;
        $this->childId  = self::$nextId + 1;
        self::$nextId  += 2;
        // sw_id is the association's own id offset by the entity's starting number, so
        // 99001 reads as SL199001A — six digits, the shape the corrected constraint wants.
        $this->fixturePath = sprintf('/en/SL%dA/delete', 100000 + $this->parentId);

        $this->rememberAutoIncrement();
        $this->removeFixtures();
    }

    protected function tearDown(): void
    {
        $this->removeFixtures();
        $this->restoreAutoIncrement();
        $this->purgeAccounts();
        $this->purgeMail();
        parent::tearDown();
    }

    /**
     * The guard runs, which is the whole reason the route being unreachable mattered.
     *
     * Before the constraint was corrected this answered **200** and rendered the
     * association's public show page to anybody at all.
     */
    public function testTheConfirmationIsRefusedAnonymously(): void
    {
        $response = $this->get(self::REAL_PATH);

        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString('/en/user/login?redirect=' . self::REAL_PATH, $response['redirect']);
    }

    /**
     * A moderator gets a confirmation page with a CSRF token — not a deletion.
     *
     * A GET must never destroy anything, and asserting the record survives is cheap
     * insurance against a future refactor that moves the delete out of the POST branch.
     */
    public function testAModeratorIsShownAConfirmationAndNothingIsDeletedYet(): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $response = $this->get(self::REAL_PATH, false, $jar);

        $this->assertSame(200, $response['status'], 'the delete confirmation must render for a moderator');
        $this->assertArrayHasKey(
            'security',
            $this->fieldsFromForm($response['body']),
            'the confirmation form must carry a CSRF token; without one the delete is a '
            . 'cross-site request away'
        );
        $this->assertTrue($this->exists(self::REAL_ID), 'a GET must not delete anything');
    }

    /**
     * An invalid token is refused and destroys nothing.
     *
     * The record surviving is the assertion that matters. A status check alone would pass
     * against a controller that deleted the row and *then* complained about the token.
     */
    public function testASubmissionWithAnInvalidTokenDeletesNothing(): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $fields             = $this->fieldsFromForm($this->get(self::REAL_PATH, false, $jar)['body']);
        $fields['security'] = 'not-a-valid-token';

        $response = $this->request('POST', self::REAL_PATH, [], false, $jar, $fields);

        $this->assertNotSame(302, $response['status'], 'a refused submission must not redirect as if it worked');
        $this->assertTrue(
            $this->exists(self::REAL_ID),
            'a real association was deleted by a submission with an invalid CSRF token'
        );
    }

    /**
     * The destructive path, end to end.
     *
     * This is the assertion the route has never had: a valid confirmation really does
     * remove the record. Worth having as much for the negative direction — a delete that
     * silently failed while redirecting as though it had worked would be
     * indistinguishable from success in a browser.
     */
    public function testAValidConfirmationDeletesTheAssociation(): void
    {
        $this->createFixtures();
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $response = $this->confirmDeletion($jar);

        $this->assertSame(302, $response['status'], 'a valid confirmation must redirect after deleting');
        $this->assertFalse($this->exists($this->parentId), 'the association was not deleted');
    }

    /**
     * A submission naming the **cancel** button deletes nothing.
     *
     * This is the regression test for a data-loss bug that was live on every delete
     * confirmation and in the two delete modals, and it deserves its history stated.
     *
     * `DeleteEntityForm` added Cancel with its `'type' => 'Submit'` line commented out —
     * which does not make it inert, it makes it a plain `Laminas\Form\Element`, and
     * `View\Helper\FormButton` renders a typeless element as `type="submit"`. So Cancel
     * submitted the delete form. `deleteAction()` validated the CSRF token and never looked
     * at which button had been pressed. **Clicking Cancel deleted the record**: measured
     * 2026-08-14 against a fixture, 302 to `/en/associations` with the row gone.
     *
     * Two locks now, and this test drives the *second* one, because the first cannot be
     * reached over HTTP: the browser no longer submits Cancel at all, so a request shaped
     * like this one can only come from a hand-crafted POST or a page cached from before the
     * fix. Both are exactly what the server-side check exists for. The markup half is
     * asserted in `DeleteSurfaceSymfonySmokeTest`.
     *
     * The token is deliberately **valid**. A refusal that only happened because the token
     * was wrong would prove nothing about the cancel check.
     */
    public function testASubmissionNamingCancelDeletesNothing(): void
    {
        $this->createFixtures();
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $form = $this->get(self::REAL_PATH, false, $jar);
        $this->assertSame(200, $form['status'], 'could not reach a confirmation page to mint a token from');

        //Exactly what a browser used to send on a Cancel click: the token, the cancel
        //button's own name and value, and no `submit`.
        $fields = $this->fieldsFromForm($form['body']);
        unset($fields['submit']);
        $fields['cancel'] = 'Cancel';

        $response = $this->request('POST', $this->fixturePath, [], false, $jar, $fields);

        $this->assertTrue(
            $this->exists($this->parentId),
            'a submission naming the cancel button deleted the record — the check in '
            . 'SionController::deleteAction() and App\Controller\EntityDeleteController is gone, '
            . 'and every delete confirmation on the site destroys data when Cancel is clicked'
        );
        $this->assertSame(
            302,
            $response['status'],
            'a cancellation must redirect the visitor away rather than render or error'
        );
    }

    /**
     * What the delete leaves behind: dangling references, on purpose for now.
     *
     * See the class docblock. This characterizes behaviour shared by all five delete
     * routes; it is not an endorsement of it. If a future change makes deletion refuse,
     * cascade or reparent, this test is where that decision becomes visible.
     */
    public function testDeletingAParentLeavesItsChildrenPointingAtNothing(): void
    {
        $this->createFixtures();
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $this->confirmDeletion($jar);

        $this->assertFalse($this->exists($this->parentId), 'precondition: the parent is gone');
        $this->assertTrue(
            $this->exists($this->childId),
            'the child was removed too — deletion has started cascading, which no foreign key '
            . 'and no line of code did on 2026-08-14. Update this test deliberately.'
        );
        $this->assertSame(
            $this->parentId,
            $this->parentOf($this->childId),
            'the child still names a parent that no longer exists; if this now reads null, '
            . 'something started nulling references out, and that is a behaviour change worth '
            . 'reviewing rather than absorbing'
        );
    }

    // -- the destructive request ---------------------------------------------

    /**
     * POST a genuine confirmation to the fixture's delete URL.
     *
     * The token is minted on the real association's page for the reason the class docblock
     * gives: the fixture's own confirmation cannot render while the entity cache has not
     * seen it, and the token is not bound to the record anyway.
     *
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    private function confirmDeletion(string $jar): array
    {
        $form = $this->get(self::REAL_PATH, false, $jar);
        $this->assertSame(200, $form['status'], 'could not reach a confirmation page to mint a token from');

        return $this->request('POST', $this->fixturePath, [], false, $jar, $this->fieldsFromForm($form['body']));
    }

    // -- fixtures ------------------------------------------------------------

    /**
     * Two throwaway associations, cloned column-for-column from a real one.
     *
     * Cloned rather than hand-built, and that is not laziness. A fixture assembled from
     * the NOT NULL columns alone (`AssociationName`, `Kind`) inserts happily and then
     * **500s the whole association index** the moment the entity cache rebuilds and
     * `processAssociationRow()` meets it. The failure surfaced two tests later, on a page
     * this file is not about, and read as a bug in the delete.
     *
     * Copying every column from a record the application already renders means the fixture
     * can only differ from real data in the three fields that must differ: its id, its
     * name, and who its parent is.
     */
    private function createFixtures(): void
    {
        $statement = $this->pdo()->prepare('SELECT * FROM sch_associations WHERE AssociationId = ?');
        $statement->execute([self::REAL_ID]);
        // FETCH_ASSOC explicitly: the default mode also returns every column under its
        // numeric offset, and those offsets become column names in the INSERT below.
        /** @var array<string, scalar|null>|false $template */
        $template = $statement->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($template, 'the association these fixtures are cloned from is missing from the dump');

        // `IsAuthor` is bit(1). PDO reads it as a one-byte binary string, and sending a
        // string back makes MariaDB read each *character* as a bit — so the one-character
        // "0" is eight bits and the insert fails with "Data too long for column
        // 'IsAuthor'". Converting to its ordinal and binding as PDO::PARAM_INT below is
        // what makes the round trip lossless; either half alone still fails.
        $bitColumns = $this->bitColumns();
        foreach ($bitColumns as $column) {
            if (isset($template[$column]) && is_string($template[$column])) {
                $template[$column] = ord($template[$column]);
            }
        }

        $fixtures = [
            [$this->parentId, 'Smoke test parent', null],
            [$this->childId, 'Smoke test child', $this->parentId],
        ];
        foreach ($fixtures as [$id, $name, $parent]) {
            $row                    = $template;
            $row['AssociationId']   = $id;
            $row['AssociationName'] = $name;
            $row['Parent']          = $parent;

            $columns   = array_keys($row);
            $statement = $this->pdo()->prepare(sprintf(
                'INSERT INTO sch_associations (`%s`) VALUES (%s)',
                implode('`, `', $columns),
                implode(', ', array_fill(0, count($columns), '?'))
            ));
            $position = 0;
            foreach ($row as $column => $value) {
                $position++;
                $statement->bindValue(
                    $position,
                    $value,
                    in_array($column, $bitColumns, true) ? PDO::PARAM_INT : PDO::PARAM_STR
                );
            }
            $statement->execute();
        }
    }

    /**
     * The `bit` columns of `sch_associations`, read rather than listed.
     *
     * @return list<string>
     */
    private function bitColumns(): array
    {
        $statement = $this->pdo()->query(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sch_associations' AND DATA_TYPE = 'bit'"
        );

        return false === $statement ? [] : array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function removeFixtures(): void
    {
        $this->pdo()
            ->prepare('DELETE FROM sch_associations WHERE AssociationId IN (?, ?)')
            ->execute([$this->parentId, $this->childId]);
    }

    /**
     * Inserting an explicit id above the counter raises it, and a raised counter outlives
     * the test — the next association created in the capsule would be numbered 99003.
     */
    private function rememberAutoIncrement(): void
    {
        $statement = $this->pdo()->query(
            'SELECT AUTO_INCREMENT FROM information_schema.TABLES '
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sch_associations'"
        );
        $value = false === $statement ? false : $statement->fetchColumn();
        if (false !== $value && null !== $value) {
            $this->autoIncrement['sch_associations'] = (int) $value;
        }
    }

    private function restoreAutoIncrement(): void
    {
        foreach ($this->autoIncrement as $table => $value) {
            // Not a prepared statement: ALTER TABLE takes no parameters. Both values are
            // an internal constant and an integer read back from information_schema.
            $this->pdo()->exec(sprintf('ALTER TABLE `%s` AUTO_INCREMENT = %d', $table, $value));
        }
    }

    private function exists(int $id): bool
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM sch_associations WHERE AssociationId = ?');
        $statement->execute([$id]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function parentOf(int $id): ?int
    {
        $statement = $this->pdo()->prepare('SELECT Parent FROM sch_associations WHERE AssociationId = ?');
        $statement->execute([$id]);
        $value = $statement->fetchColumn();

        return false === $value || null === $value ? null : (int) $value;
    }

    /**
     * Every field the confirmation form rendered, with its current value.
     *
     * Selected by its `action`, not by position: the delete view renders no `id`, and the
     * first form on the page is the navbar's search box. Taking `//form[1]` produced a
     * field set with no token in it and a confident-looking failure that had nothing to do
     * with CSRF.
     *
     * @return array<string, string>
     */
    private function fieldsFromForm(string $body): array
    {
        $document = new DOMDocument();
        @$document->loadHTML($body);
        $xpath = new DOMXPath($document);

        $form = $xpath->query('//form[contains(@action, "/delete")]')->item(0);
        $this->assertNotNull($form, 'the delete confirmation rendered no form pointing at a delete URL');

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
}

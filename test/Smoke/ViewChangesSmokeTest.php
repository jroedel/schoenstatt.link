<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use function array_unique;
use function array_values;
use function microtime;
use function preg_match_all;
use function sprintf;
use function strlen;
use function substr_count;

/**
 * /sm/view-changes renders, and stays bounded.
 *
 * Regression for a page that was **dead** until 2026-08-07 and nobody noticed, because
 * it sits behind `sch_general_moderator`:
 *
 *     Fatal error: Allowed memory size of 536870912 bytes exhausted
 *       in vendor/laminas/laminas-db/src/Adapter/Driver/Pdo/Result.php on line 175
 *
 * Two bugs, both in SionModel. `SionTable::getChanges()` asked for the changed entities
 * by their **database column** (`TextId`) where `queryObjects()` matches entity **field**
 * names (`textId`) — a mismatch it answers by silently dropping the predicate, so the
 * query came back unfiltered: all 2,757 rows of a table averaging 85 KB a row instead of
 * the 250 that had changed. And `ChangesCollector::getAllChanges()` took no limit at all,
 * so `changes_max_rows` bounded only the *display*.
 *
 * Both are fixed, and this is what stops them coming back. It is a smoke test rather
 * than an integration test for one reason: the failure was a fatal *during the response*,
 * which only an HTTP request can observe — the page returned 200 with a fatal-error
 * fragment for a body, so a status assertion alone would have passed throughout.
 *
 * **The route is now served by Symfony** (ported 2026-08-08). Fixing the page and porting
 * it were two separate changes in that order, and they had to be: a port is verified by
 * comparing its rendering against the laminas one, and there was no laminas rendering
 * while the page exhausted memory. Porting additionally needed
 * App\Laminas\EntityFormatter to stop refusing `publication` and `role`, which the
 * entity column meets constantly — sch_changes holds 18,243 and 1,317 of them.
 *
 * The three renderings were captured from laminas before the route moved and compared
 * byte for byte against the ported ones: the default all-tables view (500 rows, 13 date
 * groups), and — by pointing `changes_model` at PublicationsTable and then
 * SchoenstattTable — one view dominated by publications and one containing roles,
 * associations and persons. All three identical. See docs/laminas-exit.md.
 */
class ViewChangesSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /**
     * `changes_max_rows` in config/autoload/sionmodel.global.php. The page shows at most
     * this many, so the row count is the bound being asserted.
     */
    private const MAX_ROWS = 500;

    /** Measured 1.96s against the capsule; generous, because this asserts "not hanging". */
    private const BUDGET_MS = 15000;

    protected function emailPrefix(): string
    {
        return 'view-changes-smoke';
    }

    public function testTheChangesPageRendersWithoutExhaustingMemory(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_general_moderator']);

        $started  = microtime(true);
        $response = $this->request('GET', '/en/sm/view-changes', [], false, $jar);
        $elapsed  = (microtime(true) - $started) * 1000;

        self::assertSame(200, $response['status']);

        //the discriminator every ported route needs: laminas sends
        //`Set-Cookie: slm_locale=en_US` on every response and a ported route never does,
        //so without this the assertions below would pass against the laminas page too
        self::assertStringNotContainsString(
            'slm_locale=en_US',
            $response['headers']['set-cookie'] ?? '',
            'a slm_locale cookie means laminas-mvc served this, not the ported route'
        );

        //the discriminator that matters: the old failure *was* a 200, with a fatal-error
        //fragment where the page should be
        self::assertStringNotContainsString(
            'Allowed memory size',
            $response['body'],
            'the changes page is exhausting memory again — see SionTable::entityFieldForTableKey()'
        );
        self::assertStringNotContainsString('Fatal error', $response['body']);
        self::assertStringContainsString('Database edits', $response['body'], 'the page rendered no heading');

        self::assertLessThan(
            self::BUDGET_MS,
            $elapsed,
            sprintf('the changes page took %.0fms; it is unbounded again', $elapsed)
        );
    }

    /**
     * The limit is a real bound on what reaches the browser. Without this the page could
     * go back to loading every change row and still pass everything above — it was the
     * *fetch* that was unbounded, and 137,321 rows rendered would be a different failure
     * with the same cause.
     */
    public function testThePageShowsNoMoreRowsThanTheConfiguredLimit(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_general_moderator']);

        $body = $this->get('/en/sm/view-changes', false, $jar)['body'];

        //one <tr> per change, plus one per date-grouped <thead>; comfortably under twice
        //the limit and nowhere near the 137,321 rows the table holds
        $rows = substr_count($body, '<tr>');
        self::assertGreaterThan(0, $rows, 'no rows at all: is the changes table empty?');
        self::assertLessThanOrEqual(
            self::MAX_ROWS * 2,
            $rows,
            "the page rendered $rows rows; changes_max_rows is " . self::MAX_ROWS
        );
        self::assertLessThan(2_000_000, strlen($body), 'the response grew by an order of magnitude');
    }

    /** Anonymous: the sign-in redirect App\Authorization\Denial reproduces. */
    public function testAnAnonymousVisitorIsRedirectedToSignIn(): void
    {
        $response = $this->get('/en/sm/view-changes');

        self::assertSame(302, $response['status']);
        self::assertStringEndsWith('/en/user/login?redirect=/en/sm/view-changes', $response['redirect']);
        self::assertStringNotContainsString('Database edits', $response['body'], 'the refusal leaked the page');
    }

    /**
     * Signed in without the role: 403, not another redirect. The guard reads
     * `route/sion-model/view-changes`, which admits sch_general_moderator and
     * view_changes — sch_moderator is neither.
     */
    public function testASignedInVisitorWithoutTheRoleGetsForbidden(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $response = $this->get('/en/sm/view-changes', false, $jar);

        self::assertSame(403, $response['status']);
        self::assertStringNotContainsString('Database edits', $response['body']);
    }

    /** The unprefixed form redirects the way SlmLocale does, before the guard is reached. */
    public function testTheUnprefixedFormRedirectsToThePrefixedOne(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_general_moderator']);

        $response = $this->get('/sm/view-changes', false, $jar);

        self::assertSame(302, $response['status']);
        self::assertStringEndsWith('/en/sm/view-changes', $response['redirect']);
    }

    /**
     * The change log never publishes an editor's IP address (#308).
     *
     * `SionTable::reportChange()` recorded `$_SERVER['REMOTE_ADDR']` raw on every field
     * change, and this page rendered it as a tooltip on the editor's username. 165,092 of
     * the capsule's 165,127 change rows still carry one, so the assertion is not vacuous:
     * the column is full, and the question is only whether a response can reach it.
     *
     * Asserted over HTTP rather than against the template, because there were three
     * places to get this wrong — the INSERT, the read in processChangeRow(), and the
     * tooltip — and only a rendered page proves all three at once.
     */
    public function testTheChangeLogNeverShowsAnEditorsIpAddress(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_general_moderator']);

        $body = $this->get('/en/sm/view-changes', false, $jar)['body'];

        //floor first: a page that rendered nothing would pass the real assertion
        self::assertStringContainsString('Database edits', $body, 'the page rendered no heading');
        self::assertGreaterThan(100, substr_count($body, '<tr>'), 'too few rows to be a real change log');

        self::assertSame(
            [],
            self::ipAddressesIn($body),
            'the change log is publishing editor IP addresses again — see SionTable::reportChange()'
        );
    }

    /**
     * And the panel on an entity page, which is the wider exposure of the two.
     *
     * /sm/view-changes is behind `sch_general_moderator`. The same table is rendered
     * inline on association, publication, book and composition pages behind
     * `is_allowed('route/association-edit')` — and that resource is granted to
     * `sch_user`, which is `is_default = 1`. A default role means "signed in", so this
     * panel is what any authenticated account sees. The account below is granted no
     * roles at all for exactly that reason.
     *
     * Association 319 is used because it carries 367 IP-bearing change rows, the most of
     * any association in the capsule.
     */
    public function testTheEntityChangePanelShowsNoIpAddressToAnOrdinarySignedInVisitor(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, []);

        //followed: the numeric id 301s to the association's canonical text-id URL, and
        //what this asserts about is the page at the end of that, not the redirect
        $response = $this->get('/en/associations/319', true, $jar);

        self::assertSame(200, $response['status']);
        $body = $response['body'];

        //the floor that matters here: if the panel stopped rendering for a default role,
        //the IP assertion would pass while testing nothing
        self::assertStringContainsString(
            'id="changesPanel"',
            $body,
            'no change panel on the page: a default role can no longer see it, so this test '
            . 'is no longer exercising the exposure it was written for'
        );

        self::assertSame(
            [],
            self::ipAddressesIn($body),
            'an entity change panel is publishing editor IP addresses to every signed-in user'
        );
    }

    /**
     * Every dotted quad in a response body, deduplicated.
     *
     * Returned rather than counted so a failure names what leaked. Four dot-separated
     * numbers is not a shape this application's pages otherwise produce — asset versions
     * are three parts at most — and a false positive here is a page worth reading anyway.
     *
     * @return list<string>
     */
    private static function ipAddressesIn(string $body): array
    {
        preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $body, $matches);

        return array_values(array_unique($matches[0]));
    }

    protected function tearDown(): void
    {
        $this->purgeMail();
        $this->purgeAccounts();
        parent::tearDown();
    }
}

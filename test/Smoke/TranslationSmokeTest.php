<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use JTranslate\Model\PhraseIdentity;
use PDO;

use function bin2hex;
use function file_get_contents;
use function htmlspecialchars;
use function is_array;
use function preg_match;
use function random_bytes;
use function substr_count;

//This test uses JTranslate\Model\PhraseIdentity to compute a phrase hash. The smoke suite
//does not autoload by default, so it loads vendor/ itself rather than depending on a sibling
//test having done so first — which is what silently broke when the serving-note tests that
//used to require it were deleted (2026-09-08).
require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * JTranslate module: the translation administration area.
 *
 * Beyond the guard, what is worth an HTTP test here is the pair of screens that read
 * `trans_translations_history` — because both of them can regress into being *right but
 * useless*, and neither would fail anything.
 *
 * - The listing marks phrases whose translations have been replaced before. The marker
 *   has to cost one query for the page rather than one per row: this table renders
 *   every pending phrase of the project, and a per-row query would be a thousand of
 *   them for an icon.
 * - The edit screen shows the thread, which is the only place the replaced text is
 *   recoverable from and the only place the reason for a change survives.
 */
class TranslationSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    private const EMAIL_PREFIX = 'translation-smoke-';

    /** What `route/jtranslate` wants; see docs/acl-rules.md. */
    private const TRANSLATOR_ROLE = 'sch_general_moderator';

    /** @var list<int> history rows this test appended, removed again in teardown. */
    private array $appended = [];

    /** @var list<int> phrases this test created, removed again in teardown. */
    private array $created = [];

    /** The text of the phrase {@see createTemporaryPhrase()} last made, for posting it back. */
    private string $temporaryPhraseText = '';

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    protected function tearDown(): void
    {
        $this->removeAppendedHistory();
        $this->removeCreatedPhrases();
        $this->purgeMail();
        $this->purgeAccounts();

        parent::tearDown();
    }

    public function testTranslationAdminRequiresLogin(): void
    {
        $this->assertRequiresLogin('/en/admin/translations');
    }

    /**
     * A phrase with history is marked in the listing; one without is not.
     *
     * Both halves, because a marker rendered on every row passes a
     * "the marker is there" test just as happily and tells a translator nothing.
     */
    public function testTheListingMarksOnlyPhrasesThatHaveHistory(): void
    {
        $withHistory = $this->phraseWithHistory();

        $jar = $this->newCookieJar();
        $this->signIn($jar, [self::TRANSLATOR_ROLE]);
        $body = $this->get('/en/admin/translations?showAll=true', true, $jar)['body'];

        $this->assertStringContainsString('Manage Translations', $body, 'the listing did not render at all');
        $this->assertStringContainsString(
            'glyphicon-time',
            $this->markerContext($body, $withHistory),
            'the phrase with history carries no marker'
        );
        $this->assertLessThan(
            substr_count($body, 'glyphicon-pencil'),
            substr_count($body, 'glyphicon-time'),
            'every row is marked, so the marker says nothing'
        );
    }

    /** The thread itself, on the screen where somebody decides whether to overwrite. */
    public function testTheEditScreenShowsWhatWasReplacedAndWhy(): void
    {
        $marker  = 'Reemplazado ' . bin2hex(random_bytes(4));
        $reason  = 'sounded like the noun, not the imperative ' . bin2hex(random_bytes(3));
        $phrase  = $this->phraseWithHistory($marker, $reason);

        $jar = $this->newCookieJar();
        $this->signIn($jar, [self::TRANSLATOR_ROLE]);
        $body = $this->get('/en/admin/translations/' . $phrase . '/edit', true, $jar)['body'];

        $this->assertStringContainsString('Previous versions', $body, 'the thread panel is missing');
        $this->assertStringContainsString($marker, $body, 'the replaced text is not shown, so it is not recoverable');
        $this->assertStringContainsString($reason, $body, 'the reason is not shown, so the thread is a bare log');
    }

    /** A phrase nothing has overwritten shows no panel, rather than an empty one. */
    public function testAPhraseWithNoHistoryShowsNoPanel(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, [self::TRANSLATOR_ROLE]);
        $body = $this->get('/en/admin/translations/' . $this->phraseWithNoHistory() . '/edit', true, $jar)['body'];

        $this->assertStringContainsString('Edit Translation', $body, 'the edit form did not render at all');
        $this->assertStringNotContainsString('Previous versions', $body);
    }

    // ------------------------------------------------- the port, batch 14 (2026-09-08)

    /**
     * All three routes are served by Symfony rather than bridged back to laminas.
     *
     * The discriminator is a response header, not the markup: laminas sends
     * `Set-Cookie: slm_locale=en_US` on every response, because SlmLocale's cookie strategy
     * sets it at `MvcEvent::FINISH`. SlmLocale does not run for a ported route, so a locale
     * cookie coming back means the request went through `App\Http\LegacyBridge` — and since
     * `JTranslateController` no longer exists, what that would actually produce is a
     * dispatch failure rather than the old page.
     */
    public function testAllThreeRoutesAreServedBySymfonyAndNotBridged(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, [self::TRANSLATOR_ROLE]);
        $phrase = $this->phraseWithNoHistory();

        foreach ([
            '/en/admin/translations',
            '/en/admin/translations/' . $phrase . '/edit',
            '/en/admin/translations/' . $phrase . '/delete',
        ] as $path) {
            $response = $this->request('GET', $path, [], false, $jar);

            $this->assertSame(200, $response['status'], $path);
            $this->assertStringNotContainsString(
                'slm_locale=en_US',
                $response['headers']['set-cookie'] ?? '',
                $path . ': a slm_locale cookie means laminas-mvc served this'
            );
        }
    }

    /**
     * The worklist shows this project's phrases and no other project's.
     *
     * `trans_phrases` is shared between the applications using JTranslate, and a phrase in
     * it belongs to whichever project contributed it — free text a moderator wrote as a
     * per-locale description, in the case of this site. So this is a privacy property and
     * not a tidiness one, which is why it is asserted against a real foreign row rather
     * than against a count.
     *
     * Skipped rather than passed when the capsule holds no other project's phrases: a test
     * that cannot see the thing it is checking for must not report success.
     */
    public function testTheWorklistShowsOnlyThisProjectsPhrases(): void
    {
        //A phrase whose *text* this project does not also have. The two projects share UI
        //strings — 'Key (English)' is one, and it is a column header on this very page — so
        //a foreign row picked by id alone makes the text assertion below fail on a page that
        //is leaking nothing. Matched on `phrase_hash`, which is the identity the table keys
        //on rather than on the text itself.
        $foreign = $this->pdo()->query(
            "SELECT f.translation_phrase_id, f.phrase FROM trans_phrases f"
            . " WHERE f.project <> 'Schoenstatt' AND f.retired_on IS NULL"
            . " AND CHAR_LENGTH(f.phrase) BETWEEN 12 AND 80"
            . " AND NOT EXISTS ("
            . "   SELECT 1 FROM trans_phrases m"
            . "   WHERE m.project = 'Schoenstatt' AND m.phrase_hash = f.phrase_hash)"
            . " ORDER BY f.translation_phrase_id LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        if (! is_array($foreign)) {
            $this->markTestSkipped('the capsule holds no other project\'s phrases to check against');
        }

        $jar = $this->newCookieJar();
        $this->signIn($jar, [self::TRANSLATOR_ROLE]);
        $body = $this->get('/en/admin/translations?showAll=true', true, $jar)['body'];

        $this->assertStringNotContainsString(
            '/' . $foreign['translation_phrase_id'] . '/edit',
            $body,
            'the listing links to another project\'s phrase'
        );
        $this->assertStringNotContainsString(
            htmlspecialchars((string) $foreign['phrase'], ENT_QUOTES),
            $body,
            'the listing renders another project\'s phrase text'
        );
    }

    /**
     * An anonymous visitor is bounced to the sign-in page **with the filter intact**.
     *
     * This is the one behavioural difference the port introduced on purpose, and it is not
     * in the module: `App\Authorization\RouteGuard` puts the full request URI in `?redirect=`
     * where BjyAuthorize's strategy dropped the query string. Worth a test because the
     * mechanism that makes it useful is three classes away — `App\JUser\Host\RouteResolver`
     * has to resolve a path *with* a query string to a route name, or signing in lands on the
     * home page instead and nothing anywhere errors.
     */
    public function testTheSignInRedirectKeepsTheFilter(): void
    {
        $response = $this->get('/en/admin/translations?showAll=true');

        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString('/user/login', $response['redirect']);
        $this->assertStringContainsString(
            'showAll',
            $response['redirect'],
            'the filter is dropped from the redirect, so signing in loses the page asked for'
        );
    }

    /**
     * A translation typed into the form reaches the database **and** the compiled catalog.
     *
     * The two halves are the point. The site does not read translations from the database —
     * it reads the `.lang.php` catalogs — so a version of this action that wrote the row and
     * skipped the export reported success while the site went on showing the old text, which
     * is what it did until 2026-08. Asserting the flash alone would pass against that.
     *
     * Written against a phrase this test creates and deletes, rather than a real one: a real
     * phrase's translation is content, and a smoke test has no business overwriting it.
     */
    public function testSavingATranslationWritesTheRowAndRewritesTheCatalog(): void
    {
        $marker = 'Traducción de prueba ' . bin2hex(random_bytes(4));
        $phrase = $this->createTemporaryPhrase();

        $jar = $this->newCookieJar();
        $this->signIn($jar, [self::TRANSLATOR_ROLE]);

        $form = $this->get('/en/admin/translations/' . $phrase . '/edit', true, $jar);
        $this->assertSame(200, $form['status']);
        $this->assertMatchesRegularExpression(
            '/name="security"[^>]*value="([^"]+)"/',
            $form['body'],
            'the edit form carries no CSRF token'
        );
        preg_match('/name="security"[^>]*value="([^"]+)"/', $form['body'], $token);

        $posted = $this->request('POST', '/en/admin/translations/' . $phrase . '/edit', [], false, $jar, [
            'phraseId' => (string) $phrase,
            'phrase'   => $this->temporaryPhraseText,
            'es_ES'    => $marker,
            'security' => $token[1],
            'submit'   => 'Submit',
        ]);

        $this->assertSame(302, $posted['status'], 'a valid save must redirect to the listing');
        $this->assertStringContainsString('/admin/translations', $posted['redirect']);

        $stored = $this->pdo()->prepare(
            'SELECT translation FROM trans_translations WHERE translation_phrase_id = :id AND locale = :locale'
        );
        $stored->execute(['id' => $phrase, 'locale' => 'es_ES']);
        $this->assertSame($marker, $stored->fetchColumn(), 'the translation did not reach the database');

        $catalog = dirname(__DIR__, 2) . '/module/JTranslate/language/es_ES.lang.php';
        $this->assertFileExists($catalog, 'the export wrote no catalog for this text domain');
        $this->assertStringContainsString(
            $marker,
            (string) file_get_contents($catalog),
            'the row was written but the catalogs were not, so the site would keep showing the old text'
        );

        //And the message the translator is shown, on the page they land on.
        $landed = $this->get('/en/admin/translations', true, $jar)['body'];
        $this->assertStringContainsString('Translations successfully updated.', $landed);
    }

    /**
     * A POST naming Cancel destroys nothing, even with no valid token.
     *
     * Cancel used to delete the phrase — `DeletePhraseForm` rendered it as a submit button
     * and the action validated the CSRF token without looking at which button was pressed.
     * The button is a `Button` now and the controller checks for cancellation *before* the
     * token, so a stale token on a cancellation is not an error about a thing the visitor
     * asked not to do. Both halves are asserted here: no valid token is sent.
     */
    public function testAPostNamingCancelDestroysNothing(): void
    {
        $phrase = $this->createTemporaryPhrase();

        $jar = $this->newCookieJar();
        $this->signIn($jar, [self::TRANSLATOR_ROLE]);

        $posted = $this->request('POST', '/en/admin/translations/' . $phrase . '/delete', [], false, $jar, [
            'cancel'   => 'Cancel',
            'security' => 'not-a-token',
        ]);

        $this->assertSame(302, $posted['status']);
        $this->assertTrue($this->phraseExists($phrase), 'Cancel deleted the phrase');
    }

    /**
     * A refused CSRF token re-renders the confirmation with a **400**, and destroys nothing.
     *
     * The status code is the port's one deliberate correction: the laminas action answered
     * `401`, which is an authentication challenge — it tells a client to retry with
     * credentials and may make a browser prompt for them. The visitor here is signed in and
     * permitted; the request body is malformed.
     */
    public function testARefusedTokenAnswers400AndDestroysNothing(): void
    {
        $phrase = $this->createTemporaryPhrase();

        $jar = $this->newCookieJar();
        $this->signIn($jar, [self::TRANSLATOR_ROLE]);

        $posted = $this->request('POST', '/en/admin/translations/' . $phrase . '/delete', [], false, $jar, [
            'security' => 'not-a-token',
            'submit'   => 'Delete',
        ]);

        $this->assertSame(400, $posted['status']);
        $this->assertStringContainsString('Error in form submission', $posted['body']);
        $this->assertTrue($this->phraseExists($phrase), 'a refused token deleted the phrase');
    }

    /**
     * A phrase id that names nothing is a message and a redirect, on both write routes.
     *
     * The delete branch for it used to call a method of no class in that hierarchy, so a
     * stale link or a double submit was an uncaught `Error` rather than the sentence a
     * translator reads.
     */
    public function testAPhraseThatDoesNotExistRedirectsWithAMessage(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, [self::TRANSLATOR_ROLE]);

        $missing = 1 + (int) $this->pdo()->query('SELECT MAX(translation_phrase_id) FROM trans_phrases')->fetchColumn();

        foreach (['edit', 'delete'] as $verb) {
            $response = $this->get('/en/admin/translations/' . $missing . '/' . $verb, false, $jar);

            $this->assertSame(302, $response['status'], $verb);
            $this->assertStringContainsString('/admin/translations', $response['redirect'], $verb);
        }

        $landed = $this->get('/en/admin/translations', true, $jar)['body'];
        $this->assertStringContainsString('not found', $landed, 'no message explains the redirect');
    }

    // ---------------------------------------------------------------- fixtures

    /**
     * A phrase of this project carrying one history entry, appended for this test.
     *
     * Written straight to the table rather than through a PATCH: this file is about the
     * two screens, and PhrasesApiV3SmokeTest already covers the write that produces an
     * entry. The rows are removed in teardown — the table is append-only *for the
     * application*, which is a rule about what the code may do, not a reason to leave
     * fixtures in the capsule's dump.
     */
    private function phraseWithHistory(string $previous = 'Texto anterior', string $note = 'a reason'): int
    {
        $pdo = $this->pdo();
        $row = $pdo->query(
            "SELECT translation_phrase_id, project, phrase_hash, text_domain FROM trans_phrases"
            . " WHERE project = 'Schoenstatt' AND retired_on IS NULL ORDER BY translation_phrase_id LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row, 'this project has no phrases at all, so there is nothing to test with');

        $statement = $pdo->prepare(
            'INSERT INTO trans_translations_history (project, phrase_hash, locale, text_domain,'
            . ' translation_phrase_id, old_translation, operation, notes, replaced_on)'
            . ' VALUES (:project, :hash, :locale, :domain, :phrase, :previous, :operation, :note, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'project'   => $row['project'],
            'hash'      => $row['phrase_hash'],
            'locale'    => 'es_ES',
            'domain'    => $row['text_domain'],
            'phrase'    => $row['translation_phrase_id'],
            'previous'  => $previous,
            'operation' => 'update',
            'note'      => $note,
        ]);
        $this->appended[] = (int) $pdo->lastInsertId();

        return (int) $row['translation_phrase_id'];
    }

    /** A phrase of this project that no history row names. */
    private function phraseWithNoHistory(): int
    {
        $row = $this->pdo()->query(
            "SELECT p.translation_phrase_id FROM trans_phrases p"
            . " LEFT JOIN trans_translations_history h"
            . "   ON h.project = p.project AND h.phrase_hash = p.phrase_hash"
            . " WHERE p.project = 'Schoenstatt' AND p.retired_on IS NULL AND h.history_id IS NULL"
            . " ORDER BY p.translation_phrase_id DESC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row, 'every phrase of this project has history, which cannot be right');

        return (int) $row['translation_phrase_id'];
    }

    /**
     * A phrase of this project, created for one test and removed in teardown.
     *
     * Created rather than borrowed because the write tests **change** what they touch, and a
     * real phrase's translation is content — the site renders it. The row is written directly
     * for the same reason the history fixtures above are: the write path is what is under
     * test, so building the fixture through it would prove nothing.
     *
     * `phrase_hash` is computed with `JTranslate\Model\PhraseIdentity`, not by hand: the
     * column is the identity a merge, a retirement and the compiled catalog all key on, and a
     * row whose hash disagreed with its own text is a row nothing can look up.
     */
    private function createTemporaryPhrase(): int
    {
        $this->temporaryPhraseText = 'Smoke test phrase ' . bin2hex(random_bytes(6));

        $pdo = $this->pdo();
        $statement = $pdo->prepare(
            'INSERT INTO trans_phrases (project, text_domain, phrase, phrase_hash, added_on)'
            . ' VALUES (:project, :domain, :phrase, :hash, UTC_TIMESTAMP())'
        );
        $statement->bindValue('project', 'Schoenstatt');
        $statement->bindValue('domain', 'JTranslate');
        $statement->bindValue('phrase', $this->temporaryPhraseText);
        $statement->bindValue('hash', PhraseIdentity::raw($this->temporaryPhraseText), PDO::PARAM_LOB);
        $statement->execute();

        $id = (int) $pdo->lastInsertId();
        $this->created[] = $id;

        return $id;
    }

    private function phraseExists(int $id): bool
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM trans_phrases WHERE translation_phrase_id = :id'
        );
        $statement->execute(['id' => $id]);

        return 0 < (int) $statement->fetchColumn();
    }

    /**
     * Remove the phrases these tests created, and anything hanging off them.
     *
     * Translations and history rows go too, and in that order: `deletePhrase()` is not what
     * created them here, so nothing else would. The history table is append-only *for the
     * application* — a rule about what the code may do, not a reason to leave fixtures in the
     * capsule's dump.
     */
    private function removeCreatedPhrases(): void
    {
        foreach ($this->created as $id) {
            $pdo = $this->pdo();
            $pdo->prepare('DELETE FROM trans_translations WHERE translation_phrase_id = :id')
                ->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM trans_translations_history WHERE translation_phrase_id = :id')
                ->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM trans_phrases WHERE translation_phrase_id = :id')
                ->execute(['id' => $id]);
        }
        $this->created = [];
    }

    private function removeAppendedHistory(): void
    {
        foreach ($this->appended as $id) {
            $this->pdo()
                ->prepare('DELETE FROM trans_translations_history WHERE history_id = :id')
                ->execute(['id' => $id]);
        }
        $this->appended = [];
    }

    /**
     * The listing is one long table, and a regex over the whole of it would match a
     * marker belonging to a different row. This narrows to the cell for one phrase.
     */
    private function markerContext(string $body, int $phraseId): string
    {
        $pattern = '#<td>(?:(?!</td>).)*/' . $phraseId . '/edit(?:(?!</td>).)*</td>#s';

        return preg_match($pattern, $body, $matches) ? $matches[0] : '';
    }
}

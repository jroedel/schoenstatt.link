<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PDO;

use function bin2hex;
use function preg_match;
use function random_bytes;
use function substr_count;

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

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    protected function tearDown(): void
    {
        $this->removeAppendedHistory();
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

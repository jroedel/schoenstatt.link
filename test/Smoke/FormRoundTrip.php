<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PDO;

use function array_keys;
use function array_values;
use function implode;
use function sprintf;

/**
 * Post a real edit form back to itself and see what the database ends up holding.
 *
 * ## The gap this closes
 *
 * Five recordings describe what a form *is* — its markup, its elements' answers, its
 * engine verdicts, its rules, its URLs — and `FormWriteSurfaceTest` proves a form writes
 * its elements and nothing else. None of them submits anything. `test/Db/sql-surface.txt`
 * records the SQL the server receives, but its four drivers post no form either, so a
 * builder fault behind a moderator POST passes every recorded check and surfaces as an
 * empty 200 with nothing logged.
 *
 * So the one question none of it answers is the one that matters when a field changes:
 * **after a real save, is the row still right?** Not "does the form render", not "would the
 * engine accept this array" — does the round trip preserve what it must and change what it
 * should.
 *
 * ## Why the payload is scraped rather than written out
 *
 * `fields()` reads every input, textarea and select out of the rendered form and posts
 * exactly that. A hand-written payload tests the payload; a scraped one tests the form,
 * including the parts nobody looks at — `PersonForm` once round-tripped five fields
 * through hidden inputs purely because their precision columns were `NOT NULL`, and a
 * submission that omitted them crashed the save. That is invisible in a fixture and
 * unmissable in a scrape.
 *
 * It also means a removed field needs no change here: it simply stops being scraped, and
 * the assertions about what survives keep running.
 *
 * ## Restoring
 *
 * {@see remember()} snapshots named columns and {@see restore()} puts them back, so a
 * suite run leaves the row as it found it. The `sch_changes` rows a save files are left
 * behind deliberately — the change log is an audit trail, and rewriting it to keep a test
 * tidy is the wrong trade. The standing consequence, which predates this file: **do not
 * run the suite between two baseline captures.**
 *
 * The using class supplies the three things that differ per form, and nothing else. It must
 * also use {@see MagicLinkSignIn}, which is where `pdo()` lives and is the only way to get a
 * signed-in session here anyway — an edit form is not reachable anonymously.
 */
trait FormRoundTrip
{
    /** @var array<string, string|null>|null */
    private ?array $roundTripOriginal = null;

    /** The table the form writes, e.g. `sch_persons`. */
    abstract protected function table(): string;

    /** Its primary key column and the row under test, e.g. `['PersonId', 31]`. */
    abstract protected function row(): array;

    /** An XPath selecting the form element, e.g. `//form[@id="edit_person"]`. */
    abstract protected function formXPath(): string;

    /**
     * Every field the rendered form would submit, keyed by input name.
     *
     * A checkbox that is not `checked` is skipped rather than sent empty: the renderer
     * emits a hidden twin carrying the unchecked value immediately before it, and sending
     * both would overwrite that twin with `''`.
     *
     * @return array<string, string>
     */
    protected function fields(string $body): array
    {
        $document = new DOMDocument();
        @$document->loadHTML($body);
        $xpath = new DOMXPath($document);

        $form = $xpath->query($this->formXPath())->item(0);
        self::assertNotNull($form, sprintf('no form matched %s', $this->formXPath()));

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
            $selected      = $xpath->query('.//option[@selected]', $node)->item(0);
            $fields[$name] = $selected instanceof DOMElement ? $selected->getAttribute('value') : '';
        }

        self::assertNotEmpty($fields, 'the form rendered no fields at all');

        return $fields;
    }

    /**
     * GET the form, apply `$overrides`, POST it back, and return the response.
     *
     * Overrides are merged over the scraped payload, so a caller states only what it is
     * changing. A key that names no rendered field is a caller error rather than a silent
     * addition — a form does not write what it does not render, and a test that believes
     * otherwise is testing a payload it invented.
     *
     * @param array<string, string> $overrides
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    protected function submitForm(string $path, string $jar, array $overrides = []): array
    {
        $form = $this->get($path, false, $jar);
        self::assertSame(200, $form['status'], sprintf('could not load %s to submit it', $path));

        $fields = $this->fields($form['body']);
        foreach (array_keys($overrides) as $name) {
            self::assertArrayHasKey(
                $name,
                $fields,
                sprintf("override '%s' names no field the form renders", $name)
            );
        }

        return $this->request('POST', $path, [], false, $jar, $overrides + $fields);
    }

    /** @param list<string> $columns */
    protected function remember(array $columns): void
    {
        [$key, $id] = $this->row();
        $statement  = $this->pdo()->prepare(sprintf(
            'SELECT `%s` FROM `%s` WHERE `%s` = ?',
            implode('`, `', $columns),
            $this->table(),
            $key
        ));
        $statement->execute([$id]);
        /** @var array<string, string|null>|false $snapshot */
        $snapshot                = $statement->fetch(PDO::FETCH_ASSOC);
        $this->roundTripOriginal = false === $snapshot ? null : $snapshot;
    }

    protected function restore(): void
    {
        if (null === $this->roundTripOriginal) {
            return;
        }

        [$key, $id]  = $this->row();
        $assignments = [];
        foreach (array_keys($this->roundTripOriginal) as $column) {
            $assignments[] = sprintf('`%s` = ?', $column);
        }
        $this->pdo()
            ->prepare(sprintf(
                'UPDATE `%s` SET %s WHERE `%s` = ?',
                $this->table(),
                implode(', ', $assignments),
                $key
            ))
            ->execute([...array_values($this->roundTripOriginal), $id]);

        $this->roundTripOriginal = null;
    }

    protected function column(string $column): ?string
    {
        [$key, $id] = $this->row();
        $statement  = $this->pdo()->prepare(sprintf(
            'SELECT `%s` FROM `%s` WHERE `%s` = ?',
            $column,
            $this->table(),
            $key
        ));
        $statement->execute([$id]);
        $value = $statement->fetchColumn();

        return false === $value || null === $value ? null : (string) $value;
    }
}

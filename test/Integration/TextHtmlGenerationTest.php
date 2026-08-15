<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Books\Model\EventTextTable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

use function str_contains;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * When `EventTextTable::preprocessText()` renders markdown to HTML, and when it must not.
 *
 * ## The two bugs one line was carrying
 *
 * The condition used to read `self::TEXT_KIND_JK_TEXT !== $entityData['kind']`, and the array
 * read was unguarded. Creating a text passes `$entityData = []`, so PHP emitted
 * `Undefined array key "kind"` — and a create emits that *before* its redirect is sent, so the
 * moderator got a 154-byte blank page over a text that had in fact been written. Pressing
 * submit again made a second one.
 *
 * Guarding the read would have fixed the blank page and left the worse half in place. Every
 * text is a jk-text now that the blog is gone, so that clause also meant **editing a text's
 * markdown never regenerated its HTML** — and the show page renders `htmlText` and nothing
 * else, so the form's main field had no visible effect at all.
 *
 * ## Why `legacyFile` and not `kind`
 *
 * The clause exists for `importJkTexts()`, which reads paired `.md` and `.html` files: the
 * imported HTML is a richer document than Parsedown's rendering of the same markdown, and
 * overwriting it would be a loss. Measured across the corpus before changing anything —
 * regenerating would alter the visible text of **2,740 of the 2,756** rows that have markdown,
 * several to twice the length. So "just drop the clause" was the wrong answer.
 *
 * But `! isset($data['htmlText'])` already protects the importer, which passes both files.
 * What the kind clause added was protection for the *edit* form, where it did harm. The data
 * separates the two cases exactly: all 2,753 imported rows carry a `LegacyFile` and all 4 rows
 * authored in the application carry none.
 *
 * ## Why it needs no database, and why it is here anyway
 *
 * `preprocessText()` touches `$data`, `$entityData`, Parsedown and Html2Text, and nothing
 * else — no database, no container, no request — so the method can be called directly on an
 * instance built without its constructor, and each of the four cases below is one call. It
 * sits in the integration suite regardless, because `EventTextTable` extends `SionTable` and
 * loading it means loading laminas-db: the unit suite deliberately has no autoloader, so that
 * its tests stay runnable while `vendor/` is mid-migration, and a test that must `require`
 * half the framework does not belong to it.
 *
 * The integration guard `PreprocessorCreateSafetyTest` covers the *shape* of the bug across
 * every preprocessor; this file covers what this one now decides.
 */
final class TextHtmlGenerationTest extends TestCase
{
    private const MARKDOWN = "# A heading\n\nSome *text*.";

    /** A create: `SionTable::createEntity()` passes an empty array as the stored row. */
    public function testACreateRendersTheMarkdown(): void
    {
        $data = $this->preprocess(['title' => 'New text', 'markdownText' => self::MARKDOWN], []);

        self::assertArrayHasKey('htmlText', $data, 'a text created through the form would render as a blank page');
        self::assertTrue(str_contains((string) $data['htmlText'], '<h1>A heading</h1>'));
        self::assertTrue(str_contains((string) $data['plainText'], 'A HEADING'));
    }

    /** An edit of a text written in the application: the moderator's change must become visible. */
    public function testEditingAnApplicationAuthoredTextRegeneratesItsHtml(): void
    {
        $data = $this->preprocess(
            ['title' => 'A text', 'markdownText' => self::MARKDOWN],
            ['kind' => 'jk-text', 'legacyFile' => null, 'htmlText' => '<p>stale</p>']
        );

        self::assertArrayHasKey('htmlText', $data);
        self::assertTrue(str_contains((string) $data['htmlText'], '<h1>A heading</h1>'));
    }

    /**
     * An edit of an imported text: its HTML came from a file and must survive.
     *
     * This is the case the removed `kind` clause was really protecting, and the one that makes
     * the change behaviour-preserving for every row in the database.
     */
    public function testEditingAnImportedTextLeavesItsHtmlAlone(): void
    {
        $data = $this->preprocess(
            ['title' => 'An imported text', 'markdownText' => self::MARKDOWN],
            ['kind' => 'jk-text', 'legacyFile' => '1965-05-31.md', 'htmlText' => '<p>imported</p>']
        );

        self::assertArrayNotHasKey('htmlText', $data, 'the import would be overwritten by a re-render');
        self::assertArrayNotHasKey('plainText', $data);
    }

    /** The importer itself, which supplies both files and must not be second-guessed. */
    public function testTheImporterKeepsTheHtmlItSupplies(): void
    {
        $data = $this->preprocess(
            ['title' => 'An import', 'markdownText' => self::MARKDOWN, 'htmlText' => '<p>from the file</p>'],
            []
        );

        self::assertSame('<p>from the file</p>', $data['htmlText']);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $entityData
     * @return array<string, mixed>
     */
    private function preprocess(array $data, array $entityData): array
    {
        //No constructor: it wants a database adapter and a service locator, and the method
        //under test reaches for neither.
        $table = (new ReflectionClass(EventTextTable::class))->newInstanceWithoutConstructor();

        //No setAccessible(): it has had no effect since PHP 8.1 and is deprecated on the
        //8.5 this runs on.
        /** @var array<string, mixed> */
        return (new ReflectionMethod($table, 'preprocessText'))->invoke($table, $data, $entityData, 'create');
    }
}

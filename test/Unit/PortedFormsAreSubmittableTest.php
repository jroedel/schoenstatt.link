<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function implode;
use function sprintf;
use function str_contains;
use function is_readable;
use function preg_match_all;
use function preg_replace;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Every ported template that opens a form also renders a way to submit it.
 *
 * ## The defect this exists for
 *
 * `templates/books/book-edit.html.twig` shipped in batch 7 with no submit control at all,
 * and stayed that way from 2026-08-13 until 2026-08-15. The reason is a boundary that only
 * that one entity has: nine of batch 7's ten laminas templates render the submit as a row
 * *inside* `fields-partial.phtml`, so it came across with the field list; `books/edit.phtml`
 * renders it **outside** the partial, between the partial and the closing tag. The port
 * reproduced the partial and stopped, and the result was a page a moderator could open,
 * fill in and not save — the only `type="submit"` in the response being the navbar's search
 * button.
 *
 * ## Why a source scan and not an HTTP assertion
 *
 * Because the response was a perfectly healthy **HTTP 200**, 589 KB of correct markup with
 * one control missing. No status assertion can see that, and the smoke test covering the
 * route asserted the anonymous 302 — the same blind spot that let the
 * `collections/collection/edit` fatal-200 ship in the same batch. An HTTP test that *could*
 * see it needs a signed-in account holding a per-library `administrate` grant, i.e. the
 * Mailpit magic-link dance `tools/port-baseline.php` does; that is worth it for a rendering
 * diff and disproportionate for "is there a button".
 *
 * `tools/port-baseline.php` did have `/books/18370/edit` in `PATHS` throughout, and nothing
 * in its `normalize()` erases a submit button — so the difference *was* in the diff. What it
 * was not is alone in it: that page carries batch 7's one accepted regression (the navbar
 * search box points at contacts rather than the library), so its diff was already expected
 * to be non-empty. This test is the check that does not depend on a reviewer's attention.
 *
 * ## What counts as submittable
 *
 * Deliberately broad, because the ported templates legitimately do it three ways and all
 * three are correct:
 *
 * - `form_submit(form.get('submit'))` — `library-edit`, `composition-edit`,
 *   `entity-delete`, `assignments-advanced-search`, and now `book-edit`.
 * - `form_row(form.get('submit'))` — `collection-edit`, `dictionary-entry-edit`,
 *   `_comment-create`, i.e. wherever the laminas partial had the submit as a row.
 * - A literal `<button type="submit">` — the four search bars, which are hand-written
 *   Bootstrap input-groups rather than rendered forms.
 *
 * The property is "this form can be submitted", not "this form uses helper X". A test that
 * insisted on one spelling would fail on markup that is fine and teach people to silence it.
 */
final class PortedFormsAreSubmittableTest extends TestCase
{
    public function testEveryTemplateThatOpensAFormCanSubmitIt(): void
    {
        $offenders = [];

        foreach (self::templates() as $file) {
            $source = file_get_contents($file);
            if (false === $source) {
                continue;
            }

            $markup = self::withoutComments($source);

            if (! str_contains($markup, 'form_open')) {
                continue;
            }

            if (self::isSubmittable($markup) || self::anIncludedTemplateSubmits($markup)) {
                continue;
            }

            $offenders[] = self::relative($file);
        }

        $this->assertSame([], $offenders, sprintf(
            "These templates open a form with no way to submit it:\n%s\n\n"
            . 'The page will render as a healthy 200 that cannot be saved. Check the laminas'
            . ' template this one ports: if it calls formSubmit() *outside* its'
            . ' fields-partial.phtml, the submit is easy to miss, which is exactly how'
            . ' books/book-edit.html.twig shipped without one.',
            implode("\n", $offenders)
        ));
    }

    /**
     * True when a template this one includes renders the submit.
     *
     * Needed from 2026-08-15, when the field rows moved into `_<entity>-fields.html.twig`
     * partials shared with the create forms: three of those partials carry their own submit
     * row, because the laminas partial they reproduce does. Reading one file at a time, this
     * test flagged all three — right about the file, wrong about the page, which is the
     * failure mode worth avoiding in a guard nobody will trust after the second false alarm.
     *
     * **One level deep, deliberately.** Every form in this project is a page template plus at
     * most one field partial, so a recursive walk would be machinery for a case that does not
     * exist and a cycle to guard against for no reason. If a second level ever appears, this
     * failing is the correct outcome: it means the form layout grew a shape nobody has
     * looked at.
     */
    private static function anIncludedTemplateSubmits(string $markup): bool
    {
        preg_match_all("/include\(\s*'([^']+)'/", $markup, $matches);

        foreach ($matches[1] as $name) {
            $path = dirname(__DIR__, 2) . '/templates/' . $name;

            $source = is_readable($path) ? file_get_contents($path) : false;

            if (false !== $source && self::isSubmittable(self::withoutComments($source))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The template with its `{# … #}` comments removed.
     *
     * **Not optional, and it took a failed self-check to notice.** Every template in this
     * project opens with a docblock explaining what it ports, and the docblock on
     * `book-edit.html.twig` now explains the missing submit button — naming `form_submit`
     * and `type="submit"` in prose. So the first version of this test passed with the fix
     * deleted again: it was matching its own explanation. `NoStaticDbAdapterTest` records
     * the same hazard from the other direction and answers it by tokenizing; a Twig
     * template has no tokenizer to hand here, and stripping the comment blocks is the
     * whole of the difference.
     */
    private static function withoutComments(string $source): string
    {
        return (string) preg_replace('/\{#.*?#\}/s', '', $source);
    }

    /**
     * A form is submittable if it renders the `submit` element by any of the three routes,
     * or carries a literal submit button.
     *
     * `str_contains` rather than a parse: a Twig template is not PHP, the three spellings
     * are one line each, and the failure this guards against is an *absent* line rather
     * than a subtly wrong one.
     */
    private static function isSubmittable(string $source): bool
    {
        foreach (["form_submit(", "form_row(form.get('submit')", 'type="submit"'] as $needle) {
            if (str_contains($source, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every Twig template, including the partials.
     *
     * Partials are included rather than skipped because three of them — the search bars —
     * are where a form actually lives; `templates/books/_search-bar.html.twig` opens and
     * closes its own form and no page-level template does it for them. A partial that opens
     * a form and leaves the submit to its includer would be a false positive here, and none
     * of the seventeen does that today; should one arrive, the honest fix is to give this
     * test the pair rather than to loosen it.
     *
     * @return iterable<string>
     */
    private static function templates(): iterable
    {
        $root = dirname(__DIR__, 2) . '/templates';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $path = $file->getPathname();

            if (str_ends_with($path, '.html.twig')) {
                yield $path;
            }
        }
    }

    private static function relative(string $path): string
    {
        $root = dirname(__DIR__, 2) . '/';

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}

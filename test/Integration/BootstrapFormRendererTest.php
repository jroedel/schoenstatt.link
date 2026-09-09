<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SionModel\Form\BootstrapFormRenderer;

use function array_keys;
use function in_array;
use function sort;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * `SionModel\Form\BootstrapFormRenderer` filters an input's attributes by input type, the way
 * `Laminas\Form\View\Helper\AbstractHelper::createAttributesString()` did — and it does so
 * from lists **transcribed by hand** out of the laminas helpers. This is the test that the
 * transcription is right.
 *
 * ## Why this is worth a test rather than a careful read
 *
 * The failure mode is a handful of bytes. Batch 6 found the renderer emitting `min="3"` on
 * a text input because `min` belongs to number, range and date inputs and laminas drops it
 * — invisible in a browser, and only caught by a byte-for-byte baseline diff of a signed-in
 * page in five locales. Batch 7 added four more types, and a wrong list would be four more
 * of those, each costing a capture cycle to find.
 *
 * ## This was a reflection harness and is now a golden master
 *
 * Each list used to be read out of the laminas helper's own `$validTagAttributes` by
 * reflection, so that a change in laminas-form would fail here and name itself. That is no
 * longer possible: every `Laminas\Form\View\Helper\*` class extends
 * `Laminas\I18n\View\Helper\AbstractTranslatorHelper`, so all six helpers went unloadable
 * when laminas-i18n was removed in 2026-09, permanently. The arrays in {@see
 * filteredTypes()} are what those helpers' `$validTagAttributes` held, captured from the
 * running code before it went (laminas-form 3.x / laminas-i18n 2.33.0); the renderer must
 * keep matching them. They are the authority now — nothing upstream will ever move them
 * again, which is the point of dropping the dependency.
 *
 * The probe on our own side is unchanged, so the *renderer* is still read live: a list
 * edited in `filteredByInputType()` still fails here.
 *
 * ## Why this is an integration test and not a unit one
 *
 * It needs `vendor/`: `BootstrapFormRenderer` is autoloaded through composer's PSR-4 map,
 * and the unit suite's contract is the opposite — CLAUDE.md: "require the class under test
 * directly — no vendor autoload, no running app — so they … stay valid while `vendor/` is
 * mid-migration". It needs no database and no container either, so it runs on a bare CI
 * runner.
 *
 * ## What this does not cover
 *
 * The renderer's *markup* — TwbBundle's row classes, the escape-only-if-no-tags help
 * block, the button class rules — is not asserted here. `SelectRenderingParityTest` freezes
 * it for `<select>`; for the rest it is checked where it can be compared against the
 * original: the baseline captures in `tools/port-baseline.php`. This file covers the one
 * part that is a pure data transcription and therefore checkable in isolation.
 */
class BootstrapFormRendererTest extends TestCase
{
    /**
     * The input types the renderer filters, and the frozen `$validTagAttributes` of the
     * laminas helper each was transcribed from.
     *
     * `hidden` is deliberately absent: the renderer uses text's list for it, and the class
     * says why — `FormHidden`'s own list is `FormInput`'s, which is far wider, and `value`
     * and `name` are the whole of it in practice. Asserting it against `FormInput` would
     * pin a decision the renderer knowingly does not make.
     *
     * @return array<string, array{string, list<string>}>
     */
    public static function filteredTypes(): array
    {
        return [
            //Laminas\Form\View\Helper\FormText
            'text'   => ['text', [
                'autocomplete',
                'autofocus',
                'dirname',
                'disabled',
                'form',
                'inputmode',
                'list',
                'maxlength',
                'minlength',
                'name',
                'pattern',
                'placeholder',
                'readonly',
                'required',
                'size',
                'type',
                'value',
            ]],
            //Laminas\Form\View\Helper\FormNumber
            'number' => ['number', [
                'autocomplete',
                'autofocus',
                'disabled',
                'form',
                'list',
                'max',
                'min',
                'name',
                'placeholder',
                'readonly',
                'required',
                'step',
                'type',
                'value',
            ]],
            //Laminas\Form\View\Helper\FormEmail: text's list plus `multiple`, minus
            //`dirname` and `inputmode`.
            'email'  => ['email', [
                'autocomplete',
                'autofocus',
                'disabled',
                'form',
                'list',
                'maxlength',
                'minlength',
                'multiple',
                'name',
                'pattern',
                'placeholder',
                'readonly',
                'required',
                'size',
                'type',
                'value',
            ]],
            //Laminas\Form\View\Helper\FormUrl: FormEmail's without `multiple`.
            'url'    => ['url', [
                'autocomplete',
                'autofocus',
                'disabled',
                'form',
                'list',
                'maxlength',
                'minlength',
                'name',
                'pattern',
                'placeholder',
                'readonly',
                'required',
                'size',
                'type',
                'value',
            ]],
            //Laminas\Form\View\Helper\FormDate declared none of its own and inherited
            //AbstractFormDateTime's — hence no `placeholder`, see below.
            'date'   => ['date', [
                'autocomplete',
                'autofocus',
                'disabled',
                'form',
                'list',
                'max',
                'min',
                'name',
                'readonly',
                'required',
                'step',
                'type',
                'value',
            ]],
            //Laminas\Form\View\Helper\FormFile: the import upload, and the shortest list of
            //the six — no `value`, because a file input's value cannot be set from markup.
            'file'   => ['file', [
                'accept',
                'autofocus',
                'disabled',
                'form',
                'multiple',
                'name',
                'required',
                'type',
            ]],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('filteredTypes')]
    public function testTheTranscribedListMatchesTheLaminasHelper(string $type, array $expected): void
    {
        $actual = self::rendererListFor($type);

        sort($expected);
        sort($actual);

        $this->assertSame(
            $expected,
            $actual,
            "SionModel\\Form\\BootstrapFormRenderer's '$type' attribute list has drifted from the "
            . 'laminas helper it was transcribed from. A missing entry silently drops an attribute '
            . 'laminas rendered; an extra one renders an attribute laminas dropped. Either is a '
            . 'byte-level difference no browser would show you.'
        );
    }

    /**
     * The distinction that motivated the whole table: `number` is not `text` with extras.
     *
     * It gains `max`/`min`/`step` and *loses* `maxlength`, `minlength`, `pattern`, `size`,
     * `dirname` and `inputmode`. Asserted explicitly because "transcribe the helper's list"
     * is easy to satisfy by copying text's and adding three names, which would pass nothing
     * here and would keep `maxlength` on every number field in the batch.
     */
    public function testNumberIsNotTextWithExtras(): void
    {
        $number = self::rendererListFor('number');
        $text   = self::rendererListFor('text');

        foreach (['max', 'min', 'step'] as $gained) {
            $this->assertContains($gained, $number, "a number input takes '$gained'");
            $this->assertNotContains($gained, $text, "a text input does not take '$gained'");
        }

        foreach (['maxlength', 'minlength', 'pattern', 'size'] as $lost) {
            $this->assertContains($lost, $text, "a text input takes '$lost'");
            $this->assertNotContains($lost, $number, "a number input does not take '$lost'");
        }
    }

    /**
     * `placeholder` is the one that catches a lazy `date` transcription: `PersonForm`
     * declares it on a Date element and `AbstractFormDateTime` had no such attribute, so
     * laminas dropped it.
     */
    public function testADateInputTakesNoPlaceholder(): void
    {
        $this->assertNotContains('placeholder', self::rendererListFor('date'));
        $this->assertContains('placeholder', self::rendererListFor('text'));
    }

    // ---------------------------------------------------------------------------- helpers

    /**
     * The renderer's list for one type, read out of `filteredByInputType()`.
     *
     * The method is private and static, and it returns the *filtered attributes* rather
     * than the list — so it is probed by handing it every candidate attribute and seeing
     * which survive. That keeps the test honest about what the renderer actually does with
     * the list rather than about how it stores it, and it works whatever shape the internals
     * take next.
     *
     * @return list<string>
     */
    private static function rendererListFor(string $type): array
    {
        $candidates = [];
        foreach (self::everyAttributeNamedAnywhere() as $name) {
            $candidates[$name] = 'x';
        }

        $method = (new ReflectionClass(BootstrapFormRenderer::class))
            ->getMethod('filteredByInputType');

        /** @var array<string, scalar> $survivors */
        $survivors = $method->invoke(null, $candidates, $type);

        //The presentational globals the renderer allows for every type are not part of any
        //helper's $validTagAttributes, so they are excluded from the comparison.
        $globals = ['accesskey', 'class', 'contenteditable', 'dir', 'draggable', 'hidden',
                    'id', 'lang', 'spellcheck', 'style', 'tabindex', 'title'];

        $list = [];
        foreach (array_keys($survivors) as $name) {
            if (! in_array($name, $globals, true)) {
                $list[] = (string) $name;
            }
        }

        return $list;
    }

    /**
     * Every attribute name any of the frozen lists holds, which is the candidate set the
     * probe needs.
     *
     * @return list<string>
     */
    private static function everyAttributeNamedAnywhere(): array
    {
        $names = [];
        foreach (self::filteredTypes() as [$_type, $allowed]) {
            foreach ($allowed as $name) {
                $names[$name] = true;
            }
        }
        //plus the ones a form in this application declares that no helper allowed, so the
        //probe can see them being dropped
        foreach (['dirname', 'inputmode', 'multiple', 'maxlength', 'placeholder'] as $extra) {
            $names[$extra] = true;
        }

        return array_keys($names);
    }
}

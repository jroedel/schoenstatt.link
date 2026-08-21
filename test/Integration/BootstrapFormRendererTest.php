<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\Form\View\Helper\FormDate;
use Laminas\Form\View\Helper\FormEmail;
use Laminas\Form\View\Helper\FormFile;
use Laminas\Form\View\Helper\FormNumber;
use Laminas\Form\View\Helper\FormText;
use Laminas\Form\View\Helper\FormUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_keys;
use function is_array;
use function sort;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * `SionModel\Form\BootstrapFormRenderer` filters an input's attributes by input type, the way
 * `Laminas\Form\View\Helper\AbstractHelper::createAttributesString()` does — and it does
 * so from lists **transcribed by hand** out of the laminas helpers. This is the test that
 * the transcription is right.
 *
 * ## Why this is worth a test rather than a careful read
 *
 * The failure mode is a handful of bytes. Batch 6 found the renderer emitting `min="3"` on
 * a text input because `min` belongs to number, range and date inputs and laminas drops it
 * — invisible in a browser, and only caught by a byte-for-byte baseline diff of a signed-in
 * page in five locales. Batch 7 added four more types, and a wrong list would be four more
 * of those, each costing a capture cycle to find.
 *
 * So the lists are compared against the helpers' own `$validTagAttributes` by reflection.
 * When laminas-form changes one, this fails and names it, which is strictly better than
 * discovering it in a rendering diff.
 *
 * ## Why this is an integration test and not a unit one
 *
 * It reflects into `laminas-form`'s helpers, so it needs `vendor/`. The unit suite's
 * contract is the opposite — CLAUDE.md: "require the class under test directly — no vendor
 * autoload, no running app — so they … stay valid while `vendor/` is mid-migration" — and a
 * test that reads a vendor class's private property cannot honour that. It needs no
 * database and no container either, so it runs on a bare CI runner.
 *
 * ## What this does not cover
 *
 * The renderer's *markup* — TwbBundle's row classes, the escape-only-if-no-tags help
 * block, the button class rules — is not asserted here. That needs a real form and a real
 * escaper, and it is checked where it can be compared against the original: the baseline
 * captures in `tools/port-baseline.php`. This file covers the one part that is a pure data
 * transcription and therefore checkable in isolation.
 */
class BootstrapFormRendererTest extends TestCase
{
    /**
     * The input types the renderer filters, and the laminas helper each was transcribed
     * from.
     *
     * `hidden` is deliberately absent: the renderer uses text's list for it, and the class
     * says why — `FormHidden`'s own list is `FormInput`'s, which is far wider, and `value`
     * and `name` are the whole of it in practice. Asserting it against `FormInput` would
     * pin a decision the renderer knowingly does not make.
     *
     * @return array<string, array{string, class-string}>
     */
    public static function filteredTypes(): array
    {
        return [
            'text'   => ['text', FormText::class],
            'number' => ['number', FormNumber::class],
            'email'  => ['email', FormEmail::class],
            'url'    => ['url', FormUrl::class],
            //FormDate declares none of its own and inherits AbstractFormDateTime's.
            'date'   => ['date', FormDate::class],
            //The import upload, and the shortest list of the six: no `value`, because a
            //file input's value cannot be set from markup.
            'file'   => ['file', FormFile::class],
        ];
    }

    /**
     * @param class-string $helper
     */
    #[DataProvider('filteredTypes')]
    public function testTheTranscribedListMatchesTheLaminasHelper(string $type, string $helper): void
    {
        $expected = self::validTagAttributesOf($helper);
        $actual   = self::rendererListFor($type);

        sort($expected);
        sort($actual);

        $this->assertSame(
            $expected,
            $actual,
            "SionModel\\Form\\BootstrapFormRenderer's '$type' attribute list has drifted from "
            . "$helper::\$validTagAttributes. A missing entry silently drops an attribute laminas "
            . 'renders; an extra one renders an attribute laminas drops. Either is a byte-level '
            . 'difference no browser would show you.'
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
     * declares it on a Date element and `AbstractFormDateTime` has no such attribute, so
     * laminas drops it.
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

        $method = (new ReflectionClass(\SionModel\Form\BootstrapFormRenderer::class))
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
     * Every attribute name any of the helpers under test declares, which is the candidate
     * set the probe needs.
     *
     * @return list<string>
     */
    private static function everyAttributeNamedAnywhere(): array
    {
        $names = [];
        foreach (self::filteredTypes() as [$_type, $helper]) {
            foreach (self::validTagAttributesOf($helper) as $name) {
                $names[$name] = true;
            }
        }
        //plus the ones a form in this application declares that no helper allows, so the
        //probe can see them being dropped
        foreach (['dirname', 'inputmode', 'multiple', 'maxlength', 'placeholder'] as $extra) {
            $names[$extra] = true;
        }

        return array_keys($names);
    }

    /**
     * A helper's `$validTagAttributes`, including whatever it inherits.
     *
     * @param class-string $helper
     * @return list<string>
     */
    private static function validTagAttributesOf(string $helper): array
    {
        $property = (new ReflectionClass($helper))->getProperty('validTagAttributes');
        /** @var mixed $value */
        $value = $property->getValue(new $helper());

        $names = [];
        if (is_array($value)) {
            foreach ($value as $name => $allowed) {
                if (true === $allowed) {
                    $names[] = (string) $name;
                }
            }
        }

        return $names;
    }
}

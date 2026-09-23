<?php

declare(strict_types=1);

namespace SchoenstattTest\Element;

use App\Locale\Locales;
use Locale;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Form\Collection;
use SionModel\Form\Element\Checkbox;
use SionModel\Form\Element\Csrf;
use SionModel\Form\Element\DateSelect;
use SionModel\Form\Element\Select;
use SionModel\Form\ElementInterface;
use SionModel\Form\Fieldset;

use function array_keys;
use function array_slice;
use function count;
use function date;
use function get_debug_type;
use function is_array;
use function is_scalar;
use function is_string;
use function ksort;
use function md5;
use function preg_replace;
use function serialize;
use function str_replace;
use function sprintf;

require_once __DIR__ . '/../Fuzz/FormRepository.php';

/**
 * Every question the application asks an element, and the answer it gets today.
 *
 * ## Why this exists before the element model does
 *
 * Step 6 replaces `Laminas\Form\Element\*` with our own, and the thing that makes that
 * safe is knowing exactly what the replacement has to answer. `BootstrapFormRenderer`
 * asks twelve questions; the form factories ask three more; four specification helpers
 * ask about types and attributes; two templates reach for a `DateSelect`'s day and month
 * sub-elements. Nothing wrote that down, and "the renderer looks like it still works" is
 * not a measurement across 439 elements.
 *
 * So this records the answers now, from laminas, and the baseline becomes the
 * specification the replacement is written against. It is the same contract as
 * `test/Fuzz/known-form-gaps.php`: generated deliberately, read line by line when it
 * changes, and worth nothing at all if regenerating is treated as a way to make a test
 * pass.
 *
 * ## What is recorded, and what is deliberately not
 *
 * Everything the renderer can read, plus the type-deciding facts the specification
 * helpers branch on. **Not** the rendered HTML: the renderer is not what step 6 changes,
 * and an HTML baseline over 136 database-populated selects would be a megabyte of options
 * that churns whenever the capsule's data does. What changes is what an element *answers*,
 * so that is what is compared — and it diffs as a name and a value rather than as a wall
 * of markup.
 *
 * ## Two things have to be normalised, and both are narrow
 *
 * A `Csrf` element's value is a fresh token on every read, and one date field defaults to
 * today. Left alone, the baseline would differ from itself between two runs a day apart.
 * Both are replaced by a marker, and the replacements are exact patterns rather than a
 * general "ignore values that look volatile" — a normaliser that is too generous is a
 * baseline that stops noticing.
 *
 * ## The baseline is recorded under one locale, deliberately
 *
 * 136 of these selects are filled by a form factory from `SchoenstattTable`, which picks a
 * name out of `nameByLocale` using `\Locale::getDefault()`. That is process state, and in a
 * CLI process it is `en_US_POSIX` — not one of the five keys, so every label comes back
 * `null`. {@see collect()} pins it to what a Symfony-served request gets and builds its own
 * repository inside that window; the reasoning is at the call site.
 *
 * Value options are recorded as a **count and a digest of the keys** plus the first three
 * and last one, rather than in full. 136 selects hold up to 325 options each, most of them
 * read from the database; recording them whole would make the file unreadable and would
 * put the capsule's data in the repository. The digest still moves when an option list
 * changes shape, and the samples still catch a change in how keys or labels are produced.
 *
 * Six of those selects are filled from the persons table, and there the sample was four
 * living people's names. {@see redactPersons()} replaces a sampled label that is somebody's
 * name with `<person>`, keeping the key; for those six the samples therefore catch a change
 * in how keys are produced, and the digest — taken before the redaction — still catches a
 * change in the labels.
 */
final class ElementSurface
{
    /** What a Csrf token is replaced by. */
    private const TOKEN = '<csrf-token>';

    /** What a sampled option label that names a person is replaced by. */
    private const PERSON = '<person>';

    /** What today's date is replaced by, wherever it appears as a value or an attribute. */
    private const TODAY = '<today>';

    /**
     * `Form\Class::path/to/element` => the element's answers.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function collect(): array
    {
        $surface = [];
        foreach (self::elements() as $path => $element) {
            $surface[$path] = self::describe($element)
                + ($element instanceof Fieldset ? ['fieldset' => true] : []);
        }

        return $surface;
    }

    /**
     * Every element in the application, keyed by `Form\Class::path/to/element`.
     *
     * Separate from {@see collect()} because a second reader wants the elements themselves
     * rather than their answers: `test/Integration/ElementModelParityTest` builds a twin of
     * each one from `SionModel\Form\Element` and asks it the same questions.
     *
     * @return array<string, ElementInterface>
     */
    public static function elements(): array
    {
        $elements = [];

        //Both halves of this matter, and neither is tidiness.
        //
        //The locale, because a form factory asks SchoenstattTable for its value options and
        //that reads \Locale::getDefault() to pick a name out of `nameByLocale`. A CLI
        //process inherits `en_US_POSIX`, which is not one of the five keys, so every one of
        //the 496 association options comes back labelled `null` — the state the first
        //baseline was recorded in. `App\Http\LocaleListener` sets the same default for every
        //Symfony-served request, so `Locales::DEFAULT_LOCALE` is what a real render sees.
        //
        //A repository of our own, because the shared one is built by whichever test touches
        //it first: pinning the locale here would do nothing if the forms were already built
        //by an earlier test in the same process. That is exactly how this test passed alone
        //and failed in the suite.
        $previous = Locale::getDefault();
        Locale::setDefault(Locales::DEFAULT_LOCALE);

        try {
            $forms = FormRepository::fresh()->forms();
        } finally {
            Locale::setDefault($previous);
        }

        foreach ($forms as $class => $form) {
            self::walk((string) $class, $form, '', $elements);
        }

        ksort($elements);

        return $elements;
    }

    /** @param array<string, ElementInterface> $elements */
    private static function walk(string $class, Fieldset $fieldset, string $prefix, array &$elements): void
    {
        foreach ($fieldset->getElements() as $name => $element) {
            $elements[$class . '::' . $prefix . (string) $name] = $element;
        }

        foreach ($fieldset->getFieldsets() as $name => $child) {
            $path                            = $prefix . (string) $name;
            $elements[$class . '::' . $path] = $child;
            self::walk($class, $child, $path . '/', $elements);

            if ($child instanceof Collection) {
                $target = $child->getTargetElement();
                if ($target instanceof Fieldset) {
                    self::walk($class, $target, $path . '/<target>/', $elements);
                }
            }
        }
    }

    /**
     * The twelve-odd questions the application asks an element, and its answers.
     *
     * Public because the parity test asks them of a replacement element; a second list
     * written out there would drift from this one, and a baseline compared against a
     * drifted list proves less than it appears to.
     *
     * @return array<string, mixed>
     */
    public static function describe(ElementInterface $element): array
    {
        $described = [
            //The class is recorded because the specification helpers and the renderer both
            //branch on it today. When the element model lands this line is what says which
            //of ours took which of theirs.
            'class'      => $element::class,
            'name'       => (string) $element->getName(),
            'label'      => self::scalar($element->getLabel()),
            'value'      => self::scalar($element->getValue()),
            'attributes' => self::normaliseAll($element->getAttributes()),
            'options'    => self::normaliseAll($element->getOptions()),
        ];

        //Each branch named two classes — laminas' element and the SionModel one replacing
        //it — while both families were live, so that the parity test could describe one of
        //each and compare them. The laminas halves went with the package.
        if ($element instanceof Select) {
            $described['valueOptions'] = self::digestOptions($element->getValueOptions());
            $described['emptyOption']  = self::scalar($element->getEmptyOption());
        }
        if ($element instanceof Checkbox) {
            $described['checkedValue']     = self::scalar($element->getCheckedValue());
            $described['uncheckedValue']   = self::scalar($element->getUncheckedValue());
            $described['useHiddenElement'] = $element->useHiddenElement();
        }
        if ($element instanceof Csrf) {
            //The options, not the token: the options are what CsrfSpec reads to build the
            //validator, and the token is regenerated on every read.
            $described['csrfValidatorOptions'] = self::normaliseAll($element->getCsrfValidatorOptions());
        }
        if ($element instanceof DateSelect) {
            //Two templates reach for these directly.
            $described['dayElementName']   = (string) $element->getDayElement()->getName();
            $described['monthElementName'] = (string) $element->getMonthElement()->getName();
            $described['yearElementName']  = (string) $element->getYearElement()->getName();
        }

        return $described;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function normaliseAll(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            $out[(string) $key] = is_array($value)
                ? self::normaliseAll($value)
                : self::scalar($value);
        }
        ksort($out);

        return $out;
    }

    private static function scalar(mixed $value): mixed
    {
        if (null === $value || is_scalar($value)) {
            if (! is_string($value)) {
                return $value;
            }

            //A token is `<32 hex>-<32 hex>`; nothing else in these values has that shape.
            $normalised = preg_replace('/\b[0-9a-f]{32}-[0-9a-f]{32}\b/', self::TOKEN, $value);
            //Today, and only today: MassCheckoutFieldset's date defaults to it, so a
            //baseline taken yesterday would differ from one taken now.
            $normalised = str_replace(date('Y-m-d'), self::TODAY, (string) $normalised);

            return $normalised;
        }

        //Objects and closures appear in a few options (a Uri handler, a callback). Their
        //identity is not stable across runs, so the type is what is recorded.
        return '<' . get_debug_type($value) . '>';
    }

    /**
     * @param array<int|string, mixed> $options
     * @return array<string, mixed>
     */
    private static function digestOptions(array $options): array
    {
        $keys = array_keys($options);

        return [
            'count'  => count($keys),
            'digest' => md5(serialize($options)),
            'first'  => self::redactPersons(self::normaliseAll(array_slice($options, 0, 3, true))),
            'last'   => self::redactPersons(self::normaliseAll(array_slice($options, -1, 1, true))),
        ];
    }

    /**
     * Replace a sampled label that is somebody's name with {@see PERSON}.
     *
     * The samples exist to catch a change in how keys and labels are produced, and for
     * 130 of the 136 database-filled selects the labels are publishers, book titles and
     * subject terms — the catalogue, already public, and worth keeping legible in a diff.
     * Six are filled from the persons table, and there the same three-and-one sample was
     * four living people's names in a file a public repository keeps forever.
     *
     * The key survives, so a change in how keys are produced still shows; the digest is
     * taken before this runs, so a change in how *labels* are produced still moves it.
     * What is lost is being able to read the new label off the diff for those six, which
     * is the trade this makes deliberately.
     *
     * @param array<int|string, mixed> $sample
     * @return array<int|string, mixed>
     */
    private static function redactPersons(array $sample): array
    {
        $persons = FormRepository::personLabels();

        foreach ($sample as $key => $label) {
            if (is_string($label) && isset($persons[$label])) {
                $sample[$key] = self::PERSON;
            }
        }

        return $sample;
    }

    /** A one-line summary for a failure message. */
    public static function summarise(mixed $value): string
    {
        return sprintf('%s', is_array($value) ? '[' . count($value) . ' keys]' : self::scalar($value));
    }
}

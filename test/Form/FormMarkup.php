<?php

declare(strict_types=1);

namespace SchoenstattTest\Form;

use App\Locale\Locales;
use Laminas\Form\Element\Collection;
use Laminas\Form\Fieldset;
use Laminas\Form\ElementInterface;
use Laminas\Form\FormInterface;
use Locale;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Form\BootstrapFormRenderer;
use SionModel\Form\Element\Button;
use SionModel\Form\Element\Checkbox;
use SionModel\Form\Element\DateSelect;
use SionModel\Form\Element\File;
use SionModel\Form\Element\Select;
use SionModel\Form\Element\Submit;
use SionModel\Form\Element\Textarea;
use Throwable;

use function array_slice;
use function count;
use function date;
use function implode;
use function gc_collect_cycles;
use function ksort;
use function md5;
use function preg_match_all;
use function preg_replace;
use function preg_replace_callback;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_replace;

require_once __DIR__ . '/../Fuzz/FormRepository.php';
require_once __DIR__ . '/FormData.php';

/**
 * The markup every form in the application produces, in the four states it is rendered in.
 *
 * ## Why this exists before iteration A does
 *
 * Iteration A removes laminas-form, laminas-inputfilter, laminas-filter and
 * laminas-validator, and replaces `Laminas\Form\{Form, Fieldset, Collection}` with our
 * own. The contract that replacement has to meet is **rendered-HTML parity**: the host's
 * CSS and its selectize/markdown bundle are written against
 * `SionModel\Form\BootstrapFormRenderer`'s exact bytes, and a moderator editing a shrine
 * should not see the form rearrange itself because a package left.
 *
 * Nothing recorded that. `test/Element/element-surface.php` records what every element
 * *answers* — deliberately not its markup, because the element swap did not touch the
 * renderer — and the form swap is the other half: the renderer keeps asking the same
 * twelve questions, but of a form model that is no longer laminas'. So this records the
 * answers as bytes, now, while laminas is still the thing producing them.
 *
 * It is the same contract as `test/Fuzz/known-form-gaps.php` and the element surface:
 * generated deliberately, read line by line when it changes, and worth nothing at all if
 * regenerating is treated as a way to make a test pass.
 *
 * ## The four states, and why exactly these
 *
 * They are the four a controller puts a form in, and each one reaches different code:
 *
 * - **pristine** — the form as its factory built it. What a create page renders on GET.
 * - **populated** — `setData()` with a value in every field and no validation. What an
 *   edit page renders on GET (`App\Controller\EntityEditController` calls
 *   `$form->setData($object)` and renders). This is the state that exercises value
 *   escaping and a `<select>`'s selected option.
 * - **invalid** — `setData()` with a value every validator should reject, then
 *   `isValid()`. What every create/edit page renders when a POST fails. This is the only
 *   state with `getMessages()` behind it, so it is the only one that records what
 *   `errors()` emits — which is also the only recording of the twenty laminas validator
 *   classes' message text that survives their removal.
 * - **prepared** — `prepare()` over a form with no data, which is what the mass-checkout
 *   page renders on GET. Two behaviours, and both are load-bearing: it renames a
 *   fieldset's elements to `fieldset[element]` — what a browser posts, and what
 *   `library-mass-checkout.html.twig`'s script selects by, as
 *   `input[name='checkout[0][checkedOutOn]']` — and it materialises a collection's `count`
 *   rows, which is the difference between the four the librarian sees and none.
 *   `App\Controller\LibraryMassCheckoutController` is the one controller that calls it.
 *   It gets a build of its own; see {@see BUILDS} for the laminas behaviour that forces
 *   that, which is also the reason the other three describe the forms as the pages that
 *   never call `prepare()` render them.
 *
 * A fifth state — an empty submit, so that required fields report "value is required" —
 * was measured and dropped: `Form::isValid()` sets the messages it finds and clears none,
 * so running two validating states over one build leaves the first one's messages standing
 * on every element the second one's data satisfies. Two passes over two builds of every
 * form would avoid that, at ten seconds and 250 MB apiece; one state whose data is invalid
 * *and* present buys the same message coverage in one pass, and "value is required and
 * can't be empty" appears in it fifteen times anyway, from the fields whose filter rejects
 * the value rather than the absence.
 *
 * ## Only the differences are recorded, after the first state
 *
 * There are 483 rendered surfaces and each state renders every one of them. Recording all
 * four in full would quadruple a file whose whole value is that a human reads its diff. So
 * every state after the first records only the helper outputs that **differ** from the
 * state it follows — {@see BASELINE_STATE} names which that is — and a helper absent from
 * a state renders there exactly as it does in the state it is read against. A path absent
 * from a state does not exist in it at all, which is how a collection that materialises a
 * row once data arrives reads.
 *
 * ## What is normalised, and why each rule is narrow
 *
 * Three rules, and each erases something that differs between two runs of the *same*
 * code rather than something that could differ between laminas and its replacement:
 *
 * 1. **A CSRF token** is a fresh hash on every read.
 * 2. **Today's date** is `MassCheckoutFieldset`'s default, so a baseline taken yesterday
 *    would differ from one taken now.
 * 3. **An option list longer than four** collapses to its first three, a count, an md5 of
 *    the middle and its last. 136 of these selects are filled from the database and the
 *    raw capture is 2.2 MB against 125 KB normalised — a megabyte of `<option>` tags that
 *    would put the capsule's export in the repository and churn whenever it moved. The
 *    digest still moves when the list does, the `<select>` attributes stay exact, and the
 *    options themselves are what `test/Element/element-surface.php` already digests.
 *
 * ## Translation is the identity function, deliberately
 *
 * `App\Twig\TwigFactory` hands the renderer `App\Twig\LaminasExtension::translate()`,
 * which is page- and text-domain-aware. A baseline through it would record the capsule's
 * catalogs rather than the forms, and would move whenever a phrase was translated.
 * `test/Integration/SelectRenderingParityTest` and `BootstrapFormRendererTest` pass the
 * identity function for the same reason, so the recorded labels are the source strings.
 */
final class FormMarkup
{
    /** The `action` every `<form>` is opened with. A real path would add nothing. */
    public const ACTION = '/form-action';

    /** What a CSRF token is replaced by. */
    private const TOKEN = '<csrf-token>';

    /** What today's date is replaced by, wherever it appears as a value or an attribute. */
    private const TODAY = '<today>';

    /** Beyond this many `<option>` tags in one run, the middle becomes a digest. */
    private const OPTIONS_KEPT = 4;

    /** The four states, in the order they are applied to one build of every form. */
    public const STATES = ['pristine', 'populated', 'invalid', 'prepared'];

    /**
     * Which state each one records its differences against.
     *
     * Three of them answer "what does data do to this form", so they are read against the
     * form with none. `prepared` answers something else — what `prepare()` renames — and
     * reading that against `pristine` would bury the renaming under every value the two
     * data states set. Against the state it actually follows, its diff is the renaming and
     * nothing else.
     */
    public const BASELINE_STATE = [
        'populated' => 'pristine',
        'invalid'   => 'pristine',
        'prepared'  => 'pristine',
    ];

    /**
     * Which states share one build of every form, in order.
     *
     * The first three are cumulative on purpose: `pristine` is read before anything is
     * set, and each of the other two overwrites every value the previous one wrote, so one
     * build answers all three and the ten seconds and 250 MB a build costs are spent once.
     *
     * `prepared` cannot join them, and the reason is a laminas behaviour worth naming:
     * `Collection::addNewTargetElementInstance()` sets `shouldCreateChildrenOnPrepareElement`
     * to false, so a collection that has already taken data renders exactly the rows that
     * were submitted and `prepare()` adds none. Recorded on the same build as the data
     * states, the mass-checkout form's collection showed **one** row — the one `invalid`
     * gave it — where the page a librarian opens shows the four its `count` asks for. So
     * `prepared` gets a build of its own, pristine, and the 250 MB is freed first.
     */
    private const BUILDS = [
        ['pristine', 'populated', 'invalid'],
        ['prepared'],
    ];

    /** @var array<string, int> */
    private array $diagnostics = [];

    /**
     * `Form\Class::path/to/element` => state => helper => markup.
     *
     * Every state after the first holds only what differs from the state {@see
     * BASELINE_STATE} reads it against; see the class docblock. An element that exists in
     * one state and not another — a collection materialises a row when data arrives — is
     * recorded under the states it exists in, and its absence elsewhere is the finding.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public static function collect(): array
    {
        return (new self())->record();
    }

    /**
     * How many PHP diagnostics were swallowed while rendering, by type.
     *
     * The 2020-era module code emits deprecations and the CSRF elements try to start a
     * session; `phpunit.xml.dist` sets `failOnWarning="true"`, so they are suppressed
     * here the way `FormRepository::quietly()` suppresses the ones construction emits.
     * Exposed rather than hidden.
     *
     * @return array<string, int>
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /** @return array<string, array<string, array<string, string>>> */
    public function record(): array
    {
        $renderer = new BootstrapFormRenderer(static fn (string $message): string => $message);

        $recorded = [];
        //The full markup of every state, kept beside the recorded differences: a state
        //records what moved since the state it follows, so the comparison needs that
        //state's whole rendering rather than its own list of differences.
        $full = [];

        foreach (self::BUILDS as $states) {
            $forms = $this->build();

            foreach ($states as $state) {
                $this->quietly(function () use ($forms, $state): void {
                    self::apply($forms, $state);
                });

                $full[$state] = $this->quietly(fn (): array => self::renderAll($forms, $renderer));
                $against      = self::BASELINE_STATE[$state] ?? null;

                foreach ($full[$state] as $path => $helpers) {
                    $recorded[$path][$state] = null === $against
                        ? $helpers
                        : self::differences($full[$against][$path] ?? [], $helpers);
                }
            }

            //Freed before the next build rather than after the loop: two repositories
            //alive at once is 500 MB of forms and their value options, and the integration
            //suite runs at 1 GB with other tests' repositories already in it.
            unset($forms);
            gc_collect_cycles();
        }

        ksort($recorded);

        return $recorded;
    }

    /**
     * Every form in the application, built under the locale a Symfony-served request has.
     *
     * The locale, for the same reason ElementSurface pins it: a form factory asks
     * SchoenstattTable for its value options and that reads `\Locale::getDefault()` to pick
     * a name out of `nameByLocale`. A CLI process inherits `en_US_POSIX`, which is not one
     * of the five keys, so every association option would be labelled `null`. Its own
     * repository, because the shared one is built by whichever test touches it first and
     * would already hold whatever locale that test ran under.
     *
     * @return array<class-string, Fieldset>
     */
    private function build(): array
    {
        $previous = Locale::getDefault();
        Locale::setDefault(Locales::DEFAULT_LOCALE);

        try {
            return FormRepository::fresh()->forms();
        } finally {
            Locale::setDefault($previous);
        }
    }

    // ------------------------------------------------------------------ states

    /**
     * Put every form into one state.
     *
     * A bare `Fieldset` subject — `App\Books\Import\ImportMappingFieldset` and
     * `Books\Form\MassCheckoutFieldset` are discovered in their own right — has no
     * `setData()` and no input filter, so it is populated through `populateValues()` and
     * never validated. That is not a gap: both are rendered as part of the form that owns
     * them, and that form is a subject too.
     *
     * @param array<class-string, Fieldset> $forms
     */
    private static function apply(array $forms, string $state): void
    {
        if ('pristine' === $state) {
            return;
        }

        if ('prepared' === $state) {
            foreach ($forms as $form) {
                if ($form instanceof FormInterface) {
                    $form->prepare();
                }
            }

            return;
        }

        $valid = 'populated' === $state;

        foreach ($forms as $form) {
            $data = FormData::forFieldset($form, $valid);

            if ($form instanceof FormInterface) {
                $form->setData($data);
                if (! $valid) {
                    try {
                        $form->isValid();
                    } catch (Throwable) {
                        //A form that cannot validate still renders, and its markup is the
                        //subject here. FormValidationContractTest is what reports a form
                        //whose input filter will not assemble.
                    }
                }
                continue;
            }

            $form->populateValues($data);
        }
    }

    // --------------------------------------------------------------- rendering

    /**
     * @param array<class-string, Fieldset> $forms
     * @return array<string, array<string, string>>
     */
    private static function renderAll(array $forms, BootstrapFormRenderer $renderer): array
    {
        $markup = [];

        foreach ($forms as $class => $form) {
            if ($form instanceof FormInterface) {
                $markup[$class . '::<form>'] = [
                    'open'  => self::normalise($renderer->open($form, self::ACTION)),
                    'close' => $renderer->close(),
                ];
            }

            self::walk((string) $class, $form, '', $renderer, $markup);
        }

        return $markup;
    }

    /**
     * The element tree, keyed the way `test/Element/element-surface.php` keys it so the
     * two baselines can be read side by side.
     *
     * @param array<string, array<string, string>> $markup
     */
    private static function walk(
        string $class,
        Fieldset $fieldset,
        string $prefix,
        BootstrapFormRenderer $renderer,
        array &$markup
    ): void {
        foreach ($fieldset->getElements() as $name => $element) {
            $markup[$class . '::' . $prefix . (string) $name] = self::helpers($element, $renderer);
        }

        foreach ($fieldset->getFieldsets() as $name => $child) {
            $path = $prefix . (string) $name;
            self::walk($class, $child, $path . '/', $renderer, $markup);

            if ($child instanceof Collection) {
                $target = $child->getTargetElement();
                if ($target instanceof Fieldset) {
                    self::walk($class, $target, $path . '/<target>/', $renderer, $markup);
                }
            }
        }
    }

    /**
     * Every helper a template can call on this element, and what it renders.
     *
     * `SionModel\Twig\FormExtension` publishes twelve functions and a template picks among
     * them per element — `association-edit.html.twig` builds two groups by hand out of
     * `form_label`/`form_element`/`help_block` and gives everything else a `form_row`,
     * mass-checkout calls `form_text` on a `Date` so the barcode scanner gets a text box,
     * and the publication form's five pickers go through
     * `form_select_without_options`. Recording only the helper a template happens to call
     * today would leave the next template's call unrecorded, so every helper that accepts
     * the element is recorded and the type decides which those are.
     *
     * `form_open`/`form_close` are the form's, recorded against `::<form>`.
     *
     * @return array<string, string>
     */
    private static function helpers(ElementInterface $element, BootstrapFormRenderer $renderer): array
    {
        $helpers = [
            'row'        => static fn (): string => $renderer->row($element),
            'element'    => static fn (): string => $renderer->element($element),
            'label'      => static fn (): string => $renderer->label($element),
            'errors'     => static fn (): string => $renderer->errors($element),
            'help_block' => static fn (): string => $renderer->helpBlock($element),
        ];

        if ($element instanceof Submit) {
            $helpers['submit'] = static fn (): string => $renderer->submit($element);
        }
        if ($element instanceof Button) {
            $helpers['button'] = static fn (): string => $renderer->button($element);
        }
        if ($element instanceof Select) {
            $helpers['select_without_options'] = static fn (): string
                => $renderer->selectWithoutOptions($element);
        }
        if (self::isInputFamily($element)) {
            $helpers['text']   = static fn (): string => $renderer->text($element);
            $helpers['hidden'] = static fn (): string => $renderer->hidden($element);
        }

        $rendered = [];
        foreach ($helpers as $name => $render) {
            try {
                $rendered[$name] = self::normalise($render());
            } catch (Throwable $e) {
                //Recorded rather than thrown: a helper that cannot render this element is
                //a fact about the pair, and a baseline that dies on the first one records
                //nothing about the other 476.
                $rendered[$name] = '<<throws ' . $e::class . ': ' . $e->getMessage() . '>>';
            }
        }

        return $rendered;
    }

    /**
     * Whether `form_text` and `form_hidden` mean anything for this element.
     *
     * Both call `input()`, which is what `element()` already does for this family; for a
     * select or a textarea they would render a text box that no template asks for and no
     * browser would ever show, and recording that is recording noise.
     */
    private static function isInputFamily(ElementInterface $element): bool
    {
        return ! $element instanceof Select
            && ! $element instanceof Checkbox
            && ! $element instanceof Textarea
            && ! $element instanceof Submit
            && ! $element instanceof Button
            && ! $element instanceof DateSelect
            && ! $element instanceof File
            && ! $element instanceof Fieldset;
    }

    // ----------------------------------------------------------- normalisation

    private static function normalise(string $markup): string
    {
        //A token is `<32 hex>-<32 hex>`; nothing else in this markup has that shape.
        $markup = (string) preg_replace('/\b[0-9a-f]{32}-[0-9a-f]{32}\b/', self::TOKEN, $markup);
        $markup = str_replace(date('Y-m-d'), self::TODAY, $markup);

        return self::digestOptions($markup);
    }

    /**
     * A run of more than four `<option>` tags keeps its first three and its last, and the
     * middle becomes a count and a digest.
     *
     * The run is matched with the whitespace between the tags, because
     * `BootstrapFormRenderer::select()` appends a newline after each one — matching
     * without it silently matches nothing, which is how a first attempt at this
     * "normalised" 2.3 MB into 2.3 MB.
     */
    private static function digestOptions(string $markup): string
    {
        return (string) preg_replace_callback(
            '#(?:<option\b.*?</option>\s*)+#s',
            static function (array $matches): string {
                preg_match_all('#<option\b.*?</option>\s*#s', $matches[0], $all);
                $options = $all[0];
                if (count($options) <= self::OPTIONS_KEPT) {
                    return $matches[0];
                }

                $middle = array_slice($options, self::OPTIONS_KEPT - 1, -1);

                return implode('', array_slice($options, 0, self::OPTIONS_KEPT - 1))
                    . sprintf(
                        "<!-- %d more options, %s -->\n",
                        count($middle),
                        md5(implode('', $middle))
                    )
                    . $options[count($options) - 1];
            },
            $markup
        );
    }

    /**
     * What the second and third states record: the helpers whose markup moved.
     *
     * @param array<string, string> $pristine
     * @param array<string, string> $current
     * @return array<string, string>
     */
    private static function differences(array $pristine, array $current): array
    {
        $changed = [];
        foreach ($current as $helper => $markup) {
            if (($pristine[$helper] ?? null) !== $markup) {
                $changed[$helper] = $markup;
            }
        }

        return $changed;
    }

    // ------------------------------------------------------------- diagnostics

    /**
     * Run a closure with PHPUnit's error handler replaced by a counter.
     *
     * `FormRepository::quietly()` does this for construction; rendering needs it too, and
     * for one reason construction does not have: reading a `Csrf` element's value
     * regenerates its token, which starts a session, and a CLI process that has written a
     * byte of output cannot ("Session ini settings cannot be changed after headers have
     * already been sent"). 35 forms carry one.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    private function quietly(callable $work): mixed
    {
        set_error_handler(function (int $severity, string $message): bool {
            $this->diagnostics[self::severityName($severity)]
                = ($this->diagnostics[self::severityName($severity)] ?? 0) + 1;

            return true;
        });

        try {
            return $work();
        } finally {
            restore_error_handler();
        }
    }

    private static function severityName(int $severity): string
    {
        return match ($severity) {
            E_DEPRECATED, E_USER_DEPRECATED => 'deprecation',
            E_WARNING, E_USER_WARNING => 'warning',
            E_NOTICE, E_USER_NOTICE => 'notice',
            default => 'other',
        };
    }
}

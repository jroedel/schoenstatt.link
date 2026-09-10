<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\Filter\FilterPluginManager;
use Laminas\Form\Element\DateSelect;
use Laminas\Form\Element\MonthSelect;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorPluginManager;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Form\Validation\FormSpecification;
use SionModel\Form\Validation\InputFilter as Engine;
use Throwable;

use function array_keys;
use function count;
use function implode;
use function is_array;
use function sprintf;
use function array_key_exists;
use function strlen;
use function strrpos;
use function substr;
use function var_export;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Fuzz/FormRepository.php';

/**
 * A whole submission, through both filters, compared field by field.
 *
 * ## What this adds to the two tests that came before it
 *
 * `InputFilterEngineParityTest` feeds laminas' `Factory` and the engine the same
 * specification: it proves they read a specification alike and nothing else.
 * `EngineMatchesAssembledFilterTest` compares one field at a time against the filter the
 * application really validates with, which is the comparison that found four association
 * fields nobody had listed — but it drives each input in isolation, with a specification
 * of one key, so it cannot see anything about the filter as a whole.
 *
 * The cutover replaces the whole filter, so the whole filter is what has to agree:
 *
 *   - **the shape of `getValues()`** — every key laminas returns, in the order it returns
 *     them, because `getData()` goes straight to `SionTable::updateEntity()` and a missing
 *     key there is a column that stops being written;
 *   - **which fields carry messages** — because that is what `Fieldset::setMessages()`
 *     distributes to the elements the template renders;
 *   - **fieldsets and collections**, which no per-field comparison reaches at all:
 *     `App\Books\Import\ImportMappingForm` nests nineteen selects under `map`, and
 *     `Books\Form\MassCheckoutForm` repeats a fieldset under `checkout`.
 *
 * ## Both sides are driven at the filter, not at the form
 *
 * `$form->getInputFilter()->setData(...)` rather than `$form->setData(...)`, on both sides
 * and deliberately. `SionModel\Form\SionForm::setData()` decodes HTML entities and blanks
 * unusable `DateSelect` values *before* either filter sees anything, and it is not what
 * the cutover replaces — it sits above both. Driving the form would compare the filters
 * through a preprocessor common to both, which is a weaker statement and a noisier one.
 *
 * ## Two things deliberately not compared
 *
 * **Message text.** laminas' `ValidatorChain` runs every validator and merges their
 * messages; the engine stops at the first failure, which is the same verdict with a
 * shorter list. Comparing text would pin that difference as though it were a defect. Which
 * *fields* failed is the part the application acts on, and that is compared exactly.
 *
 * **`security`.** `Laminas\Validator\Csrf` reads a session container and regenerates its
 * token on each use, so driving it here would compare session bookkeeping. It is covered
 * where it means something: `testTheCsrfSpecificationBuildsTheElementsOwnValidator` below
 * checks the one property that matters — the container key — statically, and the smoke
 * suite posts real tokens through real forms.
 */
final class WholeFormEngineParityTest extends TestCase
{
    /**
     * Applied to every field of a form at once, one submission per value, plus the empty
     * submission. Strings and `null` only: the shapes `$_POST` can hold.
     *
     * The set is small on purpose. This test's subject is the filter as a whole — key
     * shape, nesting, which fields failed — and forty corpus values would multiply the
     * run time without reaching a different code path. Per-value depth over a single field
     * is `EngineMatchesAssembledFilterTest`'s job, and it uses seven probes over 2,149
     * comparisons to do it.
     */
    private const PROBES = [null, '', '0', '1', 'wat', '2026-01-01', '<script>alert(1)</script>'];

    /**
     * Fields where the engine and the assembled filter are known to differ, with the same
     * reasons `EngineMatchesAssembledFilterTest::KNOWN_DIFFERENCES` records. Skipped
     * per field rather than per form, so the other 40-odd fields of `ImportForm` are still
     * compared.
     *
     * @var array<string, string>
     */
    private const KNOWN_DIFFERENCES = [
        //Value options arrive per request; the harness builds the form with none, so the
        //element's own InArray has an empty haystack and rejects everything.
        'Books\Form\SearchForm::collectionId'            => 'options are populated per request',
        'Books\Form\CheckoutForm::personId'              => 'options are populated per request',
        'Schoenstatt\Form\ImportFatherForm::personId'    => 'options come from an HTTP gateway',
        //Same service, reached through the collection's target element. `CheckoutForms::mass()`
        //populates it from `Schoenstatt\FathersValueOptions`, which is an HTTP call to patres
        //and answers with an empty list from the capsule — so the element's own InArray holds
        //an empty haystack and rejects every id, while the specification's ChoiceDomain
        //declines to constrain a domain it cannot see. In a request both are the same list.
        'Books\Form\MassCheckoutForm::personId'          => 'options come from an HTTP gateway',
        'App\Books\Import\ImportMappingForm::worksheet'  => 'options are the uploaded file\'s sheets',
        //FileInput injects Laminas\Validator\File\UploadFile at isValid() time, so laminas
        //refuses a $_FILES-shaped array PHP did not put there. Unreachable in production:
        //an upload arrives in $request->files and never in the form's data.
        'Books\Form\ImportForm::file'                    => 'FileInput injects an upload validator',
    ];

    private const SESSION_BOUND = 'security';

    public function testEveryFormAnswersTheSameOnAWholeSubmission(): void
    {
        $disagreed   = [];
        $comparisons = 0;
        $formsSeen   = 0;

        foreach (FormRepository::instance()->forms() as $class => $form) {
            if (! $form instanceof Form) {
                continue;
            }

            try {
                $assembled = $form->getInputFilter();
                $spec      = FormSpecification::of($form);
            } catch (Throwable $e) {
                $disagreed[] = sprintf('%s: could not be assembled — %s', $class, $e->getMessage());
                continue;
            }

            //`security` is left in place on both sides rather than removed. Removing it
            //would mutate the filter FormRepository hands to every other test in the
            //process — which it did, and testTheValueArrayHasTheSameKeysInTheSameOrder
            //duly reported 34 forms whose laminas half was missing exactly one input.
            //It is skipped where it is compared instead.
            if ([] === $spec) {
                continue;
            }
            $formsSeen++;

            foreach (self::PROBES as $probe) {
                $data = self::submission($spec, $probe);

                $laminas = self::runLaminas($assembled, $data);
                $ours    = self::runEngine($spec, $data);

                if (null === $laminas) {
                    //laminas threw on a submission the engine answered. Reported rather
                    //than skipped: an uncaught exception in a controller action is a 500
                    //with the visitor's whole submission lost, so "the engine answers
                    //where laminas throws" is a finding worth reading, not a difference to
                    //bury. There are none today.
                    $disagreed[] = sprintf(
                        '%s with %s — laminas threw, the engine answered',
                        $class,
                        var_export($probe, true)
                    );
                    continue;
                }

                //Flattened to `checkout/0/personId` rather than compared a top level at a
                //time, because a collection is one key: with `messages['checkout']` as the
                //unit, a row where the engine is stricter about one field and laminas
                //about another reads as agreement. Flattening is what turned one reported
                //difference on MassCheckoutForm into the four real ones underneath it.
                $laminasFailed = self::failedPaths($laminas['messages']);
                $oursFailed    = self::failedPaths($ours['messages']);
                $laminasValues = self::flatValues($laminas['values']);
                $oursValues    = self::flatValues($ours['values']);

                foreach (self::pathsOf($spec) as $path) {
                    if (self::isSkipped($class, $path)) {
                        continue;
                    }

                    $comparisons++;

                    if (isset($laminasFailed[$path]) !== isset($oursFailed[$path])) {
                        $disagreed[] = sprintf(
                            '%s::%s with %s — laminas %s it, the engine %s it',
                            $class,
                            $path,
                            var_export($probe, true),
                            isset($laminasFailed[$path]) ? 'rejected' : 'accepted',
                            isset($oursFailed[$path]) ? 'rejected' : 'accepted'
                        );
                    }

                    $laminasHas = array_key_exists($path, $laminasValues);
                    $oursHas    = array_key_exists($path, $oursValues);
                    if ($laminasHas !== $oursHas) {
                        $disagreed[] = sprintf(
                            '%s::%s with %s — %s in getValues()',
                            $class,
                            $path,
                            var_export($probe, true),
                            $laminasHas ? 'only laminas returns it' : 'only the engine returns it'
                        );
                        continue;
                    }
                    if ($laminasHas && $laminasValues[$path] != $oursValues[$path]) {
                        $disagreed[] = sprintf(
                            '%s::%s with %s — laminas kept %s, the engine kept %s',
                            $class,
                            $path,
                            var_export($probe, true),
                            self::describe($laminasValues[$path]),
                            self::describe($oursValues[$path])
                        );
                    }
                }
            }
        }

        self::assertGreaterThanOrEqual(
            35,
            $formsSeen,
            'almost no form was compared, so this test proves nothing'
        );
        //2,499 today: seven submissions across 36 forms with a specification. A floor
        //rather than the number, so adding a form or a field needs no edit here, and low
        //enough to survive one form losing its factory — but high enough that a skip rule
        //growing to cover everything fails instead of passing quietly.
        self::assertGreaterThanOrEqual(
            2400,
            $comparisons,
            'almost no field was compared, so this test proves nothing'
        );
        self::assertSame(
            [],
            $disagreed,
            "The engine over FormSpecification disagreed with the filter the application "
            . "validates with today:\n  " . implode("\n  ", $disagreed)
        );
    }

    /**
     * A `DateSelect` posts three selects, and the shape only agrees if the specification
     * declares the filter that reassembles them.
     *
     * The probes above are strings and `null` — the shapes a text input can hold — so they
     * never reach this. `Laminas\Form\Element\DateSelect` renders `year`, `month` and `day`
     * as three `<select>`s, the browser posts an array, and
     * `DateSelect::getInputSpecification()` supplies the `Laminas\Filter\DateSelect` that
     * turns it into `Y-m-d`.
     *
     * Without that filter in the specification the array reaches `Laminas\Validator\Date`
     * unchanged and the field fails — which is exactly what happened the first time the
     * engine was put behind the forms: the smoke suite could not create a person, on a
     * field the form does not even render on that page. This is the assertion that would
     * have said so first.
     */
    public function testADateSelectPostsThreeSelectsAndBothFiltersAgree(): void
    {
        $posted    = ['year' => '2026', 'month' => '3', 'day' => '17'];
        $checked   = 0;
        $disagreed = [];

        foreach (FormRepository::instance()->forms() as $class => $form) {
            if (! $form instanceof Form) {
                continue;
            }

            foreach ($form->getElements() as $name => $element) {
                if (! $element instanceof DateSelect && ! $element instanceof MonthSelect) {
                    continue;
                }
                $name = (string) $name;

                try {
                    $assembled = $form->getInputFilter();
                    $spec      = FormSpecification::of($form);
                } catch (Throwable) {
                    continue;
                }
                if (! $assembled->has($name) || ! isset($spec[$name])) {
                    continue;
                }

                $checked++;

                $laminas = self::runLaminas($assembled, [$name => $posted]);
                $ours    = self::runEngine($spec, [$name => $posted]);

                if (null === $laminas) {
                    $disagreed[] = sprintf('%s::%s — laminas threw on a three-select post', $class, $name);
                    continue;
                }

                $laminasFailed = isset($laminas['messages'][$name]);
                $oursFailed    = isset($ours['messages'][$name]);
                if ($laminasFailed !== $oursFailed) {
                    $disagreed[] = sprintf(
                        '%s::%s — laminas %s the three-select post, the engine %s it',
                        $class,
                        $name,
                        $laminasFailed ? 'rejected' : 'accepted',
                        $oursFailed ? 'rejected' : 'accepted'
                    );
                }
                if (($laminas['values'][$name] ?? null) != ($ours['values'][$name] ?? null)) {
                    $disagreed[] = sprintf(
                        '%s::%s — laminas filtered it to %s, the engine to %s',
                        $class,
                        $name,
                        self::describe($laminas['values'][$name] ?? null),
                        self::describe($ours['values'][$name] ?? null)
                    );
                }
            }
        }

        self::assertGreaterThanOrEqual(1, $checked, 'no DateSelect element was found to drive');
        self::assertSame([], $disagreed, implode("\n  ", $disagreed));
    }

    /**
     * A validation group narrows what is checked **and what comes back**.
     *
     * `BaseInputFilter::getValues()` iterates `$this->validationGroup ?? array_keys($this->inputs)`,
     * so a field outside the group is neither validated nor returned.
     * `Schoenstatt\Form\EditAssignmentForm` depends on exactly that and says so in a
     * comment — its three disabled selects must not be re-saved from a submitted value —
     * and getting the second half wrong would be invisible in a verdict and visible in the
     * database.
     *
     * Driven generically over every form rather than through the two `prepareForEdit()`
     * methods that set one, because those mutate the shared form instances this harness
     * hands to every other test.
     */
    public function testAValidationGroupNarrowsBothHalvesTheSameWay(): void
    {
        $disagreed = [];
        $checked   = 0;

        foreach (FormRepository::instance()->forms() as $class => $form) {
            if (! $form instanceof Form) {
                continue;
            }

            try {
                $assembled = $form->getInputFilter();
                $spec      = FormSpecification::of($form);
            } catch (Throwable) {
                continue;
            }

            //A group of the first three names that are neither session-bound nor known to
            //differ: enough to prove both halves narrow, few enough that the rest of the
            //form is demonstrably excluded.
            $group = [];
            foreach (array_keys($spec) as $name) {
                $name = (string) $name;
                if (self::SESSION_BOUND === $name || self::isSkipped($class, $name)) {
                    continue;
                }
                $group[] = $name;
                if (3 === count($group)) {
                    break;
                }
            }
            if (count($group) < 2) {
                continue;
            }

            $data = self::submission($spec, 'wat');

            try {
                $assembled->setValidationGroup($group);
                $laminas = self::runLaminas($assembled, $data);
            } finally {
                //Never leave the shared filter narrowed: every later test would then be
                //comparing three fields and passing.
                $assembled->setValidationGroup(InputFilterInterface::VALIDATE_ALL);
            }

            if (null === $laminas) {
                continue;
            }

            $engine = new Engine($spec, self::filterFactory(), self::validatorFactory());
            $engine->setValidationGroup($group);
            $engine->setData($data);
            $engine->isValid();

            $checked++;

            if (array_keys($laminas['values']) !== array_keys($engine->getValues())) {
                $disagreed[] = sprintf(
                    "%s with group [%s]\n      laminas returned: %s\n      engine returned:  %s",
                    $class,
                    implode(', ', $group),
                    implode(', ', array_keys($laminas['values'])),
                    implode(', ', array_keys($engine->getValues()))
                );
            }
        }

        self::assertGreaterThanOrEqual(25, $checked, 'almost no form was narrowed');
        self::assertSame(
            [],
            $disagreed,
            "A validation group narrowed getValues() differently:\n  " . implode("\n  ", $disagreed)
        );
    }

    /**
     * The key order of `getValues()`, which the field-by-field comparison above cannot see.
     *
     * It matters for one reason and it is not aesthetics: a controller writes
     * `$form->getData()` into `SionTable::updateEntity()`, and a diff of two value arrays
     * is how anybody will ever debug a write that went wrong. Key order the same means the
     * two can be compared directly.
     */
    public function testTheValueArrayHasTheSameKeysInTheSameOrder(): void
    {
        $disagreed = [];

        foreach (FormRepository::instance()->forms() as $class => $form) {
            if (! $form instanceof Form) {
                continue;
            }

            try {
                $assembled = $form->getInputFilter();
                $spec      = FormSpecification::of($form);
            } catch (Throwable) {
                continue;
            }

            $laminasKeys = array_keys($assembled->getInputs());
            $ourKeys     = array_keys($spec);

            if ($laminasKeys !== $ourKeys) {
                $disagreed[] = sprintf(
                    "%s\n      laminas: %s\n      engine:  %s",
                    $class,
                    implode(', ', $laminasKeys),
                    implode(', ', $ourKeys)
                );
            }
        }

        self::assertSame(
            [],
            $disagreed,
            "FormSpecification produced different inputs from Form::attachInputFilterDefaults():\n  "
            . implode("\n  ", $disagreed)
        );
    }

    /**
     * The CSRF check, compared where a session cannot get in the way.
     *
     * `SionModel\Form\CsrfSpec` exists because a specification-side `Csrf` with default
     * options gets the validator's own default container key, `csrf`, while the element
     * wrote the token into a container named after the element — so the chain becomes
     * `Csrf(security), Csrf(csrf)` and **a valid token is rejected**. That was measured
     * before the helper was written; every form on the site would have refused every
     * submission.
     *
     * Comparing the two validators' names is the whole of that failure, and it needs no
     * session at all.
     */
    public function testTheCsrfSpecificationBuildsTheElementsOwnValidator(): void
    {
        $checked   = 0;
        $disagreed = [];

        foreach (FormRepository::instance()->forms() as $class => $form) {
            if (! $form instanceof Form || ! $form->has(self::SESSION_BOUND)) {
                continue;
            }

            $spec = FormSpecification::of($form);
            /** @var list<array{name: string, options?: array<string, mixed>}> $declared */
            $declared = $spec[self::SESSION_BOUND]['validators'] ?? [];

            if ([] === $declared) {
                $disagreed[] = sprintf('%s: no CSRF validator in the specification at all', $class);
                continue;
            }

            $element = $form->get(self::SESSION_BOUND);
            /** @psalm-suppress MixedMethodCall */
            $expected = $element->getCsrfValidator()->getName();

            foreach ($declared as $entry) {
                $checked++;
                /** @var object $validator */
                $validator = (self::validatorFactory())($entry['name'], $entry['options'] ?? []);
                /** @psalm-suppress MixedMethodCall */
                $actual = $validator->getName();

                if ($actual !== $expected) {
                    $disagreed[] = sprintf(
                        '%s: the specification builds a Csrf reading container %s, the element wrote to %s',
                        $class,
                        var_export($actual, true),
                        var_export($expected, true)
                    );
                }
            }
        }

        self::assertGreaterThanOrEqual(30, $checked, 'almost no CSRF element was checked');
        self::assertSame([], $disagreed, implode("\n  ", $disagreed));
    }

    // --------------------------------------------------------------------- driving

    /**
     * One submission with every field of the specification set to $probe, nested as the
     * specification is nested. A collection gets two rows, so a per-row disagreement
     * cannot hide behind a single-row shape.
     *
     * @param array<string, mixed> $spec
     * @return array<string, mixed>
     */
    private static function submission(array $spec, mixed $probe): array
    {
        $data = [];

        foreach ($spec as $name => $rules) {
            $name = (string) $name;
            if (! is_array($rules)) {
                continue;
            }
            if (is_array($rules['fieldset'] ?? null)) {
                $data[$name] = self::submission($rules['fieldset'], $probe);
                continue;
            }
            if (is_array($rules['collection'] ?? null)) {
                $row         = self::submission($rules['collection'], $probe);
                $data[$name] = [$row, $row];
                continue;
            }
            //A null probe means "this field was not submitted at all", which is a
            //different question from "it was submitted empty" and the one that separates
            //rules (1) and (2) from rule (3).
            if (null !== $probe) {
                $data[$name] = $probe;
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{values: array<string, mixed>, messages: array<string, mixed>}|null
     *         null when laminas threw
     */
    private static function runLaminas(InputFilterInterface $filter, array $data): ?array
    {
        try {
            $filter->setData($data);
            $filter->isValid();

            return ['values' => $filter->getValues(), 'messages' => $filter->getMessages()];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $spec
     * @param array<string, mixed> $data
     * @return array{values: array<string, mixed>, messages: array<string, mixed>}
     */
    private static function runEngine(array $spec, array $data): array
    {
        $engine = new Engine($spec, self::filterFactory(), self::validatorFactory());
        $engine->setData($data);
        $engine->isValid();

        return ['values' => $engine->getValues(), 'messages' => $engine->getMessages()];
    }

    /**
     * Every leaf path the specification describes, as `checkout/0/personId`.
     *
     * A collection is expanded to the two rows `submission()` sends, so a per-row
     * difference has a name of its own.
     *
     * @param array<string, mixed> $spec
     * @return list<string>
     */
    private static function pathsOf(array $spec, string $prefix = ''): array
    {
        $paths = [];

        foreach ($spec as $name => $rules) {
            $path = $prefix . (string) $name;
            if (! is_array($rules)) {
                continue;
            }
            if (is_array($rules['fieldset'] ?? null)) {
                foreach (self::pathsOf($rules['fieldset'], $path . '/') as $child) {
                    $paths[] = $child;
                }
                continue;
            }
            if (is_array($rules['collection'] ?? null)) {
                foreach ([0, 1] as $row) {
                    foreach (self::pathsOf($rules['collection'], $path . '/' . $row . '/') as $child) {
                        $paths[] = $child;
                    }
                }
                continue;
            }
            $paths[] = $path;
        }

        return $paths;
    }

    /**
     * `path => true` for every field that produced a message, at any depth.
     *
     * @param array<array-key, mixed> $messages
     * @return array<string, true>
     */
    private static function failedPaths(array $messages, string $prefix = ''): array
    {
        $failed = [];

        foreach ($messages as $name => $entry) {
            $path = $prefix . (string) $name;
            //A message set is `messageKey => text`; anything whose values are themselves
            //arrays is a nested filter's messages, not this field's.
            if (is_array($entry) && [] !== $entry && self::isNested($entry)) {
                foreach (self::failedPaths($entry, $path . '/') as $child => $ignored) {
                    $failed[$child] = true;
                }
                continue;
            }
            $failed[$path] = true;
        }

        return $failed;
    }

    /** @param array<array-key, mixed> $entry */
    private static function isNested(array $entry): bool
    {
        foreach ($entry as $value) {
            if (! is_array($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * `path => value` for every leaf of a values array.
     *
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private static function flatValues(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $name => $value) {
            $path = $prefix . (string) $name;
            if (is_array($value) && self::isNested($value) && [] !== $value) {
                foreach (self::flatValues($value, $path . '/') as $child => $leaf) {
                    $flat[$child] = $leaf;
                }
                continue;
            }
            $flat[$path] = $value;
        }

        return $flat;
    }

    private static function isSkipped(string $class, string $path): bool
    {
        if (self::SESSION_BOUND === $path) {
            return true;
        }

        //A path is skipped by its leaf as well as in full, so one entry covers both rows
        //of a collection.
        $leaf = $path;
        $cut  = strrpos($path, '/');
        if (false !== $cut) {
            $leaf = substr($path, $cut + 1);
        }

        return isset(self::KNOWN_DIFFERENCES[$class . '::' . $path])
            || isset(self::KNOWN_DIFFERENCES[$class . '::' . $leaf]);
    }

    private static function describe(mixed $value): string
    {
        $printed = var_export($value, true);

        return strlen($printed) > 120 ? substr($printed, 0, 117) . '...' : $printed;
    }

    // ------------------------------------------------------------------ resolvers

    private static ?FilterPluginManager $filters       = null;
    private static ?ValidatorPluginManager $validators = null;
    private static ?ServiceManager $container          = null;

    /** @return callable(string, array<string, mixed>): object */
    private static function filterFactory(): callable
    {
        self::$filters ??= new FilterPluginManager(self::container());

        return static fn(string $name, array $options): object => self::$filters->get($name, $options);
    }

    /** @return callable(string, array<string, mixed>): object */
    private static function validatorFactory(): callable
    {
        self::$validators ??= new ValidatorPluginManager(self::container());

        return static fn(string $name, array $options): object => self::$validators->get($name, $options);
    }

    private static function container(): ServiceManager
    {
        if (null === self::$container) {
            /** @var array<string, mixed> $appConfig */
            $appConfig       = require __DIR__ . '/../../config/application.config.php';
            self::$container = \App\Laminas\ContainerFactory::build($appConfig);
        }

        return self::$container;
    }
}

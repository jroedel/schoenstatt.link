<?php

declare(strict_types=1);

namespace SchoenstattTest\Fuzz;

use SionModel\Form\Collection;
use SionModel\Form\ElementInterface;
use SionModel\Form\Fieldset;
use SionModel\Filter\Registry as FilterRegistry;
use SionModel\Form\InputFilterProviderInterface;
use SionModel\Validator\Explode;
use SionModel\Entity\Entity;
use SionModel\Form\Element;
use SionModel\Service\EntitiesService;
use Throwable;

/**
 * Turns every form in the application into a sorted list of validation gaps.
 *
 * Shared by `FormValidationContractTest` (which compares the lists against the
 * committed baseline) and `regenerate-baseline.php` (which writes them into it).
 * Keeping the two on one implementation is not tidiness: a regenerator that
 * computed gaps even slightly differently from the test would produce a baseline
 * that never quite matches, and the usual response to that is to stop trusting the
 * suite.
 *
 * ## The one mechanic every check below depends on
 *
 * **The specification is the whole of it.** {@see \SionModel\Form\Validation\InputFilter}
 * reads `getInputFilterSpecification()` and nothing else, so a field the spec does not
 * name is filtered by nothing and validated by nothing, whatever its element is.
 *
 * That was not true until iteration A. `Laminas\Form\Form::attachInputFilterDefaults()`
 * built an input per element first — from `InputProviderInterface::getInputSpecification()`,
 * which is where `Select` got its `InArray`, `Email` its `EmailAddress`, `Url` its `Uri`,
 * `Date` its `Date` and `Number` its `Step`/`GreaterThan`/`LessThan` — and then *merged*
 * the spec into it rather than replacing it. This file asserted the opposite until
 * 2026-08-15 and every choice-field finding it produced was computed wrong, which is why
 * `LibraryForm::mainCollectionId` sat in the baseline as an unconstrained gap while
 * rejecting `999` end-to-end. Measured 2026-09-10, 120 fields were validated *only* by
 * that element half; they were written into the specifications before the merge went away.
 *
 * Two consequences still drive the checks:
 *
 *  - `disable_inarray_validator => true` on a choice element is now the *statement* that
 *    the domain comes from the spec rather than the thing that removes it — 35 elements
 *    set it. The choice-field category asks about the option, and treats a spec-side
 *    `InArray` ({@see \SionModel\Form\ChoiceDomain}) as the domain.
 *  - A spec key naming no element still becomes an input and emits `null` into the
 *    values. So a typo is silent in both directions: the field it was meant to protect
 *    is naked, and a phantom key appears in the data.
 *
 * ## Scope: one class, its own elements
 *
 * Each discovered class is examined against its own elements and its own spec.
 * Elements contributed by a nested fieldset are checked when that fieldset class is
 * itself the subject — every fieldset in this codebase is its own discovered class,
 * so nothing goes unexamined, and this avoids having to model how {@see FormSpecification}
 * nests spec keys under fieldset names (where a mistake would manufacture false findings).
 *
 * ## Scope: construction-time state
 *
 * Forms are examined as constructed. `SionForm::prepareForSuggestion()` and
 * `prepareForModeration()` add elements and rewrite the spec at request time, and
 * those paths are not modelled — a limitation worth knowing rather than papering
 * over. The elements they add (`suggestionNotes`, `suggestionResponse`,
 * `suggestionByEmail`, …) do get covered for `SuggestForm`, whose factory calls
 * `prepareForSuggestion()` during construction.
 */
final class FormGapCollector
{
    /**
     * Element types that carry no user data and therefore need no validation.
     * Exempted by type, never by name: a name list would exempt a real field the
     * day someone called one `submit`, and would miss the button called `deny`.
     */
    private const NON_DATA_ELEMENT_TYPES = [
        Element\Submit::class,
        Element\Button::class,
        Element\Csrf::class,
    ];

    /**
     * Element types whose value is *not* free text, so a `StringLength` is the
     * wrong tool for them.
     *
     * The classification is stated as an exclusion list rather than an inclusion
     * list, and that is deliberate. Every element extends
     * `SionModel\Form\Element\Element`, so an inclusion list containing the base class
     * matches `Select`, `Checkbox` and `Number` too — the first version of this
     * file did exactly that and reported 249 "unbounded text fields", most of
     * them dropdowns. More importantly, an exclusion list fails *safe*: an element
     * type nobody here anticipated is treated as free text and demands a bound,
     * rather than slipping through unchecked. That is also why the types the element
     * model dropped — Radio, MultiCheckbox, Range, Time, Week, Month, the DateTime
     * family — are simply gone from these lists rather than kept as laminas class
     * names: nothing can be an instance of them any more, and a form that somehow
     * grew one would be reported rather than waved through.
     */
    private const NON_TEXT_ELEMENT_TYPES = [
        Element\Select::class,
        Element\Checkbox::class,
        Element\Number::class,
        Element\DateSelect::class,
        Element\Date::class,
        Element\File::class,
        Collection::class,
        Fieldset::class,
    ];

    /**
     * Element types whose value must come from a fixed set of options.
     *
     * `Select` alone. laminas' `Radio` and `MultiCheckbox` stood here too; the element
     * model has neither, because the census found none in any form on this site.
     */
    private const CHOICE_ELEMENT_TYPES = [
        Element\Select::class,
    ];

    /**
     * `attributes.type` values that mean "this is a button", used when the element
     * definition declares no PHP `type` at all.
     *
     * Several forms here write `['name' => 'submit', 'attributes' => ['type' =>
     * 'submit']]` with no `'type'` key, which produces a base
     * `SionModel\Form\Element\Element` — a browser button that the *server* treats as an
     * ordinary text input, present in `getData()` and validated by nothing. That is
     * a genuine (if usually harmless) hole, so these are reported in their own
     * category rather than either failing the main checks as unbounded text or
     * being waved through as buttons.
     */
    private const BUTTON_ATTRIBUTE_TYPES = ['submit', 'button', 'reset', 'image'];

    /**
     * Validators that constrain how long a value can be. `Regex` and `Between`
     * qualify because a pattern or a numeric range bounds the string too, and
     * demanding literally `StringLength` would make the harness reject correct
     * fixes. `InArray` bounds a value to the longest option, which is a bound.
     */
    private const LENGTH_BOUNDING_VALIDATORS = ['stringlength', 'regex', 'between', 'inarray', 'digits', 'barcode'];

    /** @var array<string, list<string>>|null */
    private ?array $gaps = null;

    /**
     * `Form class::element` => true, from test/Fuzz/open-ended-choice-fields.php.
     *
     * @var array<string, true>
     */
    private array $openEnded;

    /** @var array<string, true> declarations that matched a field this run */
    private array $openEndedSeen = [];

    public function __construct(private readonly FormRepository $repository)
    {
        /** @var list<string> $declared */
        $declared = require __DIR__ . '/open-ended-choice-fields.php';

        $this->openEnded = array_fill_keys($declared, true);
    }

    /**
     * Every gap category, each a sorted list of stable one-line strings.
     *
     * The strings are the baseline's unit of comparison, so they must not embed
     * anything that moves for reasons unrelated to the gap — no line numbers for
     * runtime findings, no exception messages, no counts.
     *
     * @return array<string, list<string>>
     */
    public function gaps(): array
    {
        if (null !== $this->gaps) {
            return $this->gaps;
        }

        $scanner = new ElementDefinitionScanner($this->repository->formSourceFiles());

        $gaps = [
            'deadElementKeys'            => $scanner->findings(),
            'unanalyzableAddCalls'       => $scanner->unanalyzableCalls(),
            'unconstructableForms'       => [],
            'choiceFieldsOpenByDesign'   => [],
            'openEndedDeclarationsStale' => [],
            'elementsMissingFromSpec'    => [],
            'specKeysWithoutElement'     => [],
            'unboundedTextFields'        => [],
            'choiceFieldsWithoutDomain'  => [],
            'boundsLooserThanColumn'     => [],
            'buttonsDeclaredOnlyByAttribute' => [],
        ];

        foreach ($this->repository->constructionFailures() as $class => $reason) {
            $gaps['unconstructableForms'][] = sprintf('%s could not be built', $class);
            unset($reason);
        }

        foreach ($this->repository->forms() as $class => $form) {
            $this->collectFor($class, $form, $gaps);
        }

        //A declaration that matched nothing this run. Either the field gained an InArray — in
        //which case leaving it declared open would hide the next regression on it — or the
        //entry names a form or element that no longer exists. Both are worth a failure: this
        //file is the one place where "unvalidated on purpose" is asserted, and an entry nobody
        //can trace back to a field is an assertion about nothing.
        foreach (array_keys($this->openEnded) as $declaration) {
            if (! isset($this->openEndedSeen[$declaration])) {
                $gaps['openEndedDeclarationsStale'][] = sprintf(
                    '%s is declared open-ended in %s but is not a choice field missing an InArray — '
                    . 'either it is constrained now, or the entry is a typo',
                    $declaration,
                    'test/Fuzz/open-ended-choice-fields.php'
                );
            }
        }

        foreach ($gaps as &$list) {
            sort($list);
            $list = array_values(array_unique($list));
        }

        return $this->gaps = $gaps;
    }

    /**
     * Fields whose column width could not be derived, and why. Reported in the
     * test's output rather than asserted on, so a field that silently escapes the
     * column check is visible instead of merely absent — the brief's requirement
     * that an underivable bound be a reported skip, not a quiet pass.
     *
     * @return list<string>
     */
    public function columnBoundSkips(): array
    {
        $this->gaps();

        return $this->skips;
    }

    /** @var list<string> */
    private array $skips = [];

    /**
     * @param array<string, list<string>> $gaps
     */
    private function collectFor(string $class, Fieldset $form, array &$gaps): void
    {
        $spec     = $this->specificationOf($form);
        $elements = $this->dataElements($form);
        $columns  = $this->columnWidthsFor($class);


        foreach (array_keys($spec) as $key) {
            if (! $form->has((string) $key)) {
                $gaps['specKeysWithoutElement'][] = sprintf(
                    '%s: input filter spec names %s, which is not an element on this form',
                    $class,
                    self::q((string) $key)
                );
            }
        }

        foreach ($elements as $name => $element) {
            $inSpec = array_key_exists($name, $spec);

            if (self::isButtonByAttributeOnly($element)) {
                $gaps['buttonsDeclaredOnlyByAttribute'][] = sprintf(
                    '%s: %s declares its button-ness only in attributes.type, so the server sees a plain '
                    . 'text input',
                    $class,
                    self::q($name)
                );
                continue;
            }

            if (! $inSpec) {
                //Unconditional now. This used to ask whether the element implemented
                //`Laminas\InputFilter\InputProviderInterface`, because such an element
                //contributed its own validators and a field missing from the spec was
                //still checked. Nothing does: the interface left with the package, and
                //what those elements contributed is written out in the specifications —
                //so a field the spec does not name is validated by nothing at all.
                $gaps['elementsMissingFromSpec'][] = sprintf(
                    '%s: %s is not named in the input filter spec '
                    . '(PASS-THROUGH: no filter, no validator, no length bound)',
                    $class,
                    self::q($name)
                );
            }

            $validators = $inSpec ? self::validatorNames($spec[$name]) : [];
            $isTextish  = ! self::isOneOf($element, self::NON_TEXT_ELEMENT_TYPES);
            $isChoice   = self::isOneOf($element, self::CHOICE_ELEMENT_TYPES);

            //`disable_inarray_validator` is the question, not the spec — see the mechanics
            //note at the top of this file. An element that has not disabled its own InArray
            //keeps it through the merge, whatever the spec says.
            $domainDisabled = $isChoice && (bool) $element->getOption('disable_inarray_validator');

            if ($domainDisabled && ! in_array('inarray', $validators, true)) {
                //Two opposite fixes wear this one description, so they get two categories.
                //A field the view lets a moderator type into has no domain to enforce, and
                //adding an InArray to it removes a feature rather than closing a hole —
                //test/Fuzz/open-ended-choice-fields.php is where that is decided and why.
                $declaration = $class . '::' . $name;
                $category    = isset($this->openEnded[$declaration])
                    ? 'choiceFieldsOpenByDesign'
                    : 'choiceFieldsWithoutDomain';

                $this->openEndedSeen[$declaration] = true;

                $gaps[$category][] = sprintf(
                    'choiceFieldsOpenByDesign' === $category
                        ? '%s: %s is a %s whose options are a suggestion, not a domain — declared open'
                        : '%s: %s is a %s with disable_inarray_validator and no InArray in the spec, '
                            . 'so nothing constrains it to its option list',
                    $class,
                    self::q($name),
                    self::shortType($element)
                );
            }

            $bounded = self::declaredMaxLength($inSpec ? $spec[$name] : null);

            if ($isTextish && null === $bounded && ! self::hasBoundingValidator($validators)) {
                $gaps['unboundedTextFields'][] = sprintf(
                    '%s: %s (%s) has no length bound',
                    $class,
                    self::q($name),
                    self::shortType($element)
                );
            }

            // (e) compare against the column the value lands in.
            if (! isset($columns[$name])) {
                continue;
            }

            [$table, $column, $width] = $columns[$name];

            if (! SchemaColumnWidths::knowsColumn($table, $column)) {
                $this->skips[] = sprintf(
                    'SKIP %s: %s -> %s.%s — column never appears in database/*.sql, so no bound can be '
                    . 'derived and this field escapes the column check',
                    $class,
                    self::q($name),
                    $table,
                    $column
                );
                continue;
            }

            if (null === $width) {
                // The column exists and its type carries no character bound
                // (INT, DATE, BIT). Not a skip: the check does not apply.
                continue;
            }

            if (null === $bounded) {
                // Already reported as unbounded above for text-ish fields; for the
                // rest (Select, Number, Date) the element's own validation is what
                // stands, and there is no max to compare.
                continue;
            }

            if ($bounded > $width) {
                $gaps['boundsLooserThanColumn'][] = sprintf(
                    '%s: %s allows %d characters but %s.%s holds %d',
                    $class,
                    self::q($name),
                    $bounded,
                    $table,
                    $column,
                    $width
                );
            }
        }
    }

    // ------------------------------------------------------------ introspection

    /**
     * The form's own input filter specification, or an empty array when it does not
     * provide one. Not read off a built InputFilter on purpose: the built filter has
     * already merged the element-provided inputs, and every check here turns on the
     * difference between the two.
     *
     * @return array<string, mixed>
     */
    public function specificationOf(Fieldset $form): array
    {
        if (! $form instanceof InputFilterProviderInterface) {
            return [];
        }

        $spec = $this->repository->quietly(static function () use ($form) {
            try {
                return $form->getInputFilterSpecification();
            } catch (Throwable) {
                return [];
            }
        });

        return is_array($spec) ? $spec : [];
    }

    /**
     * The form's own elements, minus buttons and CSRF, keyed by name. Nested
     * fieldsets are not descended into — see the class docblock.
     *
     * @return array<string, ElementInterface>
     */

    /**
     * The classes a specification entry's `filters` resolve to, keyed by class name.
     *
     * @return array<class-string, true>
     */
    private function declaredFilterClasses(mixed $specEntry): array
    {
        if (! is_array($specEntry)) {
            return [];
        }

        $classes = [];
        foreach ((array) ($specEntry['filters'] ?? []) as $filter) {
            if (! is_array($filter) || ! is_string($filter['name'] ?? null)) {
                continue;
            }
            try {
                /** @var object $instance */
                $instance = FilterRegistry::get($filter['name']);
            } catch (Throwable) {
                //A name nothing can build is a different finding, and `throwingInputs`
                //already reports the form it breaks. Skipping it here would hide the
                //element-supplied filter beside it, so the name is treated as declaring
                //nothing and the comparison stays conservative.
                continue;
            }
            $classes[$instance::class] = true;
        }

        return $classes;
    }

    public function dataElements(Fieldset $form): array
    {
        $elements = [];

        foreach ($form->getElements() as $element) {
            if (self::isOneOf($element, self::NON_DATA_ELEMENT_TYPES)) {
                continue;
            }
            $elements[(string) $element->getName()] = $element;
        }

        ksort($elements);

        return $elements;
    }

    /**
     * @return list<string> lowercased short validator names in a spec entry
     *
     * A validator **wrapped in `Explode` counts as itself**, and that is not a convenience:
     * `Explode` is how laminas applies a scalar validator to a multiple select's array, and
     * `Laminas\Form\Element\Select::getInputSpecification()` wrapped its own `InArray` in one for
     * exactly that reason. Without this, a multiple select constrained correctly reads as
     * unconstrained forever — measured 2026-08-15 on `DictionaryEntryForm::links` and
     * `PublicationForm::inLanguage`, which stayed in the baseline after being fixed.
     */
    public static function validatorNames(mixed $specEntry): array
    {
        if (! is_array($specEntry)) {
            return [];
        }

        $names = [];
        foreach ((array) ($specEntry['validators'] ?? []) as $validator) {
            foreach (self::namesOf($validator) as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * One validator's name, plus the name of whatever it wraps.
     *
     * @return list<string>
     */
    private static function namesOf(mixed $validator): array
    {
        if (is_object($validator)) {
            $names = [self::shortName($validator::class)];

            //An Explode instance holds its inner validator, and that inner one is the domain
            //check a reader is looking for.
            if ($validator instanceof Explode) {
                $inner = $validator->getValidator();
                if (null !== $inner) {
                    $names[] = self::shortName($inner::class);
                }
            }

            return $names;
        }

        if (is_string($validator)) {
            return [self::shortName($validator)];
        }

        if (! is_array($validator) || ! isset($validator['name']) || ! is_string($validator['name'])) {
            return [];
        }

        $names = [self::shortName($validator['name'])];

        /** @var mixed $inner */
        $inner = $validator['options']['validator'] ?? null;
        if (is_object($inner)) {
            $names[] = self::shortName($inner::class);
        } elseif (is_string($inner)) {
            $names[] = self::shortName($inner);
        } elseif (is_array($inner) && isset($inner['name']) && is_string($inner['name'])) {
            $names[] = self::shortName($inner['name']);
        }

        return $names;
    }

    /**
     * The tightest `max` the spec declares for a field, in characters, or null when
     * it declares none.
     *
     * Only `StringLength` is read for a number, because it is the only validator in
     * use whose `max` is unambiguously a character count. A `Regex` bounds length in
     * practice but not in a way that can be compared with an integer, so it counts
     * as "bounded" for check (d) and as "no comparable max" for check (e).
     */
    public static function declaredMaxLength(mixed $specEntry): ?int
    {
        if (! is_array($specEntry)) {
            return null;
        }

        $max = null;
        foreach ((array) ($specEntry['validators'] ?? []) as $validator) {
            if (is_object($validator) && $validator instanceof \SionModel\Validator\StringLength) {
                $candidate = $validator->getMax();
            } elseif (
                is_array($validator)
                && isset($validator['name'])
                && is_string($validator['name'])
                && 'stringlength' === self::shortName($validator['name'])
            ) {
                $candidate = $validator['options']['max'] ?? null;
            } else {
                continue;
            }

            if (null !== $candidate && is_numeric($candidate)) {
                $max = null === $max ? (int) $candidate : min($max, (int) $candidate);
            }
        }

        return $max;
    }

    /** @param list<string> $validators */
    private static function hasBoundingValidator(array $validators): bool
    {
        return [] !== array_intersect($validators, self::LENGTH_BOUNDING_VALIDATORS);
    }

    // ---------------------------------------------------------- column mapping

    /**
     * For one form class, the column each of its fields is written into.
     *
     * The chain is the application's own: a `sion_model` entity names its form in
     * `editActionForm`/`createActionForm`/`suggestForm`, its table in `tableName`,
     * and its field-to-column whitelist in `updateColumns` — which is the same
     * whitelist `SionTable::updateHelper()` filters writes through, so a field
     * absent from it never reaches the database at all and is correctly ignored
     * here.
     *
     * A form shared by two entities must satisfy both, so the *narrowest* width
     * across them wins.
     *
     * @return array<string, array{0: string, 1: string, 2: int|null}> field => [table, column, width]
     */
    private function columnWidthsFor(string $formClass): array
    {
        $mapping = [];

        foreach ($this->entitiesUsing($formClass) as $entity) {
            $table = (string) $entity->tableName;
            if ('' === $table) {
                continue;
            }

            foreach ((array) $entity->updateColumns as $field => $column) {
                if (! is_string($field) || ! is_string($column)) {
                    continue;
                }

                if (! SchemaColumnWidths::knowsTable($table)) {
                    $this->skips[] = sprintf(
                        'SKIP %s: %s -> %s.%s — table %s is never created in database/*.sql '
                        . '(it exists only in the production dump)',
                        $formClass,
                        self::q($field),
                        $table,
                        $column,
                        $table
                    );
                    continue;
                }

                $width = SchemaColumnWidths::widthOf($table, $column);

                if (! isset($mapping[$field]) || (null !== $width && ($mapping[$field][2] ?? null) === null)) {
                    $mapping[$field] = [$table, $column, $width];
                    continue;
                }
                if (null !== $width && null !== $mapping[$field][2] && $width < $mapping[$field][2]) {
                    $mapping[$field] = [$table, $column, $width];
                }
            }
        }

        return $mapping;
    }

    /**
     * The entity specs that point at a given form class.
     *
     * @return list<Entity>
     */
    private function entitiesUsing(string $formClass): array
    {
        $matches = [];

        foreach ($this->entities() as $entity) {
            foreach ([$entity->editActionForm, $entity->createActionForm, $entity->suggestForm] as $named) {
                if (is_string($named) && ltrim($named, '\\') === ltrim($formClass, '\\')) {
                    $matches[] = $entity;
                    continue 2;
                }
            }
        }

        return $matches;
    }

    /** @var array<string, Entity>|null */
    private ?array $entities = null;

    /** @return array<string, Entity> */
    private function entities(): array
    {
        if (null !== $this->entities) {
            return $this->entities;
        }

        $container = $this->repository->container();

        $entities = $this->repository->quietly(static function () use ($container): array {
            try {
                return $container->get(EntitiesService::class)->getEntities();
            } catch (Throwable) {
                return [];
            }
        });

        return $this->entities = is_array($entities) ? $entities : [];
    }

    // ----------------------------------------------------------------- helpers

    /**
     * True for an element that is a plain `SionModel\Form\Element\Element` whose only claim
     * to being a button is `attributes.type`. See BUTTON_ATTRIBUTE_TYPES.
     */
    public static function isButtonByAttributeOnly(ElementInterface $element): bool
    {
        if ($element::class !== Element\Element::class) {
            return false;
        }

        $type = $element->getAttribute('type');

        return is_string($type) && in_array(strtolower($type), self::BUTTON_ATTRIBUTE_TYPES, true);
    }

    /** @param list<class-string> $types */
    private static function isOneOf(ElementInterface $element, array $types): bool
    {
        foreach ($types as $type) {
            if ($element instanceof $type) {
                return true;
            }
        }

        return false;
    }

    public static function shortType(ElementInterface $element): string
    {
        return self::shortName($element::class);
    }

    private static function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return strtolower(false === $position ? $class : substr($class, $position + 1));
    }

    /** Quote a field name so an empty or whitespace name is still visible. */
    private static function q(string $name): string
    {
        return "'" . $name . "'";
    }
}

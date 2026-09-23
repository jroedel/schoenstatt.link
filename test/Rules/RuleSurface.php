<?php

declare(strict_types=1);

namespace SchoenstattTest\Rules;

use Books\Validator\UniqueBarcodeInLibrary;
use DateTimeImmutable;
use DateTimeInterface;
use SionModel\Db\Connection;
use SionModel\Validator\AbstractValidator;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Form\Validation\InputFilter;
use Throwable;

use function abs;
use function error_reporting;
use function get_debug_type;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function ksort;
use function method_exists;
use function restore_error_handler;
use function set_error_handler;
use function sha1;
use function sprintf;
use function strlen;
use function mb_check_encoding;
use function ord;
use function preg_replace_callback;
use function substr;
use function var_export;

require_once __DIR__ . '/RuleCases.php';
require_once __DIR__ . '/../Fuzz/FormRepository.php';

/**
 * What every filter and validator answers, recorded through the resolver production uses.
 *
 * ## Why each rule is driven through the engine rather than constructed
 *
 * `new SionModel\Filter\StripTags()` would measure laminas. What matters is what happens when
 * a **specification names a rule**, because that — a string and an options array — is the
 * whole of what a form declares, and the resolver in the middle is the piece this iteration
 * replaces. So each case is turned into a one-field specification and run through a real
 * {@see InputFilter}, which is the path a submitted form takes.
 *
 * The field is declared `required => false, continue_if_empty => true`, and that pair is
 * chosen rather than convenient: it is the only combination under which the engine runs the
 * rule on **every** value. `continue_if_empty` stops the empty-value shortcut (`''`, `null`
 * and `[]` would otherwise never reach a validator) and it also suppresses the injected
 * `NotEmpty`, which would otherwise answer for the rule under test. What is recorded is
 * therefore one rule's answer and nothing else's.
 *
 * ## What is recorded
 *
 * For a filter, the value it returns — which is the value that reaches
 * `SionTable::updateEntity()` and the database. For a validator, the verdict and the
 * messages, whose text is the other half of the contract: a visitor reads it, and the
 * `default` text domain translates it, so a message that changes wording changes five
 * catalogs.
 *
 * PHP diagnostics are recorded too. A rule that starts emitting a deprecation, or stops,
 * is a finding — and on a value like `"a\xC3"` the diagnostic is often the only difference
 * between two implementations that both return the input unchanged.
 *
 * ## One line per answer, and why the type is spelled out
 *
 * Each answer is a single string rather than a nested array. `var_export` of the nested
 * form is 894 KB across 38,719 lines for the same information, which is a diff nobody
 * reads — and the whole worth of a recording is that its diff is read.
 *
 * The type is written out because it is the finding more often than the value is:
 * `ToNull` differs from every other filter precisely in returning `null` where the input
 * was `''`, and `"" `, `null` and `0` all render as nothing at all when concatenated. So
 * `null`, `string ""` and `int 0` are three distinct answers here and look it.
 *
 * ## The messages are recorded untranslated, and that is not a simplification
 *
 * `AbstractValidator::setDefaultTranslator()` is **process-global static state**, and
 * `App\Laminas\TranslatorConfigurator` sets it. So in a suite that has built a translator
 * and a test that has switched locale, `NotEmpty` answers "Valor es requerido" — which is
 * what this file recorded on its first run, and what it would have recorded on some runs
 * and not others forever after, decided by test order.
 *
 * The source text is the contract a replacement has to meet; translating it is JTranslate's
 * job and is measured where JTranslate is. So the default translator is taken away for the
 * length of the collection and put back afterwards.
 */
final class RuleSurface
{
    /** Longer than this and a value is recorded as its length and digest. */
    private const INLINE = 120;

    /** Within this many seconds of the recording's own clock, a time is `<clock>`. */
    private const CLOCK_TOLERANCE = 300;

    /** The instant the recording was taken; every produced moment is relative to it. */
    private static ?DateTimeImmutable $reference = null;

    /**
     * `'filter'|'validator'` => case label => input label => answer, plus the SQL the two
     * database validators build.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function collect(): array
    {
        $surface = [
            'filter'    => [],
            'validator' => [],
        ];

        $inputs = RuleCases::inputs();

        $translator = AbstractValidator::getDefaultTranslator();
        AbstractValidator::setDefaultTranslator(null);

        //PHPUnit and the regeneration script start from different `error_reporting()`
        //settings, and the handler below consults it to detect `@`. Pinning it here is what
        //makes the file the test compares against the file the script wrote.
        $reporting = error_reporting(E_ALL);

        try {
            foreach (RuleCases::filters() as $label => $case) {
                $surface['filter'][$label] = self::answersFor('filters', $case, $inputs);
            }

            foreach (RuleCases::validators() as $label => $case) {
                $surface['validator'][$label] = self::answersFor('validators', $case, $inputs);
            }
        } finally {
            AbstractValidator::setDefaultTranslator($translator);
            error_reporting($reporting);
        }

        $surface['database-query'] = self::databaseQueries();

        ksort($surface['filter']);
        ksort($surface['validator']);

        return $surface;
    }

    /**
     * One rule, over the whole corpus.
     *
     * @param 'filters'|'validators' $kind
     * @param array{name: string, options: array<string, mixed>} $case
     * @param array<string, mixed> $inputs
     * @return array<string, mixed>
     */
    private static function answersFor(string $kind, array $case, array $inputs): array
    {
        $spec = [
            'value' => [
                'required'          => false,
                'continue_if_empty' => true,
                $kind               => [$case],
            ],
        ];

        $answers = [];

        foreach ($inputs as $inputLabel => $value) {
            $answers[$inputLabel] = self::answerFor($kind, $spec, $value);
        }

        return $answers;
    }

    /**
     * @param 'filters'|'validators' $kind
     * @param array<string, mixed> $spec
     */
    private static function answerFor(string $kind, array $spec, mixed $value): string
    {
        $diagnostics = [];

        set_error_handler(static function (int $severity, string $message) use (&$diagnostics): bool {
            //`@` is honoured, which any correct error handler does and which this one did
            //not: a validator that compiles its pattern behind `@preg_match()` and reports
            //a bad one as an exception was having the suppressed warning recorded as if a
            //caller could see it. `collect()` forces `E_ALL` first, so this tests the
            //suppression and not the ambient setting — the regeneration script and PHPUnit
            //do not agree about the latter.
            if (0 === (error_reporting() & $severity)) {
                return true;
            }

            //Deprecations are not recorded. Every one seen here came from a **compile-time**
            //check in a third-party file — an implicitly nullable parameter in spatie or
            //parsedown — which PHP raises when it compiles the file and never again, so
            //whether it appears depends on what OPcache already holds. A recording that
            //changes with the state of a cache is not an oracle.
            if (E_DEPRECATED === $severity || E_USER_DEPRECATED === $severity) {
                return true;
            }

            $diagnostics[] = sprintf('%s: %s', self::severityName($severity), $message);

            return true;
        });

        try {
            $filter = InputFilter::withRules($spec);
            $filter->setData(['value' => $value]);
            $valid = $filter->isValid();

            if ('filters' === $kind) {
                $answer = self::describe($filter->getValues()['value'] ?? null);
            } else {
                $messages = $filter->getMessages()['value'] ?? [];
                $answer   = $valid ? 'valid' : 'invalid — ' . self::describeMessages($messages);
            }
        } catch (Throwable $e) {
            //A rule that throws is a real answer — `SionModel\Validator\GpsPoint` raises a
            //TypeError on a non-string, and `SionModel\Filter\ToDateTime` lets DateTime's
            //own exception out. Recording the class and the message is what makes a
            //replacement that throws something else, or nothing, visible.
            $answer = 'threw ' . $e::class . ': ' . self::printable(self::shorten($e->getMessage()));
        } finally {
            restore_error_handler();
        }

        foreach ($diagnostics as $diagnostic) {
            $answer .= ' [' . $diagnostic . ']';
        }

        return $answer;
    }

    /**
     * @param mixed $messages message key => message, as a failing validator returns them
     */
    private static function describeMessages(mixed $messages): string
    {
        if (! is_array($messages)) {
            return self::describe($messages);
        }

        $rendered = [];
        /** @var mixed $message */
        foreach ($messages as $key => $message) {
            $rendered[] = $key . ': '
                . (is_string($message) ? self::printable(self::shorten($message)) : self::describe($message));
        }

        return [] === $rendered ? '(no message)' : implode(' | ', $rendered);
    }

    /**
     * One value, as one line: its type and what it holds.
     */
    private static function describe(mixed $value): string
    {
        /** @var mixed $value */
        $value = self::normalise($value);

        if (null === $value) {
            return 'null';
        }

        if (is_bool($value)) {
            return 'bool ' . ($value ? 'true' : 'false');
        }

        if (is_int($value)) {
            return 'int ' . $value;
        }

        if (is_float($value)) {
            return 'float ' . var_export($value, true);
        }

        if (is_string($value)) {
            return 'string ' . self::quote($value);
        }

        if (is_array($value)) {
            $parts = [];
            /** @var mixed $item */
            foreach ($value as $key => $item) {
                $parts[] = $key . ': ' . self::describe($item);
            }

            return 'array {' . implode(', ', $parts) . '}';
        }

        return get_debug_type($value);
    }

    /**
     * The SQL `Db\RecordExists` and `Db\NoRecordExists` build, without asking the database.
     *
     * Their verdict is a fact about the capsule's rows rather than about the rule, and
     * `test/Form/engine-surface.php` already records both messages through the three forms
     * that use them. What is worth pinning here is the query, because that is what a
     * replacement gets wrong quietly: a missing schema qualifier or a changed `WHERE` reads
     * as "record not found" and the form simply refuses a valid entry.
     *
     * The adapter is the application's, because MySQL's platform quotes through the live
     * connection; no query is executed, so what is recorded is a fact about the rule and
     * not about the rows.
     *
     * @return array<string, string>
     */
    private static function databaseQueries(): array
    {
        /** @var Connection $adapter */
        $adapter = FormRepository::instance()->container()->get(Connection::class);

        $queries = [];

        foreach (
            [
                'NoRecordExists' => \SionModel\Validator\Db\NoRecordExists::class,
                'RecordExists'   => \SionModel\Validator\Db\RecordExists::class,
            ] as $label => $class
        ) {
            $validator = new $class([
                'table'   => 'sch_persons',
                'field'   => 'PersonId',
                'adapter' => $adapter,
            ]);

            //The statement, not one interpolation of it: what the rule asks the database is
            //the query, and the value it compares against is whatever the form submitted.
            $queries[$label] = $validator->getSelect()->render()[0];
        }

        //Books' own uniqueness rule, which asks a three-term question the two above cannot
        //express. Both forms of it are recorded: a create compares against every book in
        //the library, an edit excludes the row it is editing, and dropping that third term
        //would refuse every save that left the barcode alone.
        $barcode = new UniqueBarcodeInLibrary([
            'adapter'   => $adapter,
            'libraryId' => 1,
        ]);
        $queries['UniqueBarcodeInLibrary (create)'] = $barcode->getSelect()->render()[0];

        $barcode->setExcludeBookId(1);
        $queries['UniqueBarcodeInLibrary (edit)'] = $barcode->getSelect()->render()[0];

        return $queries;
    }

    /**
     * Values as they can be compared between two runs.
     *
     * An object becomes its class and, for the two kinds a filter actually produces, its
     * value — a `DateTime` from `ToDateTime` and a `GeoPoint` from `ToGeoPoint` are answers,
     * and their identity is not stable across runs.
     */
    private static function normalise(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            /** @var mixed $item */
            foreach ($value as $key => $item) {
                $out[$key] = self::normalise($item);
            }

            return $out;
        }

        if ($value instanceof DateTimeInterface) {
            return '<DateTime ' . self::describeMoment($value) . '>';
        }

        if (is_object($value)) {
            return method_exists($value, '__toString')
                ? '<' . $value::class . ' ' . self::shorten((string) $value) . '>'
                : '<' . $value::class . '>';
        }

        if (is_string($value)) {
            return self::shorten($value);
        }

        if (null === $value || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        return '<' . get_debug_type($value) . '>';
    }

    /**
     * A moment, expressed so that two runs of the same code agree.
     *
     * `SionModel\Filter\ToDateTime` is `new DateTime($value)`, and PHP fills in whatever
     * the value leaves out **from the clock**: `'1799'` is the year 1799 on today's month
     * and day at this second, `'tomorrow'` is midnight tomorrow, `'+500 years'` is five
     * centuries from this instant. Recorded literally, a third of the date corpus would
     * disagree with itself between the regeneration and the next test run — which is a
     * recording that has to be regenerated to pass, which is no recording at all.
     *
     * So the date is written relative to the day the recording was taken, and the
     * time-of-day as `<clock>` when it is this instant. Both halves are compared against
     * the **same** shifted reference, so nothing moves when a year rolls over between the
     * two: `'tomorrow'` on 31 December is `today+1 day, year+0`, not `year+1`.
     *
     * A moment that is nobody's idea of near today — `'@99999999999'`, `'999999-01-01'` —
     * is absolute already and is written out.
     */
    private static function describeMoment(DateTimeInterface $moment): string
    {
        $now = self::$reference ??= new DateTimeImmutable();

        $date = $moment->format('Y-m-d');

        foreach ([0, 1, 2, -1, -2] as $days) {
            $reference = $now->modify(sprintf('%+d days', $days));
            if ($moment->format('m-d') !== $reference->format('m-d')) {
                continue;
            }

            $date = sprintf(
                'today%+d days, year%+d',
                $days,
                (int) $moment->format('Y') - (int) $reference->format('Y')
            );
            break;
        }

        $secondsIntoDay = static fn(DateTimeInterface $at): int
            => (int) $at->format('H') * 3600 + (int) $at->format('i') * 60 + (int) $at->format('s');

        $time = abs($secondsIntoDay($moment) - $secondsIntoDay($now)) <= self::CLOCK_TOLERANCE
            ? '<clock>'
            : $moment->format('H:i:s');

        return $date . ' ' . $time . ' ' . $moment->format('P');
    }

    /**
     * A long value as its length and digest.
     *
     * The 100 KB corpus entry is there to say whether a filter truncates it, not to be
     * stored; a recording that carried it would be a megabyte of one letter.
     */
    private static function shorten(string $value): string
    {
        if (strlen($value) <= self::INLINE) {
            return $value;
        }

        return sprintf('<len=%d sha1=%s>', strlen($value), substr(sha1($value), 0, 12));
    }

    /**
     * A string with its whitespace and its unprintable bytes visible.
     *
     * A recording whose lines end in a trailing space is a recording whose diff lies about
     * what changed, and half this corpus exists to ask what a filter does with whitespace.
     *
     * The escaping is not decoration either. Three corpus entries are **invalid UTF-8** on
     * purpose, and a filter that passes them through puts those bytes in the generated
     * file — where `git diff`, `phpcs` and every editor disagree about what they say, and
     * the recording stops being readable at all. So a string that is valid UTF-8 keeps its
     * accents and its emoji, and one that is not has every byte above ASCII written out.
     */
    private static function quote(string $value): string
    {
        return '"' . self::printable($value) . '"';
    }

    /**
     * Every byte a text file cannot carry, written out.
     *
     * Applied to a validator's **message** as well as to a filter's value, which is not
     * obvious and cost one regeneration to learn: a message template substitutes `%value%`,
     * so a validator handed `"a\x80\x81b"` reports it verbatim and puts those bytes in the
     * recording just as surely as a filter that returns them.
     */
    private static function printable(string $value): string
    {
        $pattern = mb_check_encoding($value, 'UTF-8')
            ? '/[\x00-\x1F\x7F]/'
            : '/[\x00-\x1F\x7F-\xFF]/';

        return preg_replace_callback(
            $pattern,
            static fn(array $match): string => match ($match[0]) {
                "\n"    => '\\n',
                "\r"    => '\\r',
                "\t"    => '\\t',
                default => sprintf('\\x%02X', ord($match[0])),
            },
            $value
        ) ?? $value;
    }

    private static function severityName(int $severity): string
    {
        return match ($severity) {
            E_WARNING, E_USER_WARNING         => 'warning',
            E_NOTICE, E_USER_NOTICE           => 'notice',
            E_DEPRECATED, E_USER_DEPRECATED   => 'deprecated',
            E_USER_ERROR                      => 'error',
            default                           => 'severity-' . $severity,
        };
    }
}

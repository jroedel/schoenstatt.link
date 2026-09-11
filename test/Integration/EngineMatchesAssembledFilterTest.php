<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\Filter\FilterPluginManager;
use Laminas\Form\Form;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorPluginManager;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Form\Validation\InputFilter as Engine;
use Throwable;

use function is_array;
use function preg_match;
use function sprintf;
use function implode;
use function method_exists;
use function var_export;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Fuzz/FormRepository.php';

/**
 * Where a form's specification claims to be complete, the replacement engine answers what
 * laminas answers.
 *
 * ## Why this exists alongside InputFilterEngineParityTest
 *
 * That test feeds `Laminas\InputFilter\Factory` and {@see Engine} a specification written
 * for the occasion. This one feeds them every specification the application actually
 * declares — all 41 forms — through `Form::getInputFilter()`, the object each of those
 * forms validated with before the cutover.
 *
 * It was written for a difference that no longer exists, and the history is worth keeping
 * because the same trap is available to anyone who narrows an input before comparing:
 * `getInputFilter()` also merged an input built by each **element**, and on 2026-09-10
 * **101 fields were validated only by that element half**, 31 of them by `Csrf`. A parity
 * harness fed from the narrowed specification agreed with itself and reported nothing.
 * Since the element swap (2026-09-11) no `SionModel\Form\Element\*` implements
 * `InputProviderInterface`, so that half is empty and the assembled filter is built from
 * the declared specification alone.
 *
 * What remains is two implementations over the real specifications, seven probes each:
 * 2,184 comparisons, none of them synthetic. The rules that used to arrive from the
 * elements are restated as plain data by `ChoiceDomain`, `CheckboxDomain`,
 * {@see \SionModel\Form\InputTypeRules} and `CsrfSpec`, and it is
 * {@see WholeFormEngineParityTest} that compares *those* — it drives
 * `FormSpecification::of()`, which carries them, against the same assembled filter.
 *
 * ## The skip list, now empty
 *
 * Entries of `validationSuppliedOnlyByElement` in `test/Fuzz/known-form-gaps.php` are
 * skipped here: a field whose specification was known to be incomplete could not be
 * compared honestly. That list read 3 before the element swap and reads **0** after it,
 * which is why this file now compares every field of every form and why the mechanism is
 * still wired up — a new gap would appear in the baseline and drop out of here in the
 * same run, rather than turning this test red for a reason it cannot explain.
 *
 * ## What the comparison is worth
 *
 * More than it looks. `Books\Form\TextForm::isDraft` and thirty-odd like it carry
 * `SionModel\Filter\ToBit`, which normalises anything to 0 or 1 *before* a validator sees
 * it — so their `InArray` can never fail and restating it changes no verdict at all. That
 * is a fine reason to be confident and a bad reason to skip the check: `isLifeCommunity`
 * has no such filter, and the fields differ one from the next. Measuring beats reasoning
 * about which is which forty times.
 */
final class EngineMatchesAssembledFilterTest extends TestCase
{
    /**
     * Strings and `null`, and deliberately nothing else — the shapes `$_POST` can actually
     * hold, which is the same choice `test/Fuzz/HostileInputCorpus` already makes. Probing
     * with a PHP `true` finds disagreements about a value no request can send, and every
     * one of them would be a distraction dressed as a finding.
     *
     * Within that, the values are chosen to separate a real domain check from a filter that
     * swallows everything: both checkbox values, a stranger, an out-of-domain number, markup,
     * empty and absent.
     */
    private const PROBES = ['1', '0', 'wat', '2', '<script>', '', null];

    /**
     * A floor on comparisons, not on forms. Every assertion here is inside two nested
     * loops over discovered sets, so a repository that found nothing — or a skip rule that
     * grew to cover everything — would pass silently.
     *
     * It rose as `validationSuppliedOnlyByElement` shrank, because a field leaving that
     * list started being compared here. 250 when the checkboxes closed; 2,149 once the
     * selects, URLs, dates, numbers and emails followed; **2,184** with the list empty. The
     * floor sits below that and above the previous step, so it catches a collapse without
     * pinning an exact number that every batch would have to edit.
     */
    private const COMPARISON_FLOOR = 1800;

    /**
     * The fields where the engine and the assembled filter genuinely differ, each with the
     * reason it is not a defect in the engine. **Empty, and kept.**
     *
     * There were three, and each left for its own reason rather than by being excused:
     *
     *   - `Books\Form\BookForm::callNumber` on 2026-09-11, when the per-library requirement
     *     moved out of a patch on the built filter and into the specification where the
     *     engine can read it — see `SchoenstattTest\Integration\CallNumberRequirementTest`.
     *   - `Books\Form\SearchForm::collectionId` and `Books\Form\ImportForm::file` with the
     *     element swap. Both were properties of a laminas element rather than of the code:
     *     `Select` carried its own `InArray`, which in this harness had an empty haystack
     *     because the controller populates the options per request; and `File` typed its
     *     input as `Laminas\InputFilter\FileInput`, which injects an upload check at
     *     `isValid()` time. `SionModel\Form\Element\*` does neither — the domain is stated
     *     by `ChoiceDomain`, the upload judged by `App\Books\Import\SpreadsheetUpload` —
     *     so the two sides now agree on both fields with nothing skipped.
     *
     * Skipping is not the same as excusing: an entry here has to say what the difference is
     * and why it is acceptable.
     *
     * @var array<string, string>
     */
    private const KNOWN_DIFFERENCES = [];

    /**
     * `security` is excluded, and only `security`.
     *
     * `Laminas\Validator\Csrf` reads a session container and regenerates a token; driving
     * it from a data provider compares session bookkeeping rather than validation rules.
     * It is covered where it means something — `AssociationValidationParityTest` builds the
     * form with it, and the smoke suite signs in and posts real tokens through real forms.
     */
    private const SESSION_BOUND = 'security';

    public function testTheEngineAgreesOnEveryFieldTheSpecificationClaims(): void
    {
        $skip        = self::fieldsKnownToLeanOnTheirElements();
        $disagreed   = [];
        $comparisons = 0;

        foreach (FormRepository::instance()->forms() as $class => $form) {
            if (! $form instanceof Form) {
                continue;
            }

            $spec = self::specificationOf($form);
            if ([] === $spec) {
                continue;
            }

            try {
                $assembled = $form->getInputFilter();
            } catch (Throwable) {
                //Reported by the fuzz harness' throwingInputs, not this file's business.
                continue;
            }

            foreach ($spec as $name => $rules) {
                $name = (string) $name;
                if (self::SESSION_BOUND === $name || ! is_array($rules)) {
                    continue;
                }
                $qualified = $class . '::' . $name;
                if (isset($skip[$qualified]) || isset(self::KNOWN_DIFFERENCES[$qualified])) {
                    continue;
                }
                if (! $assembled->has($name)) {
                    continue;
                }

                foreach (self::PROBES as $probe) {
                    $input = $assembled->get($name);
                    $input->setValue($probe);
                    $laminas = $input->isValid();

                    $engine = new Engine([$name => $rules], self::filterFactory(), self::validatorFactory());
                    $engine->setData([$name => $probe]);
                    $ours = $engine->isValid();

                    $comparisons++;
                    if ($laminas === $ours) {
                        continue;
                    }

                    $disagreed[] = sprintf(
                        '%s::%s with %s — laminas says %s, the engine says %s',
                        $class,
                        $name,
                        var_export($probe, true),
                        var_export($laminas, true),
                        var_export($ours, true)
                    );
                }
            }
        }

        self::assertGreaterThanOrEqual(
            self::COMPARISON_FLOOR,
            $comparisons,
            'almost nothing was compared, so this test proves nothing'
        );

        self::assertSame(
            [],
            $disagreed,
            "The specification-driven engine disagreed with the filter the application validates with "
            . "today. Each line is a field whose specification claims to be complete and is not:\n  "
            . implode("\n  ", $disagreed)
        );
    }

    /**
     * `Class::field` for every entry of the baseline's `validationSuppliedOnlyByElement`.
     *
     * @return array<string, true>
     */
    private static function fieldsKnownToLeanOnTheirElements(): array
    {
        /** @var array<string, list<string>> $gaps */
        $gaps = require __DIR__ . '/../Fuzz/known-form-gaps.php';

        $skip = [];
        foreach ($gaps['validationSuppliedOnlyByElement'] ?? [] as $line) {
            if (1 === preg_match("/^(.+?): '(.+?)' is validated by /", $line, $m)) {
                $skip[$m[1] . '::' . $m[2]] = true;
            }
        }

        return $skip;
    }

    /** @return array<string, mixed> */
    private static function specificationOf(Form $form): array
    {
        if (! method_exists($form, 'getInputFilterSpecification')) {
            return [];
        }

        try {
            /** @var mixed $spec */
            $spec = $form->getInputFilterSpecification();
        } catch (Throwable) {
            return [];
        }

        return is_array($spec) ? $spec : [];
    }

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

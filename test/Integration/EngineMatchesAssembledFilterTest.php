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
 * That test feeds `Laminas\InputFilter\Factory` and {@see Engine} the **same**
 * specification, so both sides start from the same place and it can only ever prove they
 * read a specification alike. What ships is `Form::getInputFilter()`, which also builds an
 * input per element and merges the two — and on 2026-09-10 **101 fields were validated only
 * by that element half**, 31 of them by `Csrf`. A parity harness fed from the narrowed
 * input agrees with itself and reports nothing.
 *
 * This test compares the two things that actually differ: the engine driven by the
 * specification, against the filter the application validates with today.
 *
 * ## Why it is not simply red
 *
 * Because the remaining entries of `validationSuppliedOnlyByElement` in
 * `test/Fuzz/known-form-gaps.php` are exactly the fields where the specification is known
 * to be incomplete, and they are skipped here by reading that file. So the two artefacts
 * are complementary rather than duplicated:
 *
 *   - the baseline names the fields still to do, and fails when a new one appears;
 *   - this test proves the ones already done actually behave identically, and its coverage
 *     **grows on its own** as that list shrinks.
 *
 * Nothing has to be remembered when a field is closed: delete its baseline line and it is
 * compared here from the next run.
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
     * It rises as `validationSuppliedOnlyByElement` shrinks, because a field leaving that
     * list starts being compared here. 250 when the checkboxes closed; **2,149** once the
     * selects, URLs, dates, numbers and emails followed, which is nine times the coverage
     * for the same assertion. The floor sits below that and above the previous step, so it
     * catches a collapse without pinning an exact number that every batch would have to
     * edit.
     */
    private const COMPARISON_FLOOR = 1800;

    /**
     * The fields where the engine and the assembled filter genuinely differ, each with the
     * reason it is not a defect in the engine.
     *
     * There were two. `Books\Form\BookForm::callNumber` left on 2026-09-11, when the
     * per-library requirement moved out of a patch on the built filter and into the
     * specification where the engine can read it — see
     * `SchoenstattTest\Integration\CallNumberRequirementTest`. That was the only
     * *structural* entry: the other is a property of the harness, not of the code.
     *
     * Skipping is not the same as excusing: an entry here has to say what the difference is
     * and why it is acceptable, and `BookForm::callNumber` is a step-5 work item rather than
     * a settled question.
     *
     * @var array<string, string>
     */
    private const KNOWN_DIFFERENCES = [
        //Not a difference in production. The controller calls setValueOptions() with the
        //library's collections before validating; FormRepository builds the form with no
        //request, so the element holds none and its own InArray has an EMPTY haystack —
        //which rejects every collection id. The specification uses ChoiceDomain's
        //documented $fallbackHaystack instead and accepts the real ones, so here the
        //engine is the more correct of the two. Comparing them in this state would pin a
        //harness artefact.
        'Books\Form\SearchForm::collectionId' => 'element options are populated per request, not at construction',

        //A `File` element becomes a Laminas\InputFilter\FileInput, which delegates a
        //present, non-empty value to an implementation that injects
        //Laminas\Validator\File\UploadFile — a check that the value really arrived
        //through an HTTP upload. So laminas REJECTS a well-formed $_FILES array that PHP
        //did not put there, and the engine, which has only the specification's
        //`required => false`, accepts it.
        //
        //Unreachable in production, and that is the form's design rather than luck: the
        //pages using it are Symfony-served, an upload arrives in $request->files and never
        //in the data the form is given, so `file` is always absent and FileInput returns
        //at its first branch. App\Books\Import\SpreadsheetUpload is what actually judges
        //the file — see the ImportForm docblock.
        //
        //Not visible to test/Fuzz/FormGapCollector either, which reads getValidatorChain()
        //and so cannot see a validator injected at isValid() time. It is recorded here
        //because this is the file that asks what the engine would carry.
        'Books\Form\ImportForm::file' => 'FileInput injects an upload validator the specification cannot state',
    ];

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

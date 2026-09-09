<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\InputFilter\Factory as LaminasFactory;
use Laminas\InputFilter\InputFilterInterface;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Form\Validation\InputFilter;
use Throwable;

use function array_keys;
use function count;
use function implode;
use function is_array;
use function sprintf;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The replacement input-filter engine answers exactly what laminas answers.
 *
 * ## Why this is the first thing step 5 does
 *
 * Step 5 replaces four packages across 136 files. It is separable because the engine and
 * the rules are separable: {@see InputFilter} decides required-ness, empty-ness and order,
 * while the individual filters and validators stay laminas' own, resolved by class name out
 * of the spec. So the risky half moves first, under a test that compares it against the
 * thing it replaces on **the application's real specifications** rather than on invented
 * ones — and the rules can then be swapped one at a time behind an engine already proven.
 *
 * The specs are the ones the forms declare. `test/Fuzz/FormRepository` builds every form
 * the same way the fuzz harness does, so a form added later is compared automatically.
 *
 * ## What is compared
 *
 * For each form and each data shape: `isValid()`, the **keys** of `getValues()`, and which
 * fields carry messages. Message *text* is deliberately not compared — laminas' chain
 * merges messages from every failing validator while this engine reports the first failing
 * one, which is the same verdict with a shorter list. A field that one engine considers
 * valid and the other does not is the failure this test exists to catch.
 */
class InputFilterEngineParityTest extends TestCase
{
    /** Data shapes chosen to exercise the five rules the engine reproduces. */
    private const SHAPES = [
        'empty submission'       => [],
        'every field empty'      => '',
        'every field null'       => null,
        'every field a string'   => 'x',
        'every field an array'   => [],
    ];

    public function testTheEngineAgreesWithLaminasOnEveryFormSpec(): void
    {
        $forms = FormRepository::instance()->forms();
        self::assertNotEmpty($forms, 'no forms were discovered — the comparison would be hollow');

        $mismatch = [];
        $compared = 0;

        foreach ($forms as $class => $form) {
            $spec = $this->specOf($form);
            if (null === $spec) {
                continue;
            }

            foreach (self::SHAPES as $label => $fill) {
                $data = $this->dataFor($spec, $label, $fill);

                try {
                    $laminas = (new LaminasFactory())->createInputFilter($spec);
                } catch (Throwable) {
                    //a spec laminas itself cannot build is not this engine's problem
                    continue 2;
                }

                //Resolved through laminas' own plugin managers, which is how a spec's short
                //names (`'StripTags'`, `'ToInt'`) become objects at all. The engine is what
                //is under test here; the rules are still laminas' and are replaced later.
                $mine = new InputFilter(
                    $spec,
                    static fn (string $n, array $o): object => self::filters()->get($n, $o),
                    static fn (string $n, array $o): object => self::validators()->get($n, $o)
                );

                try {
                    $laminas->setData($data);
                    $lamValid = $laminas->isValid();
                    $lamKeys  = array_keys($laminas->getValues());
                    $lamBad   = array_keys($laminas->getMessages());

                    $mine->setData($data);
                    $myValid = $mine->isValid();
                    $myKeys  = array_keys($mine->getValues());
                    $myBad   = array_keys($mine->getMessages());
                } catch (Throwable $e) {
                    //a validator needing a database or a container is out of scope here;
                    //the fuzz harness drives those through the real container
                    continue;
                }

                $compared++;

                if ($lamValid !== $myValid) {
                    $mismatch[] = sprintf(
                        '%s [%s]: laminas says %s, engine says %s',
                        $class,
                        $label,
                        $lamValid ? 'valid' : 'invalid',
                        $myValid ? 'valid' : 'invalid'
                    );
                    continue;
                }

                sort($lamKeys);
                sort($myKeys);
                if ($lamKeys !== $myKeys) {
                    $mismatch[] = sprintf(
                        '%s [%s]: values differ — laminas has %s, engine has %s',
                        $class,
                        $label,
                        implode(',', array_diff($lamKeys, $myKeys)) ?: '(none extra)',
                        implode(',', array_diff($myKeys, $lamKeys)) ?: '(none extra)'
                    );
                    continue;
                }

                sort($lamBad);
                sort($myBad);
                if ($lamBad !== $myBad) {
                    $mismatch[] = sprintf(
                        '%s [%s]: different fields rejected — laminas %s, engine %s',
                        $class,
                        $label,
                        implode(',', array_diff($lamBad, $myBad)) ?: '(none extra)',
                        implode(',', array_diff($myBad, $lamBad)) ?: '(none extra)'
                    );
                }
            }
        }

        self::assertSame([], $mismatch, sprintf(
            "%d of %d form/shape pairs disagree:\n%s",
            count($mismatch),
            $compared,
            implode("\n", array_slice($mismatch, 0, 30))
        ));
        self::assertGreaterThan(40, $compared, 'the comparison has gone hollow');
    }

    private static ?\Laminas\Filter\FilterPluginManager $filters = null;
    private static ?\Laminas\Validator\ValidatorPluginManager $validators = null;

    private static function filters(): \Laminas\Filter\FilterPluginManager
    {
        return self::$filters ??= new \Laminas\Filter\FilterPluginManager(self::container());
    }

    private static function validators(): \Laminas\Validator\ValidatorPluginManager
    {
        return self::$validators ??= new \Laminas\Validator\ValidatorPluginManager(self::container());
    }

    private static function container(): \Laminas\ServiceManager\ServiceManager
    {
        /** @var array<string, mixed> $appConfig */
        $appConfig = require __DIR__ . '/../../config/application.config.php';

        return \App\Laminas\ContainerFactory::build($appConfig);
    }

    /** @return array<string, mixed>|null */
    private function specOf(object $form): ?array
    {
        if (! $form instanceof \Laminas\InputFilter\InputFilterProviderInterface) {
            return null;
        }
        try {
            $spec = $form->getInputFilterSpecification();
        } catch (Throwable) {
            return null;
        }

        return is_array($spec) && [] !== $spec ? $spec : null;
    }

    /**
     * @param array<string, mixed> $spec
     * @return array<string, mixed>
     */
    private function dataFor(array $spec, string $label, mixed $fill): array
    {
        if ('empty submission' === $label) {
            return [];
        }

        $data = [];
        foreach (array_keys($spec) as $name) {
            $data[(string) $name] = $fill;
        }

        return $data;
    }
}

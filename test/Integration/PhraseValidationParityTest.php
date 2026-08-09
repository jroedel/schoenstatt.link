<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\JTranslate\Phrase\PhraseValidator;
use JTranslate\Form\EditPhraseForm;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\Feature\GlobalAdapterFeature;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_values;
use function sort;
use function str_repeat;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * A translation agent is refused exactly what a translator is refused.
 *
 * `/api/v3/phrases` does not reimplement the translator form's rules; it takes the
 * form's own `InputFilter` through {@see PhraseValidator}. That claim is worth a test
 * because it is invisible at the call site — a later refactor that "simplifies"
 * PhraseValidator into a hand-written specification would keep every smoke test
 * passing while quietly making the API looser or stricter than the web form, and the
 * direction it would fail in is the bad one: an agent storing a value the moderator
 * form would have refused, rendered on every page of the site in that locale.
 *
 * This mirrors {@see AssociationValidationParityTest} for the second entity v3
 * exposes. Two differences are worth knowing about, both consequences of the form
 * rather than of the API:
 *
 * 1. **This form's rules are thin.** Each locale is optional, trimmed and bounded at
 *    2000 characters, and that is the whole of it. The test below is correspondingly
 *    short, and its value is in *pinning* that thinness — if someone adds a
 *    placeholder or markup rule to the form tomorrow, the API inherits it with no
 *    edit here, and if someone adds one to the API alone this test fails.
 * 2. **The CSRF seam is the same, and so is the static-adapter one.**
 *    `EditPhraseForm::getInputFilterSpecification()` reads
 *    `GlobalAdapterFeature::getStaticAdapter()` while building its `RecordExists`
 *    validator, and nothing populates that registry on a Symfony-served route — see
 *    PhraseValidator. That is a wiring difference, not a rules difference, and
 *    testTheSameInputsAreValidatedOnBothSides is what says so.
 */
final class PhraseValidationParityTest extends TestCase
{
    private static ?ServiceManager $services = null;

    /** The locales this site writes, resolved once from the merged config. */
    private static ?array $locales = null;

    /**
     * The API's filter, and the form's, built from the same live configuration.
     *
     * Deliberately not built from a fixture locale list. The merged config is what
     * decides the writable set — JTranslate's module config names three locales and
     * this application's `config/autoload/jtranslate.global.php` appends `it_IT`, so a
     * hardcoded list here would be testing a set neither surface uses.
     */
    private static function validator(): PhraseValidator
    {
        return PhraseValidator::fromServices(self::services()->get(...));
    }

    /** The web form, built the way JTranslate's own factory builds it. */
    private static function form(): EditPhraseForm
    {
        $services = self::services();
        /** @var Adapter $adapter */
        $adapter = $services->get(Adapter::class);
        GlobalAdapterFeature::setStaticAdapter($adapter);

        /** @var array<string, mixed> $config */
        $config     = $services->get('config');
        $jtranslate = $config['jtranslate'];

        $locales = [];
        foreach (self::locales() as $locale) {
            $locales[$locale] = $locale;
        }

        return new EditPhraseForm(
            $locales,
            $jtranslate['phrases_table_name'],
            $jtranslate['translations_table_name']
        );
    }

    /** @return list<string> */
    private static function locales(): array
    {
        if (null !== self::$locales) {
            return self::$locales;
        }

        /** @var \JTranslate\Model\TranslationsTable $table */
        $table = self::services()->get(\JTranslate\Model\TranslationsTable::class);

        return self::$locales = array_keys($table->getLocales(true));
    }

    /**
     * The set of inputs is the same on both sides, save the one documented difference.
     *
     * This is the assertion that catches the API quietly dropping a field. Comparing
     * *names* rather than behaviour, because a field the API never validates at all is
     * the failure mode a payload-by-payload comparison misses: it would simply never
     * produce a payload for it.
     */
    public function testCsrfIsTheOnlyInputTheApiDoesNotEnforce(): void
    {
        $formInputs = array_keys(self::form()->getInputFilter()->getInputs());
        $apiInputs  = array_keys(self::validator()->inputFilter()->getInputs());

        sort($formInputs);
        sort($apiInputs);

        $onlyOnTheForm = array_values(array_diff($formInputs, $apiInputs));
        $onlyOnTheApi  = array_values(array_diff($apiInputs, $formInputs));

        self::assertSame(
            [PhraseValidator::SESSION_ONLY_INPUT],
            $onlyOnTheForm,
            'the API skips an input the translator form enforces, so an agent can store something a '
            . 'translator cannot'
        );
        self::assertSame(
            [],
            $onlyOnTheApi,
            'the API enforces an input the form does not, so it has stopped being the form\'s filter'
        );
    }

    /**
     * Every writable locale is actually validated — not merely present.
     *
     * The trap this pins down is documented on EditPhraseForm and in CLAUDE.md: an
     * element missing from `getInputFilterSpecification()` gets `['required' => false]`
     * and nothing else, so it looks like a validated field and enforces nothing. Every
     * locale textarea on this form used to be in exactly that state, accepting any
     * string of any length into a `varchar(2000) NOT NULL`.
     */
    #[DataProvider('locale')]
    public function testEachWritableLocaleIsBounded(string $locale): void
    {
        $filter = self::validator()->inputFilter();

        $filter->setData(['phraseId' => self::anExistingPhraseId(), $locale => str_repeat('a', 2001)]);
        self::assertFalse($filter->isValid(), "$locale accepts more than the column holds");
        self::assertArrayHasKey('stringLengthTooLong', $filter->getMessages()[$locale]);

        $filter->setData(['phraseId' => self::anExistingPhraseId(), $locale => str_repeat('a', 2000)]);
        self::assertTrue($filter->isValid(), "$locale refuses a translation that fits the column");
    }

    /** @return iterable<string, array{0: string}> */
    public static function locale(): iterable
    {
        foreach (self::locales() as $locale) {
            yield $locale => [$locale];
        }
    }

    /**
     * A patch naming one locale validates, with no other locale supplied.
     *
     * This is what makes a partial update possible without a second, weaker rule set,
     * and it is a property of the form rather than of the API: nothing here is required
     * except `phraseId`. The association API has to merge the stored record in before
     * validating because `name` and `kind` are required there; this one must not, and
     * this test is why the difference is safe rather than an oversight.
     */
    public function testAPatchNamingOneLocaleIsAlreadyACompleteSubmission(): void
    {
        $filter = self::validator()->inputFilter();
        $filter->setData(['phraseId' => self::anExistingPhraseId(), 'de_DE' => 'Ein Satz.']);

        self::assertTrue($filter->isValid(), (string) json_encode($filter->getMessages()));
    }

    /** Trimming happens, and the *trimmed* value is what a caller must write. */
    public function testTheFilterTrimsTheValueTheApiThenWrites(): void
    {
        $filter = self::validator()->inputFilter();
        $filter->setData(['phraseId' => self::anExistingPhraseId(), 'de_DE' => "  Ein Satz.\n"]);

        self::assertTrue($filter->isValid());
        self::assertSame(
            'Ein Satz.',
            $filter->getValues()['de_DE'],
            'the API writes getValues(), so an untrimmed value here is an untrimmed row'
        );
    }

    /**
     * `phraseId` is checked against the table, which is the form's rule and the reason
     * PhraseValidator has to populate the static adapter at all.
     */
    public function testAnUnknownPhraseIdIsRefused(): void
    {
        $filter = self::validator()->inputFilter();
        $filter->setData(['phraseId' => 999999999, 'de_DE' => 'Ein Satz.']);

        self::assertFalse($filter->isValid());
        self::assertArrayHasKey('phraseId', $filter->getMessages());
    }

    /**
     * A phrase id that exists in this database.
     *
     * Read from the table rather than hardcoded: `RecordExists` asks the real table, so
     * a fixed id would make this file fail whenever the dump changes, for a reason that
     * has nothing to do with the rules under test.
     */
    private static function anExistingPhraseId(): int
    {
        /** @var \JTranslate\Model\TranslationsTable $table */
        $table = self::services()->get(\JTranslate\Model\TranslationsTable::class);
        $page  = $table->getPhrasePage([], 1, 0);

        $first = array_values($page)[0] ?? null;
        if (null === $first) {
            self::markTestSkipped('this database holds no phrases for the configured project');
        }

        return (int) $first['phraseId'];
    }

    /**
     * The application container, built the way bin/console builds it: modules loaded,
     * never bootstrapped. Both sides of the comparison come out of the same one, so a
     * difference between them cannot be a difference in configuration.
     */
    private static function services(): ServiceManager
    {
        if (null !== self::$services) {
            return self::$services;
        }

        /** @var array<string, mixed> $appConfig */
        $appConfig = require __DIR__ . '/../../config/application.config.php';

        $services = new ServiceManager();
        (new ServiceManagerConfig($appConfig['service_manager'] ?? []))->configureServiceManager($services);
        $services->setService('ApplicationConfig', $appConfig);
        $services->get('ModuleManager')->loadModules();

        return self::$services = $services;
    }
}

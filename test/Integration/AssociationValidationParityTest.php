<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Date as DateElement;
use Laminas\Form\Element\Email;
use Laminas\Form\Element\Url;
use App\Schoenstatt\Association\AssociationFieldDomains;
use App\Schoenstatt\Association\AssociationInputFilterSpec;
use App\Schoenstatt\Association\AssociationValidator;
use InvalidArgumentException;
use Laminas\Form\Factory as FormFactory;
use Laminas\Form\FormElementManager;
use Laminas\InputFilter\Factory as InputFilterFactory;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Schoenstatt\Form\AssociationForm;
use SionModel\Form\Element\Phone;
use SionModel\Form\SionForm;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The contract that makes "the API validates shrines exactly as the web form does"
 * a fact rather than an intention.
 *
 * Automated agents write associations through `/api/v3`; moderators write them
 * through the edit form. The requirement is that the two are held to the same rules,
 * so {@see AssociationValidator} hands the API the web form's own `InputFilter`
 * rather than rebuilding one.
 *
 * That makes most of the parity structural, which is the point — but not all of it,
 * and what is left is what this test pins:
 *
 * - **The API removes the CSRF input.** It has to: `Laminas\Validator\Csrf` reads a
 *   session an API request does not have. That removal is the entire permitted
 *   difference between the two surfaces, and `testCsrfIsTheOnlyDifference` asserts
 *   it is the *only* one — field by field, validator by validator, filter by filter.
 *   A future edit that drops another input to make something pass fails here.
 * - **Behaviour, not just wiring.** `SionForm::setData()` rewrites data before the
 *   filter sees it and `AssociationForm::setData()` mutates value options, so
 *   identical chains still do not prove identical verdicts. Both surfaces are driven
 *   with the same payloads and their verdicts, messages *and filtered values*
 *   compared.
 * - **The specification is not the whole filter.** Building an input filter straight
 *   from `AssociationInputFilterSpec` yields something looser than the form on twelve
 *   fields, because element-provided validators merge in.
 *   `testSpecificationAloneIsLooserThanTheForm` records that as a fact so nobody
 *   "simplifies" the validator back into the bug it was.
 *
 * No container and no database: the domains are fixtures. That the *real* form is
 * wired to the *real* domains is a different claim, and it is the fuzz suite that
 * makes it — a form built through `AssociationFormFactory` without domains produces
 * an empty specification, which `test/Fuzz` reports as every field missing from the
 * spec.
 *
 * Run in the capsule: php composer.phar integration
 */
final class AssociationValidationParityTest extends TestCase
{
    /**
     * Small stand-ins for the four runtime domains. Real shapes, fixed contents, so a
     * failure here is about the rules and never about what happens to be in the
     * database today.
     */
    private static function domains(): AssociationFieldDomains
    {
        return new AssociationFieldDomains(
            kinds: ['sch-shrine', 'sch-wayside-shrine', 'sch-national-movement'],
            countries: ['US', 'DE', 'GB-SCT'],
            parentIds: [71, 519, 8],
            timeZones: ['America/Chicago', 'Europe/Berlin'],
        );
    }

    /**
     * A form that can be built without the application.
     *
     * `init()` needs one thing the default `FormElementManager` does not have: the
     * `Phone` element, which `SionModel` registers as an invokable in its module
     * config. Registering just that keeps the test free of module loading — the point
     * being to compare two rule sets, not to rebuild the application around them.
     */
    private static function form(bool $withCsrf = true): AssociationForm
    {
        $elements = new FormElementManager(new ServiceManager(), [
            'invokables' => ['Phone' => Phone::class],
        ]);

        $form = new AssociationForm();
        $form->setFormFactory(new FormFactory($elements));
        $form->setFieldDomains(self::domains());
        $form->init();

        if (! $withCsrf) {
            //The behavioural tests compare *rules*, and a CSRF element with no session
            //and no submitted token fails every payload — which would make the form
            //invalid for a reason the API deliberately does not share, on all 32 cases
            //at once. Dropping it here keeps those tests about the association.
            //
            //It does not weaken the CSRF claim: testCsrfIsTheOnlyDifference builds the
            //form *with* it and compares the two input sets directly, which is the
            //assertion that would catch the API skipping anything else.
            //
            //The **input** goes, not the element — which is what
            //AssociationValidator::inputFilter() does, so this helper is now the same
            //operation the API performs rather than a similar-looking one. It matters
            //since AssociationForm's specification names `security` (2026-09-10): a form
            //whose element had been removed could no longer describe its own rules, and
            //every case here died on "No element by the name of [security] found in form".
            $filter = $form->getInputFilter();
            if ($filter->has(AssociationValidator::SESSION_ONLY_INPUT)) {
                $filter->remove(AssociationValidator::SESSION_ONLY_INPUT);
            }
        }

        return $form;
    }

    /**
     * The association's checkboxes. Named once because two assertions need the same list
     * and a copy of it would drift.
     */
    private const CHECKBOXES = [
        'isActive',
        'isAuthor',
        'isLifeCommunity',
        'isNameTranslateable',
        'overrideNameFormat',
        'isInternalNameTranslateable',
    ];

    /** A payload that must pass, and the base every invalid case below is a mutation of. */
    private const VALID = [
        'associationId' => 10519,
        'name'          => 'Austin',
        'kind'          => 'sch-shrine',
        'country'       => 'US',
        'timeZoneId'    => 'America/Chicago',
        'parentId'      => 71,
        'geoPoint'      => '30.311108, -97.842738',
        'email'         => 'shrine@example.com',
        'publicNotes'   => 'Turn right after the highway.',
    ];

    /**
     * One case per rule that the move to a shared specification introduced or kept,
     * plus the hostile shapes that have historically been 500s rather than validation
     * failures — an array where a scalar belongs, in the two fields whose `setData()`
     * handling exists because of exactly that.
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function payloads(): iterable
    {
        yield 'valid'                  => [self::VALID];
        yield 'unknown kind'           => [['kind' => 'not-a-kind'] + self::VALID];
        yield 'unknown country'        => [['country' => 'ZZ'] + self::VALID];
        yield 'lowercase country'      => [['country' => 'us'] + self::VALID];
        yield 'subdivision country'    => [['country' => 'GB-SCT'] + self::VALID];
        yield 'unknown time zone'      => [['timeZoneId' => 'Mars/Olympus'] + self::VALID];
        yield 'unknown parent'         => [['parentId' => 999999] + self::VALID];
        yield 'parent as string'       => [['parentId' => '71'] + self::VALID];
        yield 'cleared parent'         => [['parentId' => ''] + self::VALID];
        yield 'cleared time zone'      => [['timeZoneId' => ''] + self::VALID];
        yield 'missing required name'  => [array_diff_key(self::VALID, ['name' => null])];
        yield 'missing required kind'  => [array_diff_key(self::VALID, ['kind' => null])];
        yield 'over-long name'         => [['name' => str_repeat('n', 250)] + self::VALID];
        yield 'over-long publicNotes'  => [['publicNotes' => str_repeat('x', 20000)] + self::VALID];
        yield 'over-long adminNotes'   => [['adminNotes' => str_repeat('x', 20000)] + self::VALID];
        yield 'over-long url'          => [['url1' => 'https://example.com/' . str_repeat('p', 1200)] + self::VALID];
        yield 'over-long url label'    => [['url1Label' => str_repeat('L', 60)] + self::VALID];
        yield 'over-long phone label'  => [['phone1Label' => str_repeat('L', 60)] + self::VALID];
        yield 'free-text phone label'  => [['phone1Label' => 'Casa del Peregrino'] + self::VALID];
        yield 'free-text url label'    => [['url1Label' => 'Parish'] + self::VALID];
        yield 'over-long twitter'      => [['twitterUser' => str_repeat('a', 60)] + self::VALID];
        yield 'malformed twitter'      => [['twitterUser' => 'not a handle!'] + self::VALID];
        yield 'malformed email'        => [['email' => 'not-an-email'] + self::VALID];
        yield 'over-long email'        => [['email' => str_repeat('a', 210) . '@example.com'] + self::VALID];
        yield 'malformed coordinates'  => [['geoPoint' => 'nope'] + self::VALID];
        yield 'future foundation date' => [['foundationDate' => '2999-01-01'] + self::VALID];
        yield 'pre-1914 foundation'    => [['foundationDate' => '1900-01-01'] + self::VALID];
        yield 'bad date precision'     => [['foundationDatePrecision' => 'century'] + self::VALID];
        yield 'array where scalar'     => [['phone1Label' => ['x']] + self::VALID];
        yield 'array in notes'         => [['publicNotes' => ['x']] + self::VALID];
        yield 'html in name'           => [['name' => '<script>alert(1)</script>Austin'] + self::VALID];
        yield 'empty payload'          => [[]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('payloads')]
    public function testFormAndBareFilterAgree(array $payload): void
    {
        $form = self::form(withCsrf: false);
        $form->setData($payload);
        $formValid = $form->isValid();

        $filter = (new AssociationValidator(self::domains()))->inputFilter();
        $filter->setData($payload);
        $filterValid = $filter->isValid();

        $formMessages   = self::comparable($form->getMessages());
        $filterMessages = self::comparable($filter->getMessages());

        self::assertSame(
            $filterMessages,
            $formMessages,
            'The web form and a bare input filter built from the shared specification disagreed about '
            . 'which fields are wrong. An agent and a moderator would get different answers for the same '
            . 'shrine.'
        );

        //Asserted after the messages, deliberately: when the two disagree it is the
        //message diff that says why, and a bare boolean mismatch reported first would
        //hide it.
        self::assertSame(
            $filterValid,
            $formValid,
            'The web form and the shared specification disagreed on whether this payload is valid at all.'
        );
    }

    /**
     * `getValues()` is what reaches `SionTable::updateHelper()`, so agreeing about
     * validity is only half of it: the two must also *filter* identically, or the API
     * and the form would write different values for the same input.
     */
    public function testFormAndBareFilterProduceTheSameValues(): void
    {
        $payload = [
            'name'        => "  <b>Austin</b>\n ",
            'country'     => 'us',
            'parentId'    => '71',
            'geoPoint'    => '30.311108, -97.842738',
            'isActive'    => '1',
            'publicNotes' => '<script>x</script>Notes',
        ] + self::VALID;

        $form = self::form(withCsrf: false);
        $form->setData($payload);
        $form->isValid();

        $filter = (new AssociationValidator(self::domains()))->inputFilter();
        $filter->setData($payload);
        $filter->isValid();

        $spec       = (new AssociationValidator(self::domains()))->writableFields();
        $formValues = array_intersect_key($form->getData(), array_flip($spec));

        self::assertEquals(
            array_intersect_key($filter->getValues(), array_flip($spec)),
            $formValues,
            'The form and the shared specification filtered the same submission into different values.'
        );
    }

    /**
     * The phone and phone-label entries in the shared specification are copies of
     * `SionForm`'s protected properties, because an API request has no form to read
     * them from. This is the assertion that keeps the copies honest.
     */
    public function testPhoneSpecificationsStillMatchSionForm(): void
    {
        $sionForm   = new ReflectionClass(SionForm::class);
        $instance   = new SionForm('parity');
        $readShared = static function (string $property) use ($sionForm, $instance): mixed {
            return $sionForm->getProperty($property)->getValue($instance);
        };

        $spec = (new AssociationInputFilterSpec(self::domains()))->toArray();

        self::assertEquals(
            $readShared('phoneInputFilterSpec'),
            $spec['phone1'],
            'AssociationInputFilterSpec::phone() has drifted from SionForm::$phoneInputFilterSpec. '
            . 'The form and the API would validate phone numbers differently.'
        );
        self::assertEquals(
            $readShared('phoneLabelInputFilterSpec'),
            $spec['phone1Label'],
            'AssociationInputFilterSpec::phoneLabel() has drifted from '
            . 'SionForm::$phoneLabelInputFilterSpec.'
        );
    }

    /**
     * The property the whole Symfony side depends on: no ServiceManager, no module
     * loading, no merged config — just the specification and laminas-inputfilter.
     */
    public function testValidatorBuildsWithoutAContainer(): void
    {
        $filter = (new AssociationValidator(self::domains()))->inputFilter();
        $filter->setData(self::VALID);

        self::assertTrue($filter->isValid(), (string) json_encode($filter->getMessages()));
    }

    /**
     * The CSRF input is the only thing the API does not enforce. Compared structurally
     * rather than behaviourally, because a validator that is never *reached* by any
     * payload in the corpus would still slip past the behavioural tests.
     */
    public function testCsrfIsTheOnlyDifference(): void
    {
        $formFilter = self::form()->getInputFilter();
        $apiFilter  = (new AssociationValidator(self::domains()))->inputFilter();

        $formInputs = array_keys(iterator_to_array($formFilter->getInputs()));
        $apiInputs  = array_keys(iterator_to_array($apiFilter->getInputs()));

        self::assertSame(
            [AssociationValidator::SESSION_ONLY_INPUT],
            array_values(array_diff($formInputs, $apiInputs)),
            'The API is skipping an input the web form enforces. Agents would be held to looser rules '
            . 'than moderators.'
        );
        self::assertSame([], array_values(array_diff($apiInputs, $formInputs)));

        foreach ($apiInputs as $name) {
            self::assertSame(
                self::chain($formFilter, (string) $name),
                self::chain($apiFilter, (string) $name),
                sprintf('The validator and filter chains for %s differ between the two surfaces.', $name)
            );
        }
    }

    /**
     * A record of the mistake AssociationValidator exists to avoid: an input filter
     * built straight from the specification is *looser* than the web form, because the
     * form's element-provided validators merge in on top of it.
     *
     * If this test ever fails because the two now agree, that is good news — but it
     * means AssociationValidator's reason for building a form has gone, and the
     * decision should be revisited rather than the test deleted.
     */
    public function testSpecificationAloneIsLooserThanTheForm(): void
    {
        $bare = (new InputFilterFactory())->createInputFilter(
            (new AssociationInputFilterSpec(self::domains()))->toArray()
        );
        $api = (new AssociationValidator(self::domains()))->inputFilter();

        $looser = [];
        foreach (['email', 'url1', 'url2', 'url3', 'facebookUrl', 'foundationDate',
                  'isActive', 'isAuthor', 'isLifeCommunity', 'isNameTranslateable',
                  'overrideNameFormat', 'isInternalNameTranslateable'] as $field) {
            if (self::chain($bare, $field) !== self::chain($api, $field)) {
                $looser[] = $field;
            }
        }

        self::assertCount(
            12,
            $looser,
            'The specification alone was expected to be looser than the form on twelve fields.'
        );
    }

    /**
     * The validator and filter class names on one input, in order — enough to detect a
     * dropped, added or reordered check without depending on option formatting.
     *
     * @return array{validators: list<string>, filters: list<string>}
     */
    /**
     * The literal in `AssociationInputFilterSpec::CHECKBOX_DOMAIN` still describes the
     * form's actual checkboxes.
     *
     * That class holds no elements — it is the contract the web form and the API share,
     * and the API builds no form — so the two checkbox values are written there as `'1'`
     * and `'0'` rather than read off anything, which is the one place this specification
     * duplicates knowledge instead of deriving it. This keeps the duplicate honest: change
     * `checked_value` on one of these elements and the shared specification would quietly
     * describe a domain the form no longer posts.
     *
     * `SionModel\Form\CheckboxDomain` is what every other form uses, and it reads the
     * element precisely so that no such assertion is needed. Six fields here cannot.
     */
    /**
     * The element-derived literals in `AssociationInputFilterSpec` still describe the
     * form's actual elements.
     *
     * `URI`, `HTML5_EMAIL` and `HTML5_DATE` restate what `Laminas\\Form\\Element\\Url`,
     * `Email` and `Date` contribute of their own accord. Every other form in the
     * application derives those from the element through
     * `SionModel\\Form\\InputTypeRules`; these six cannot, because this specification is
     * shared with the API and the API builds no form.
     *
     * So the check is that the elements are still the types those literals were written
     * for, and that the one attribute the literals encode — the date's lower bound — has
     * not moved. Change the element and this fails instead of the specification quietly
     * describing rules the form no longer applies.
     */
    public function testTheSharedSpecificationStillDescribesTheFormsElements(): void
    {
        $form = self::form(withCsrf: false);

        foreach (['url1', 'url2', 'url3', 'facebookUrl'] as $field) {
            self::assertInstanceOf(Url::class, $form->get($field), $field);
        }

        self::assertInstanceOf(Email::class, $form->get('email'));

        $foundationDate = $form->get('foundationDate');
        self::assertInstanceOf(DateElement::class, $foundationDate);
        self::assertSame('Y-m-d', $foundationDate->getFormat());
        self::assertSame(
            '1900-01-01',
            $foundationDate->getAttribute('min'),
            "the date element's lower bound moved; AssociationInputFilterSpec::HTML5_DATE still says 1900-01-01"
        );
    }

    public function testTheSharedSpecificationStillDescribesTheFormsCheckboxes(): void
    {
        $form = self::form(withCsrf: false);

        foreach (self::CHECKBOXES as $field) {
            $element = $form->get($field);

            self::assertInstanceOf(Checkbox::class, $element, $field);
            self::assertSame(
                ['1', '0'],
                [$element->getCheckedValue(), $element->getUncheckedValue()],
                sprintf(
                    "%s no longer posts '1'/'0', so AssociationInputFilterSpec::CHECKBOX_DOMAIN "
                    . 'describes a domain this form does not use',
                    $field
                )
            );
        }
    }

    private static function chain(\Laminas\InputFilter\InputFilterInterface $filter, string $name): array
    {
        $input = $filter->get($name);

        $validators = [];
        if ($input instanceof \Laminas\InputFilter\InputInterface) {
            foreach ($input->getValidatorChain()->getValidators() as $entry) {
                $validators[] = is_object($entry['instance']) ? $entry['instance']::class : '?';
            }
            $filters = [];
            foreach ($input->getFilterChain()->getFilters() as $one) {
                $filters[] = is_object($one) ? $one::class : '?';
            }

            sort($validators);
            sort($filters);

            return ['validators' => $validators, 'filters' => $filters];
        }

        return ['validators' => [], 'filters' => []];
    }

    /**
     * An empty domain would reject every submission. It is a failed service, and it
     * says so at construction rather than at the first refused edit.
     */
    public function testAnEmptyDomainIsRefusedLoudly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/countries domain is empty/');

        new AssociationFieldDomains(
            kinds: ['sch-shrine'],
            countries: [],
            parentIds: [71],
            timeZones: ['America/Chicago'],
        );
    }

    /**
     * `eventsJson` was named in the specification while its element stayed commented
     * out, which made it an input that contributed `null` to every submission and let
     * `SionTable::updateHelper()` write that null over a stored value. Four
     * associations held one. It must not come back.
     */
    public function testEventsJsonIsNotAPhantomField(): void
    {
        $spec = (new AssociationInputFilterSpec(self::domains()))->toArray();
        self::assertArrayNotHasKey('eventsJson', $spec);

        $form = self::form(withCsrf: false);
        $form->setData(self::VALID);
        $form->isValid();

        self::assertArrayNotHasKey(
            'eventsJson',
            $form->getData(),
            'A field absent from the form is contributing a null to the data again, which is how the '
            . 'stored EventsJson of four associations got erased on edit.'
        );
    }

    /**
     * Everything but the CSRF field, which only the form has. See the class docblock.
     *
     * @param array<string, mixed> $messages
     * @return array<string, mixed>
     */
    private static function comparable(array $messages): array
    {
        unset($messages['security']);
        ksort($messages);

        return $messages;
    }
}

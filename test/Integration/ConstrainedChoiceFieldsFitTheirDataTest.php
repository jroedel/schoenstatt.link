<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\Db\Adapter\AdapterInterface;
use SionModel\Form\Element\Select;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Laminas\Validator\Explode;
use Laminas\Validator\InArray;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Form\Engine;
use SchoenstattTest\Fuzz\FormGapCollector;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Service\EntitiesService;
use Throwable;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_intersect;
use function array_shift;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function sort;
use function sprintf;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Fuzz/FormRepository.php';
require_once __DIR__ . '/../Fuzz/FormGapCollector.php';

/**
 * Every value already in the database is one its own form would still accept.
 *
 * ## The hazard this exists for
 *
 * Restating a select's `InArray` in a form specification looks like pure gain — the option
 * list starts constraining again, nothing legitimate is refused. That is true only if the
 * stored data is inside the option list, and in this application it repeatedly is not:
 *
 * - `getEditionValueOptions()` filters `DataSource IS NULL`, so the publication select offers
 *   **4,203 of the 10,166** publications — and **386 books point at one of the other 5,963**.
 * - `mus_compositions.Country` held `pt` and `cl` in lowercase against an uppercase-keyed
 *   list: 292 of 308 rows. Corrected by db7.9 and the field is constrained as of batch 12.
 * - `lib_books.lang` held `ceb`, a bare `p`, and `es;de;en` — three languages in a
 *   single-value column. Also corrected by db7.9, except `ceb`, which is now an option.
 *
 * Constraining any of those does not protect the column, it makes existing records
 * **unsaveable**: the moderator opens a record that has been there for years, changes a
 * comma, and the form refuses it over a field they never touched. Worse, the message points
 * at the wrong thing, because the value was not typed in this session.
 *
 * So the rule is not "add an InArray everywhere" — it is "add one where the data already
 * fits", and this test is what makes the second half checkable. Batch 11 constrained 24
 * fields and left 26 alone; batch 12 constrained 13 more, and the three publication selects
 * that are still open are open precisely because of what this test measures.
 *
 * ## Why it reads the schema instead of submitting forms
 *
 * The direct proof would be to load each record into its form and validate it, and that runs
 * into the same wall everywhere else in this suite does: turning a stored row back into the
 * POST a browser would send means reproducing every filter, date format and array join the
 * form applies. Comparing the column's distinct values against the element's option list asks
 * the same question — *is what we stored still an answer this field offers?* — without any of
 * that machinery, and it covers **every** row rather than a sampled one.
 *
 * ## What a failure means
 *
 * Not necessarily that the form is wrong. It can equally mean the data drifted, or that a
 * factory narrowed a list. Read which values are outside before deciding: the fix is either a
 * data correction, a wider option list, or removing the `InArray` again and filing the field
 * in `docs/BACKLOG.md`.
 */
final class ConstrainedChoiceFieldsFitTheirDataTest extends TestCase
{
    /**
     * Constrained choice fields reachable from an entity's edit form, counted 2026-08-15
     * after batch 12. The floor is well under the real number on purpose: it exists to catch
     * discovery breaking entirely, not to be a second copy of the count.
     */
    private const CHECKED_FLOOR = 15;

    /**
     * Fields whose option list is scoped to a route parameter, so a single comparison here is
     * meaningless.
     *
     * `BookForm` and `LibraryForm` are built by factories that read the library out of the
     * route match, and the harness injects one arbitrary library. Comparing that library's
     * four collections against every book in the database reports the other library's 11,401
     * books as violations — which is an artifact of the fixture, not a finding. The real
     * question for these is per-library and
     * `testNoRecordPointsAtAnotherLibrarysCollection()` asks it in SQL.
     */
    private const ROUTE_SCOPED = [
        'book::collectionId',
        'library::mainCollectionId',
    ];

    /**
     * Records that cannot be saved through their own form today, accepted for now.
     *
     * Every entry here is a live defect, not a false positive: open one of these records,
     * change anything, press save, and the form refuses it over a field the moderator never
     * touched. They are listed rather than fixed because each needs a decision about the data
     * that is not a developer's to make — see docs/BACKLOG.md.
     *
     * The counts are what makes an entry reviewable, and they are checked: an accepted
     * mismatch that has stopped being a mismatch fails the test, the same way a stale
     * declaration does in the fuzz harness. Shrinking one of these numbers is progress and
     * requires editing this list, which is the point.
     *
     * @var array<string, string>
     */
    private const ACCEPTED_MISMATCHES = [
        //getEditionValueOptions() filters `DataSource IS NULL`, offering 4,203 of the 10,166
        //publications. The imported 5,963 are excluded from the picker but not from the data.
        'book::publicationId'                    => '479 books point at an excluded publication',
        'publication::mainPublicationId'         => '46 publications point at an excluded publication',
        'publication::translatedFromPublicationId' => '165 publications point at an excluded publication',
        //getAssociationValueOptions() offers active associations only, and sch_roles has no
        //foreign key: 145 roles name an association id that does not exist at all, 4 more sit
        //under the two inactive ones.
        'role::associationId'                    => '149 roles name an association the picker does not offer',
    ];

    public function testEveryStoredValueIsStillOfferedByItsField(): void
    {
        try {
            $repository = FormRepository::instance();
            $container  = $repository->container();
            /** @var AdapterInterface $adapter */
            $adapter = $container->get('Laminas\Db\Adapter\Adapter');
            $adapter->query('SELECT 1', []);
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }

        $entities = $container->get(EntitiesService::class)->getEntities();
        $forms    = $repository->forms();

        $checked          = 0;
        $findings         = [];
        $acceptedSeen     = [];
        $staleAcceptances = [];

        foreach ($entities as $entity => $spec) {
            $formClass = $spec->editActionForm ?: ($spec->createActionForm ?: null);
            $form      = null === $formClass ? null : ($forms[$formClass] ?? null);
            if (! $form instanceof Fieldset || null === $spec->tableName) {
                continue;
            }

            if (! $form instanceof Form) {
                continue;
            }

            $formSpec = Engine::specificationOf($form);

            foreach ($form->getElements() as $element) {
                //`MultiCheckbox` stood beside `Select` here. The element model has neither
                //it nor `Radio`, because no form on this site uses one.
                if (! $element instanceof Select) {
                    continue;
                }

                $name = (string) $element->getName();
                if (! isset($spec->updateColumns[$name]) || ! array_key_exists($name, $formSpec)) {
                    continue;
                }

                //**The specification, which is now the only half there is.** This read
                //laminas' assembled filter, and had to: laminas merged the element's own
                //InArray with the spec's rather than replacing it, so a field could be
                //constrained by either, by both with different haystacks, or by neither —
                //and reading the specification alone was wrong, which is how three
                //publication selects sat in docs/BACKLOG.md as "unconstrained, do not
                //touch" while rejecting 484 books' stored values. The element model of
                //#243 has no `getInputSpecification()`, so no element supplies a
                //validator any more; `validationSuppliedOnlyByElement` in
                //`test/Fuzz/known-form-gaps.php` is the measurement of that, and it is
                //empty. Verified field by field when this changed: every haystack read
                //here matched the one the assembled filter answered.
                $options = $this->effectiveHaystack($formSpec[$name]);
                if (null === $options) {
                    continue;
                }

                $key = $entity . '::' . $name;
                if (in_array($key, self::ROUTE_SCOPED, true)) {
                    continue;
                }

                $checked++;

                $column = (string) $spec->updateColumns[$name];
                $stored = $this->distinctValues($adapter, (string) $spec->tableName, $column);

                $outside = array_diff($stored, $options);
                if ([] === $outside) {
                    $staleAcceptances[] = $key;
                    continue;
                }

                if (isset(self::ACCEPTED_MISMATCHES[$key])) {
                    $acceptedSeen[$key] = true;
                    continue;
                }

                sort($outside);
                $findings[] = sprintf(
                    '%s.%s (%s::%s) holds %d value(s) its own select does not offer: %s',
                    $spec->tableName,
                    $column,
                    $entity,
                    $name,
                    count($outside),
                    implode(', ', array_slice($outside, 0, 10))
                );
            }
        }

        self::assertGreaterThanOrEqual(
            self::CHECKED_FLOOR,
            $checked,
            'far fewer constrained choice fields were found than exist — discovery is broken'
        );

        self::assertSame(
            [],
            $findings,
            "a record in the database can no longer be saved through its own form:\n  "
                . implode("\n  ", $findings)
                . "\n\nEither correct the data, widen the option list, or take the InArray off again"
                . ' and file the field in docs/BACKLOG.md.'
        );

        //An accepted mismatch that no longer mismatches is stale. Left in place it would hide
        //the field's next regression, so it fails here rather than quietly protecting nothing.
        $stale = array_values(array_intersect($staleAcceptances, array_keys(self::ACCEPTED_MISMATCHES)));
        sort($stale);
        self::assertSame(
            [],
            $stale,
            'these fields now fit their option list and should be removed from ACCEPTED_MISMATCHES: '
                . implode(', ', $stale)
        );

        $unseen = array_values(array_diff(array_keys(self::ACCEPTED_MISMATCHES), array_keys($acceptedSeen)));
        sort($unseen);
        self::assertSame(
            [],
            $unseen,
            'these ACCEPTED_MISMATCHES entries matched no field at all — a renamed form, entity or'
                . ' element, or a typo: ' . implode(', ', $unseen)
        );
    }

    /**
     * The per-library question the route-scoped fields make this suite unable to ask directly.
     *
     * `BookForm::collectionId` and `LibraryForm::mainCollectionId` are constrained to the
     * collections of one library, so the property that matters is not "is this collection in
     * some list" but "does this record point at a collection of its own library". That is a
     * join, and it is a stronger check than the generic one: it holds for every library at
     * once rather than for whichever the fixture happened to pick.
     */
    public function testNoRecordPointsAtAnotherLibrarysCollection(): void
    {
        try {
            $adapter = FormRepository::instance()->container()->get('Laminas\Db\Adapter\Adapter');
            $adapter->query('SELECT 1', []);
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }

        $checks = [
            'books whose collection_id names no collection at all'    =>
                'SELECT COUNT(*) AS c FROM lib_books b'
                . ' LEFT JOIN lib_collections c ON b.collection_id = c.CollectionId'
                . ' WHERE b.collection_id IS NOT NULL AND c.CollectionId IS NULL',
            'books in a collection belonging to a different library'  =>
                'SELECT COUNT(*) AS c FROM lib_books b'
                . ' JOIN lib_collections c ON b.collection_id = c.CollectionId'
                . ' WHERE b.library_id IS NOT NULL AND c.LibraryId <> b.library_id',
            'libraries whose main collection is another library\'s'   =>
                'SELECT COUNT(*) AS c FROM lib_libraries l'
                . ' JOIN lib_collections c ON l.MainCollectionId = c.CollectionId'
                . ' WHERE c.LibraryId <> l.LibraryId',
        ];

        $findings = [];
        foreach ($checks as $label => $sql) {
            $count = 0;
            foreach ($adapter->query($sql, []) as $row) {
                $count = (int) $row['c'];
            }
            if (0 !== $count) {
                $findings[] = sprintf('%s: %d', $label, $count);
            }
        }

        self::assertSame([], $findings, implode('; ', $findings));
    }

    /**
     * The values the specification will actually accept, or null when it constrains nothing.
     *
     * Two unwrappings are needed. A multiple select's validator is an `Explode` wrapping the
     * `InArray` — what `Laminas\Form\Element\Select::getInputSpecification()` produced and
     * therefore what `ChoiceDomain` reproduces — so the haystack sits one level down, as an
     * *instance* rather than as a nested specification, which is how `ChoiceDomain` writes
     * it. And a field can carry more than one `InArray`; a value has to pass every one, so
     * the effective domain is their intersection rather than either one.
     *
     * An empty haystack is returned as an empty list, not as null: an `InArray([])` rejects
     * every non-empty value, which is a real constraint and a finding worth reporting, not an
     * absent one.
     *
     * @param array<string, mixed> $rules one field's entry in the form specification
     * @return list<string>|null
     */
    private function effectiveHaystack(array $rules): ?array
    {
        $haystacks = [];
        foreach (Engine::validatorsFor(['field' => $rules], 'field') as $validator) {
            $haystack = self::haystackOf($validator);
            if (null !== $haystack) {
                $haystacks[] = $haystack;
            }
        }

        if ([] === $haystacks) {
            return null;
        }

        $effective = array_shift($haystacks);
        foreach ($haystacks as $further) {
            $effective = array_values(array_intersect($effective, $further));
        }
        return $effective;
    }

    /**
     * One validator specification's haystack, whichever of the three shapes it is written in.
     *
     * `ChoiceDomain` writes an `InArray` by class name with a `haystack` option, and wraps a
     * multiple select's in an `Explode` whose `validator` option is an **instance**. A
     * specification elsewhere may name either by short name, which is what the plugin
     * managers accept, so both spellings are matched.
     *
     * @param array<string, mixed> $validator
     * @return list<string>|null
     */
    private static function haystackOf(array $validator): ?array
    {
        $name    = (string) ($validator['name'] ?? '');
        $options = is_array($validator['options'] ?? null) ? $validator['options'] : [];

        if (Explode::class === $name || 'Explode' === $name) {
            $inner = $options['validator'] ?? null;
            if ($inner instanceof InArray) {
                return array_map(static fn (mixed $v): string => (string) $v, $inner->getHaystack());
            }

            return is_array($inner) ? self::haystackOf($inner) : null;
        }

        if (InArray::class !== $name && 'InArray' !== $name) {
            return null;
        }

        $haystack = $options['haystack'] ?? null;

        return is_array($haystack)
            ? array_map(static fn (mixed $v): string => (string) $v, array_values($haystack))
            : null;
    }

    /**
     * Every distinct value in a column, with pipe-joined multi-values split.
     *
     * `SionTable` stores a multiple select's value as one pipe-joined string, so the column
     * holds `en|de` where the form holds two options — splitting is what makes the comparison
     * mean the same thing on both sides.
     *
     * `BINARY` is not decoration. The schema collates utf8mb4_general_ci, so a plain
     * `SELECT DISTINCT` folds `pt` and `PT` into one row and returns whichever it met first —
     * which would let a lowercase value hide behind an uppercase one and pass a check the
     * validator, comparing in PHP, would fail. db7.9 was written without this and updated
     * nothing for the same reason.
     *
     * @return list<string>
     */
    private function distinctValues(AdapterInterface $adapter, string $table, string $column): array
    {
        $sql = sprintf(
            'SELECT DISTINCT BINARY `%s` AS v FROM `%s` WHERE `%s` IS NOT NULL AND `%s` <> \'\'',
            $column,
            $table,
            $column,
            $column
        );

        $values = [];
        foreach ($adapter->query($sql, []) as $row) {
            foreach (explode('|', (string) $row['v']) as $part) {
                if ('' !== $part) {
                    $values[$part] = true;
                }
            }
        }

        return array_keys($values);
    }
}

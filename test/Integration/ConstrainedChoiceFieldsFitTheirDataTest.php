<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Form\Element\MultiCheckbox;
use Laminas\Form\Element\Select;
use Laminas\Form\Fieldset;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Fuzz\FormGapCollector;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Service\EntitiesService;
use Throwable;

use function array_diff;
use function array_keys;
use function array_map;
use function array_slice;
use function count;
use function explode;
use function implode;
use function in_array;
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
 * - `mus_compositions.Country` holds `pt` and `cl` in lowercase against an uppercase-keyed
 *   list: 301 of 308 rows.
 * - `lib_books.lang` holds `ceb`, `p`, and `es;de;en` — three languages in a single-value
 *   column.
 *
 * Constraining any of those does not protect the column, it makes existing records
 * **unsaveable**: the moderator opens a record that has been there for years, changes a
 * comma, and the form refuses it over a field they never touched. Worse, the message points
 * at the wrong thing, because the value was not typed in this session.
 *
 * So the rule is not "add an InArray everywhere" — it is "add one where the data already
 * fits", and this test is what makes the second half checkable. Batch 11 constrained 24
 * fields and left 26 alone; four of those 26 are left alone precisely because of what this
 * test measures.
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
    /** Constrained choice fields on 2026-08-15: 22 in this repository, 2 more in JUser. */
    private const CHECKED_FLOOR = 15;

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

        $checked  = 0;
        $findings = [];

        foreach ($entities as $entity => $spec) {
            $formClass = $spec->editActionForm ?: ($spec->createActionForm ?: null);
            $form      = null === $formClass ? null : ($forms[$formClass] ?? null);
            if (! $form instanceof Fieldset || null === $spec->tableName) {
                continue;
            }

            try {
                $filterSpec = $form->getInputFilterSpecification();
            } catch (Throwable) {
                continue;
            }

            foreach ($form->getElements() as $element) {
                if (! $element instanceof Select && ! $element instanceof MultiCheckbox) {
                    continue;
                }

                $name = (string) $element->getName();
                if (! isset($filterSpec[$name], $spec->updateColumns[$name])) {
                    continue;
                }

                //Only fields that actually carry a domain check. An unconstrained one is a
                //different finding and the fuzz harness owns it.
                if (! in_array('inarray', FormGapCollector::validatorNames($filterSpec[$name]), true)) {
                    continue;
                }

                $options = array_map(
                    static fn(int|string $key): string => (string) $key,
                    array_keys($element->getValueOptions())
                );
                if ([] === $options) {
                    continue;
                }

                $checked++;

                $column = (string) $spec->updateColumns[$name];
                $stored = $this->distinctValues($adapter, (string) $spec->tableName, $column);

                $outside = array_diff($stored, $options);
                if ([] === $outside) {
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
    }

    /**
     * Every distinct value in a column, with pipe-joined multi-values split.
     *
     * `SionTable` stores a multiple select's value as one pipe-joined string, so the column
     * holds `en|de` where the form holds two options — splitting is what makes the comparison
     * mean the same thing on both sides.
     *
     * @return list<string>
     */
    private function distinctValues(AdapterInterface $adapter, string $table, string $column): array
    {
        $sql = sprintf(
            'SELECT DISTINCT `%s` AS v FROM `%s` WHERE `%s` IS NOT NULL AND `%s` <> \'\'',
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

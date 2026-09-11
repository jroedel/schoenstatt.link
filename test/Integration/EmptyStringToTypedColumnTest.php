<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\Db\Adapter\AdapterInterface;
use SionModel\Form\Element\Checkbox;
use SionModel\Form\Element\Select;
use Laminas\Form\Fieldset;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Service\EntitiesService;
use Throwable;

use function array_key_exists;
use function dirname;
use function get_debug_type;
use function implode;
use function in_array;
use function is_string;
use function sprintf;
use function strtolower;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Fuzz/FormRepository.php';

/**
 * No form may hand an empty string to a column that cannot hold one.
 *
 * ## The bug this generalises
 *
 * `/persons/create` answered HTTP 500 for any person without a spouse — which is 243 of the
 * 325 in the database — because `spousePersonId` is a `Select` with an `empty_option` and an
 * input filter of `['required' => false]` and nothing else. An unchosen spouse posts `''`,
 * `sch_persons.SpousePersonId` is an integer column, and MariaDB answers
 *
 *     22007 - 1366 - Incorrect integer value: '' for column `SpousePersonId` at row 1
 *
 * The *edit* form posted the identical value and saved, which is what kept it hidden for
 * years: `SionTable::updateEntity()` writes only the columns whose value changed, and an
 * untouched empty spouse never changed, while `createEntity()` writes every column it is
 * handed. (Not entirely hidden, as it turns out — `updateHelper()` compares with `==`, and
 * `494 == ''` is false in PHP 8, so *clearing* a spouse hit the same wall.)
 *
 * ## Why a test and not a fix note
 *
 * Because nothing in the application states the rule anywhere. The form layer knows the
 * element is optional, the database knows the column is an integer, and no code holds both
 * facts at once — so the pairing is only ever checked by a moderator meeting a 500. Every new
 * nullable-id select is a fresh chance to reintroduce it, and it will present as "create is
 * broken" long after the form was written.
 *
 * The audit that produced this file ran over the whole application first: 74 elements can put
 * an empty string somewhere non-string, and **`spousePersonId` was the only one that let it
 * through**. Every other select and every free input already carries `ToNull`, `ToInt`,
 * `ToDateTime` or a validator that refuses. That result is the reason this file asserts a
 * flat "no gaps" rather than carrying a baseline of accepted ones the way
 * `test/Fuzz/known-form-gaps.php` does — there is nothing to accept.
 *
 * ## What is in scope, and the one exclusion
 *
 * Checkboxes are excluded, and they are 38 of the 74. A `SionModel\Form\Element\Checkbox`
 * renders a hidden companion input carrying its unchecked value, so a browser posts `'0'` or
 * `'1'` and never `''` — an unfiltered checkbox over a `tinyint` is therefore not reachable
 * the way an unfiltered select is. Several of them do accept `''` when handed it directly
 * (`association.isLifeCommunity`, `person.automaticTitle`, `user-role.isDefault`), and if the
 * exclusion below is ever narrowed those become real findings rather than noise.
 *
 * Everything else is in scope, including hidden and free-text inputs: a text input left blank
 * posts `''` exactly like an unchosen select.
 *
 * ## Why it reuses the fuzz harness's container
 *
 * `FormRepository` already solves the hard part — building each form through its **real
 * factory**, with the route match and session config those factories reach for. A bare
 * `new PersonForm()` would answer this test's question about a form the application never
 * builds. The file lives in the integration suite rather than the fuzz one because its
 * subject is the form↔schema pairing and it needs a database to read `information_schema`;
 * the fuzz suite is deliberately schema-blind.
 */
final class EmptyStringToTypedColumnTest extends TestCase
{
    /**
     * Column types that can hold `''` honestly. Everything else — every integer, decimal,
     * date, time and geometry column — cannot, and MariaDB refuses rather than coercing,
     * because the server runs in strict mode.
     */
    private const STRING_TYPES = [
        'varchar',
        'char',
        'text',
        'tinytext',
        'mediumtext',
        'longtext',
        'enum',
        'set',
        'blob',
        'tinyblob',
        'mediumblob',
        'longblob',
    ];

    /**
     * A floor, in the spirit of `FormRepository::COUNT_SANITY_FLOOR`: the opposite failure
     * to the one this test is for is a refactor that makes it check nothing and pass.
     * 30 against the 36 in scope on 2026-08-15 — the 74 candidates less the 38 checkboxes.
     */
    private const CHECKED_FLOOR = 30;

    public function testNoFormCanPutAnEmptyStringInANonStringColumn(): void
    {
        $repository = FormRepository::instance();

        try {
            $container = $repository->container();
            /** @var AdapterInterface $adapter */
            $adapter = $container->get('Laminas\Db\Adapter\Adapter');
            $types   = $this->columnTypes($adapter);
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }

        if ([] === $types) {
            self::markTestSkipped('no database schema to read');
        }

        $entities = $container->get(EntitiesService::class)->getEntities();
        $built    = $repository->forms();

        $checked  = 0;
        $findings = [];

        foreach ($entities as $name => $spec) {
            //The create form where there is one, the edit form otherwise: both post the same
            //empty string, and createEntity() is only the harsher of the two paths.
            $formClass = $spec->createActionForm ?: ($spec->editActionForm ?: null);
            if (null === $formClass || null === $spec->tableName || [] === (array) $spec->updateColumns) {
                continue;
            }

            $form = $built[$formClass] ?? null;
            if (! $form instanceof Fieldset) {
                continue;
            }

            $table = strtolower((string) $spec->tableName);
            if (! isset($types[$table])) {
                continue;
            }

            foreach ($this->elements($form) as $element) {
                $property = $element->getName();
                if (! isset($spec->updateColumns[$property])) {
                    continue;
                }

                $column = (string) $spec->updateColumns[$property];
                $type   = $types[$table][strtolower($column)] ?? null;
                if (null === $type || in_array($type, self::STRING_TYPES, true)) {
                    continue;
                }

                if (! $this->canPostAnEmptyString($element)) {
                    continue;
                }

                $checked++;

                try {
                    $input = $form->getInputFilter()->get($property);
                } catch (Throwable) {
                    //An element with no input at all is a different finding, and the fuzz
                    //harness is the file that reports it.
                    continue;
                }

                $input->setValue('');
                if (! $input->isValid()) {
                    continue; //refusing the empty string is a perfectly good answer
                }

                $filtered = $input->getValue();
                if (! is_string($filtered)) {
                    continue; //null, 0, a DateTime — anything the column can hold
                }

                $findings[] = sprintf(
                    '%s.%s -> %s.%s (%s): accepts \'\' and passes it through as %s',
                    $name,
                    $property,
                    $spec->tableName,
                    $column,
                    $type,
                    get_debug_type($filtered)
                );
            }
        }

        self::assertGreaterThanOrEqual(
            self::CHECKED_FLOOR,
            $checked,
            'this test checked far fewer elements than it should — discovery is broken, not the forms'
        );

        self::assertSame(
            [],
            $findings,
            "an empty submission reaches a column that cannot hold it:\n  " . implode("\n  ", $findings)
                . "\n\nAdd a filter that turns '' into something the column accepts — `ToInt` then"
                . " `ToNull` with `TYPE_INTEGER` for a nullable id, `ToDateTime` for a date — or a"
                . ' validator that refuses the empty string outright.'
        );
    }

    /**
     * Can a browser submit this element with an empty value?
     *
     * A select can if it offers an empty option; a radio group can if one of its values is
     * empty; a checkbox never can, for the reason in the class docblock. Everything else —
     * text, date, number, hidden — posts `''` whenever it is left blank.
     */
    private function canPostAnEmptyString(mixed $element): bool
    {
        if ($element instanceof Checkbox) {
            return false;
        }

        if ($element instanceof Select) {
            return null !== $element->getEmptyOption()
                || array_key_exists('', $element->getValueOptions());
        }

        //A `Radio` branch stood here. The element model has no Radio — the census found
        //none in any form — so `Select` is the whole of the choice family now.
        return true;
    }

    /** Every element in a form, fieldsets included. */
    private function elements(Fieldset $fieldset): iterable
    {
        foreach ($fieldset->getElements() as $element) {
            yield $element;
        }

        foreach ($fieldset->getFieldsets() as $child) {
            yield from $this->elements($child);
        }
    }

    /**
     * `table => column => data type`, lower-cased on both keys.
     *
     * Read from the live schema rather than from a fixture, because the point of the test is
     * that the form and the column disagree — a fixture would only record someone's belief
     * about the column and could be as wrong as the form.
     *
     * @return array<string, array<string, string>>
     */
    private function columnTypes(AdapterInterface $adapter): array
    {
        $types = [];

        $rows = $adapter->query(
            'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE()',
            []
        );

        foreach ($rows as $row) {
            $types[strtolower((string) $row['TABLE_NAME'])][strtolower((string) $row['COLUMN_NAME'])]
                = strtolower((string) $row['DATA_TYPE']);
        }

        return $types;
    }
}

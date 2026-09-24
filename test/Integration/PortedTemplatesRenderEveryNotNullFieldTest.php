<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Controller\EntityCreateController;
use App\Controller\EntityEditController;
use SionModel\Db\Connection;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Form\Engine;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Service\EntitiesService;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Throwable;

use function array_key_exists;
use function count;
use function dirname;
use function file_exists;
use function file_get_contents;
use function implode;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function strtolower;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Fuzz/FormRepository.php';
require_once __DIR__ . '/../Form/Engine.php';

/**
 * A ported form's template must render, or carry, every field its table insists on.
 *
 * ## The failure, which has now happened twice
 *
 * A field the template never renders is a field the browser never posts — at which point
 * laminas is not neutral about it. `BaseInputFilter::setData()` gives every input it holds a
 * value whether or not the data mentions it, so an absent optional field arrives in
 * `getData()` as `null`, and `SionTable::createHelper()`/`updateHelper()` write it. Against a
 * `NOT NULL` column MariaDB refuses:
 *
 *     23000 - 1048 - Column 'DeathDatePrecision' cannot be null
 *
 * That is the whole mechanism, and it bit this port twice. Both times the form was
 * `PersonForm`, which the patres application shares, and the fields were ordination dates that
 * no page here rendered: batch 7 found it by hand on `person-edit` — **every save of a person
 * 500d** — and carried them in hidden inputs; batch 10 met it again on `/persons/create`,
 * which inherits the same partial, and only after a live probe. Those seven columns have since
 * been removed outright, which is why this test reads the `NOT NULL` set out of
 * `information_schema` rather than naming fields: the rule outlives the fields that taught
 * it.
 *
 * ## Why this test can exist and a general one cannot
 *
 * The rule needs three facts at once — which fields a form holds, which columns they write,
 * and which fields a page renders — and only the ported routes have all three in a form a test
 * can read: `config/symfony/routes.php` names the template, and a Twig template can be scanned
 * for `form.get('x')`. The laminas view scripts are `.phtml` and their partial selection is
 * decided at runtime, which is exactly why this went unnoticed there for years.
 *
 * That makes the scope a feature rather than a limitation. The ported templates are what
 * production serves; a laminas view script for a ported route is dead code.
 *
 * ## Reading a finding
 *
 * The fix is **not** to default the column, because the wrong fix is the attractive one:
 * defaulting a precision column lets the same POST's date `=> null` land, turning a loud 500
 * into a silent deletion of the value it was protecting. Round-trip it instead — render the
 * field, or carry it in a hidden input — so the save writes back what it read.
 *
 * Required fields are out of scope: their absence is a validation error the moderator sees and
 * can act on, not a write. This test is only about the silent ones.
 *
 * @see EmptyStringToTypedColumnTest for the same question asked of `''` rather than `null`
 */
final class PortedTemplatesRenderEveryNotNullFieldTest extends TestCase
{
    /** Ported routes on 2026-08-15: 11 edit, 11 create. */
    private const ROUTE_FLOOR = 15;

    public function testEveryPortedFormRendersTheFieldsItsColumnsRequire(): void
    {
        try {
            $repository = FormRepository::instance();
            $container  = $repository->container();
            /** @var Connection $adapter */
            $adapter  = $container->get('SionModel\Db\Connection');
            $required = $this->notNullColumns($adapter);
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }

        if ([] === $required) {
            self::markTestSkipped('no database schema to read');
        }

        $entities = $container->get(EntitiesService::class)->getEntities();
        $forms    = $repository->forms();

        $checked  = 0;
        $findings = [];

        foreach ($this->portedFormRoutes() as $name => [$entity, $template, $isCreate]) {
            $spec = $entities[$entity] ?? null;
            if (null === $spec || null === $spec->tableName) {
                continue;
            }

            $formClass = $isCreate
                ? ($spec->createActionForm ?: $spec->editActionForm)
                : ($spec->editActionForm ?: $spec->createActionForm);
            $form = $forms[$formClass] ?? null;
            if (null === $form) {
                continue;
            }

            $table = strtolower((string) $spec->tableName);
            if (! isset($required[$table])) {
                continue;
            }

            $markup = $this->templateClosure($template);
            if (null === $markup) {
                $findings[] = sprintf('route `%s` names template `%s`, which is not there', $name, $template);
                continue;
            }

            $checked++;

            //What the form yields when the browser posts nothing at all. Not a realistic
            //submission — it is the *worst case per field*, which is what an unrendered field
            //produces on an otherwise complete submission.
            //
            //The engine, not `Laminas\Form\Form::getInputFilter()`: the assembled filter has
            //decided nothing since #239 and does not exist since the form model landed. The
            //verdict is asked for and discarded because the engine fills its values during
            //validation rather than on demand.
            $filter   = Engine::of($form);
            $formSpec = $filter->specification();
            $filter->setData([]);
            $filter->isValid();
            $values = $filter->getValues();

            foreach ($spec->updateColumns as $property => $column) {
                if (! array_key_exists($property, $values) || null !== $values[$property]) {
                    continue;
                }
                if (! isset($required[$table][strtolower((string) $column)])) {
                    continue; //nullable, or auto_increment
                }
                if (Engine::requires($formSpec, $property)) {
                    continue; //the moderator gets a validation message, not a write
                }
                if ($this->mentions($markup, $property)) {
                    continue; //rendered, or carried in a hidden input
                }

                $findings[] = sprintf(
                    'route `%s` (%s): `%s` is never rendered by `%s` or anything it includes, so a'
                        . ' save writes NULL to %s.%s, which is NOT NULL',
                    $name,
                    $entity,
                    $property,
                    $template,
                    $spec->tableName,
                    $column
                );
            }
        }

        self::assertGreaterThanOrEqual(
            self::ROUTE_FLOOR,
            $checked,
            'far fewer ported form routes were found than exist — discovery is broken, not the templates'
        );

        self::assertSame([], $findings, "\n  " . implode("\n  ", $findings) . "\n");
    }

    /**
     * Ported create and edit routes: name => [entity, template, isCreate].
     *
     * @return iterable<string, array{string, string, bool}>
     */
    private function portedFormRoutes(): iterable
    {
        /** @var RouteCollection $routes */
        $routes = require dirname(__DIR__, 2) . '/config/symfony/routes.php';

        foreach ($routes as $name => $route) {
            if (str_ends_with((string) $name, '.locale')) {
                continue; //the locale twin carries the same defaults
            }

            /** @var Route $route */
            $defaults = $route->getDefaults();

            $entity   = $defaults[EntityCreateController::ENTITY] ?? null;
            $template = $defaults[EntityCreateController::TEMPLATE] ?? null;
            if (null !== $entity && null !== $template) {
                yield (string) $name => [(string) $entity, (string) $template, true];
                continue;
            }

            $entity   = $defaults[EntityEditController::ENTITY] ?? null;
            $template = $defaults[EntityEditController::TEMPLATE] ?? null;
            if (null !== $entity && null !== $template) {
                yield (string) $name => [(string) $entity, (string) $template, false];
            }
        }
    }

    /**
     * A template's markup plus every template it extends or includes, transitively.
     *
     * Comments are stripped first, for the reason `PortedFormsAreSubmittableTest` learned the
     * hard way: this project's templates name their own fields in prose constantly, and a
     * docblock explaining why a field is *not* rendered would otherwise prove it is.
     */
    private function templateClosure(string $name, array &$seen = []): ?string
    {
        if (isset($seen[$name])) {
            return '';
        }
        $seen[$name] = true;

        $path = dirname(__DIR__, 2) . '/templates/' . $name;
        if (! file_exists($path)) {
            return null;
        }

        $source = (string) file_get_contents($path);
        $markup = (string) preg_replace('/\{#.*?#\}/s', '', $source);

        preg_match_all("/(?:extends|include\(|source\()\s*'([^']+\.twig)'/", $markup, $matches);
        foreach ($matches[1] as $child) {
            $markup .= "\n" . ($this->templateClosure($child, $seen) ?? '');
        }

        return $markup;
    }

    /** Does this markup name the field, in any of the shapes the templates use? */
    private function mentions(string $markup, string $property): bool
    {
        $quoted = preg_quote($property, '/');

        return (bool) preg_match("/get\(\s*'$quoted'\s*\)/", $markup)
            || str_contains($markup, 'name="' . $property . '"');
    }

    /**
     * `table => column => true` for every column that refuses NULL and is not filled in for us.
     *
     * `auto_increment` columns are excluded: the key is assigned by the server and a NULL there
     * is how you ask for one.
     *
     * @return array<string, array<string, true>>
     */
    private function notNullColumns(Connection $adapter): array
    {
        $columns = [];

        $rows = $adapter->select(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS'
            . " WHERE TABLE_SCHEMA = DATABASE() AND IS_NULLABLE = 'NO' AND EXTRA NOT LIKE '%auto_increment%'",
            []
        );

        foreach ($rows as $row) {
            $columns[strtolower((string) $row['TABLE_NAME'])][strtolower((string) $row['COLUMN_NAME'])] = true;
        }

        return $columns;
    }
}

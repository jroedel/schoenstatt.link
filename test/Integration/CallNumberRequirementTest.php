<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Books\LibraryScopedForms;
use App\Laminas\ServiceBridge;
use Books\Form\BookForm;
use Laminas\ServiceManager\ServiceManager;
use PDO;
use PHPUnit\Framework\TestCase;

use function getenv;
use function sprintf;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Whether a book needs a call number follows the library, and the specification says so.
 *
 * ## Why this is worth its own file
 *
 * `lib_libraries.RequireCallNumbers` is per library and really varies — Bellavista and
 * both Austin libraries require one, Colegio Mayor and the rest do not — so this is a rule
 * that cannot be read off the form class. It was applied by reaching into the built input
 * filter:
 *
 *     $this->getInputFilter()->get('callNumber')->setRequired($libraryOptions->…);
 *
 * which made `BookForm::callNumber` the one field in the application whose validation the
 * step 5 cutover could not have carried. `SionModel\Form\Validation\InputFilter` reads
 * `getInputFilterSpecification()` and nothing else, so a rule that exists only in the
 * assembled filter disappears with `Laminas\InputFilter`.
 * `EngineMatchesAssembledFilterTest` carried it as a named exception until this landed.
 *
 * ## What is asserted
 *
 * Both directions, against two real libraries, at both layers: what the **specification**
 * says, and what the **assembled filter** does. Asserting only the assembled filter would
 * pass just as well with the old patch in place, which is the whole point — the change
 * being pinned is that the specification is now the thing that knows.
 */
final class CallNumberRequirementTest extends TestCase
{
    /** Bellavista: RequireCallNumbers = 1. */
    private const REQUIRES = 1;

    /** Colegio Mayor: RequireCallNumbers = 0. */
    private const DOES_NOT_REQUIRE = 3;

    public function testTheRequirementFollowsTheLibrary(): void
    {
        foreach ([self::REQUIRES => true, self::DOES_NOT_REQUIRE => false] as $libraryId => $expected) {
            self::assertSame(
                $expected,
                (bool) self::storedSetting($libraryId),
                sprintf('precondition: library %d RequireCallNumbers is not %s', $libraryId, var_export($expected, true))
            );

            $form = self::forms()->formForLibrary('book', $libraryId);
            self::assertInstanceOf(BookForm::class, $form);

            $spec = $form->getInputFilterSpecification();
            self::assertSame(
                $expected,
                $spec['callNumber']['required'] ?? null,
                sprintf(
                    'the SPECIFICATION does not state the requirement for library %d — a rule that '
                    . 'lives only in the assembled filter is one the replacement engine cannot carry',
                    $libraryId
                )
            );

            self::assertSame(
                $expected,
                $form->getInputFilter()->get('callNumber')->isRequired(),
                sprintf('the assembled filter disagrees for library %d', $libraryId)
            );
        }
    }

    /**
     * A form built with no library options requires nothing — which is what the
     * specification said before the requirement was ever patched in, so the fallback is a
     * reproduction rather than a new decision.
     */
    public function testAFormWithNoLibraryOptionsRequiresNothing(): void
    {
        $form = new BookForm();

        self::assertFalse($form->requiresCallNumber());
        self::assertFalse($form->getInputFilterSpecification()['callNumber']['required']);
    }

    private static function storedSetting(int $libraryId): int
    {
        $statement = self::pdo()->prepare('SELECT RequireCallNumbers FROM lib_libraries WHERE LibraryId = ?');
        $statement->execute([$libraryId]);

        return (int) $statement->fetchColumn();
    }

    private static ?ServiceManager $container = null;
    private static ?PDO $pdo                  = null;

    private static function forms(): LibraryScopedForms
    {
        return new LibraryScopedForms(ServiceBridge::around(self::container()));
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

    private static function pdo(): PDO
    {
        return self::$pdo ??= new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: 'db', getenv('DB_NAME') ?: 'ourlink_db1'),
            getenv('DB_USER') ?: 'schoenstatt',
            getenv('DB_PASS') ?: 'schoenstatt',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}

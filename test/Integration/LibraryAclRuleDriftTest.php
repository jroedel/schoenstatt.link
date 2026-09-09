<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Books\LibraryAclRules;
use App\Laminas\ContainerFactory;
use Books\Model\LibraryTable;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use Throwable;

use function is_readable;
use function usort;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Pins `App\Books\LibraryAclRules` against the method it copies,
 * `Books\Model\LibraryTable::getRules()`.
 *
 * The copy exists because the per-library rules live in `lib_libraries` rows rather than
 * in configuration, so `tools/acl-table.php` — which builds its picture from merged
 * config plus plain PDO, precisely so it survives a mid-migration `vendor/` — could not
 * report them. It said so in the output, and the consequence was that the ACL baseline
 * covered every route guard and none of the rules that actually gate the library pages.
 * Three libraries granted `checkout` to all 34 effective roles for years and no diff
 * ever showed it.
 *
 * A copy that can drift is worth less than no copy at all, because a baseline that is
 * *confidently wrong* is worse than one that admits a gap. Hence this test. It is also
 * the only check of the pair that runs the real provider, so it doubles as a guard on
 * `getRules()` itself: change the shape it returns and this fails.
 *
 * Needs a database — the rules are rows. Skips without one, the way the rest of this
 * suite does, so a bare CI runner stays green.
 */
class LibraryAclRuleDriftTest extends TestCase
{
    private static ?ServiceManager $services = null;

    /**
     * Built the way bin/console does it: load modules, never bootstrap(). Config caching
     * off for the reason AclGuardRouteDriftTest gives — a bare runner cannot write
     * data/config/, and a cache file owned by the wrong user next to a real deployment is
     * worse than a slow test.
     */
    private function services(): ServiceManager
    {
        if (null !== self::$services) {
            return self::$services;
        }

        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no local configuration; this test needs a database');
        }

        try {
            $appConfig = require __DIR__ . '/../../config/application.config.php';

            $services = ContainerFactory::build($appConfig);

            /** @var AdapterInterface $adapter */
            $adapter = $services->get('Laminas\Db\Adapter\Adapter');
            $adapter->query('SELECT 1', []);

            return self::$services = $services;
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }
    }

    /**
     * The two produce the same allow list for the same data.
     *
     * Compared as sorted lists rather than in emission order: the copy has no reason to
     * iterate libraries in the same sequence as `getObjects('library')`, and an order
     * difference is not a drift in what is granted. Everything else is compared exactly —
     * roles, resource and permission — because each of the three changes who gets in.
     */
    public function testTheReproductionMatchesTheProvider(): void
    {
        /** @var LibraryTable $table */
        $table = $this->services()->get(LibraryTable::class);

        $fromProvider = $table->getRules()['allow'] ?? [];

        $libraries = [];
        foreach ($table->getObjects('library') as $object) {
            $libraries[] = [
                'library_id'    => (int) $object['libraryId'],
                'view_role'     => $object['viewRole'] ?? null,
                'checkout_role' => $object['checkoutBooksRole'] ?? null,
            ];
        }
        $fromCopy = LibraryAclRules::asProviderRules($libraries)['allow'];

        self::assertNotSame([], $fromProvider, 'the provider returned no rules at all — no library rows?');

        $sort = static function (array &$rules): void {
            usort($rules, static fn (array $a, array $b): int => [$a[1], $a[2], $a[0]] <=> [$b[1], $b[2], $b[0]]);
        };
        $sort($fromProvider);
        $sort($fromCopy);

        self::assertEquals(
            $fromProvider,
            $fromCopy,
            'App\Books\LibraryAclRules has drifted from Books\Model\LibraryTable::getRules(). '
            . 'They are two copies of one mapping on purpose (see the class docblock); update both, '
            . 'then regenerate docs/acl-baseline.json.'
        );
    }

    /**
     * A `guest` setting admits `user` as well, and that is the whole ballgame.
     *
     * Asserted separately from the equality above because it is the single most misread
     * thing in this configuration and the equality test would still pass if *both* copies
     * dropped it. `user` is the root every library role descends from, so a library set to
     * "Public" is open to every signed-in account, not to anonymous visitors only. That is
     * deliberate on the libraries that carry it — unrecorded loans are the worse problem —
     * but it must not become deliberate by accident somewhere else.
     */
    public function testGuestAlsoAdmitsUser(): void
    {
        $rules = LibraryAclRules::forLibrary(['view_role' => 'guest', 'checkout_role' => 'guest']);

        foreach ($rules as $rule) {
            if ('administrate' === $rule['permission']) {
                continue;
            }
            self::assertSame(
                ['guest', 'user'],
                $rule['roles'],
                'a `guest` library setting must expand to [guest, user]; LibraryTable::getRules() does '
                . 'this with the comment "if it\'s free to guests, it should also be open to users"'
            );
        }

        $named = LibraryAclRules::forLibrary(['view_role' => 'lib_patres', 'checkout_role' => 'lib_patres']);
        foreach ($named as $rule) {
            if ('administrate' === $rule['permission']) {
                continue;
            }
            self::assertSame(['lib_patres'], $rule['roles'], 'only `guest` gets the extra `user`');
        }
    }

    /**
     * A null column emits no rule, which under default-deny means nobody.
     *
     * PUC and the Vaterhaus Investigation Library both carry a null `CheckoutBooksRole`.
     * The tempting reading is "unrestricted"; the true one is the opposite, and a copy
     * that emitted an empty-roles allow instead of no allow would invert it silently.
     */
    public function testANullColumnEmitsNoRule(): void
    {
        $rules = LibraryAclRules::forLibrary(['view_role' => null, 'checkout_role' => null]);

        self::assertSame(
            [['roles' => ['lib_administrator'], 'permission' => 'administrate']],
            $rules,
            'only the unconditional administrate rule should survive two null columns'
        );
    }
}

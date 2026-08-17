<?php

declare(strict_types=1);

namespace App\Books;

/**
 * The ACL rules `Books\Model\LibraryTable::getRules()` builds from `lib_libraries`,
 * expressed over plain rows so something other than a booted laminas application can
 * ask what they are.
 *
 * **Why this exists.** Those rules are the real authorization on every library page —
 * `checkout` is what `CheckoutsController::createAction()` asks about before showing the
 * lending form, `show` is what `LibrariesController::showAction()` asks about — and they
 * live in database rows, so `tools/acl-table.php` could not list them. The ACL baseline
 * was therefore silently incomplete: `docs/acl-rules.md` said in so many words that no
 * config-derived table could show them, and nothing else was looking. Three libraries
 * granted `checkout` to every signed-in account for years without that ever appearing in
 * a diff. Reproducing the mapping here is what lets the baseline cover them.
 *
 * **This is a copy, and copies drift.** `test/Integration/LibraryAclRuleDriftTest` builds
 * the real `LibraryTable` and fails if its `getRules()` and this class disagree, so the
 * copy cannot quietly fall behind the original. Change one, run that test, change the
 * other.
 *
 * Deliberately *not* a refactor of `LibraryTable::getRules()` into a shared call. That
 * method is reached through bjyauthorize's provider interface on a booted application
 * with a warm cache; this one has to work from a PDO row in a CLI script while `vendor/`
 * is mid-migration. Two callers with genuinely different constraints, one behaviour,
 * pinned by a test — the same arrangement `App\Schoenstatt\AdminIndex` has.
 */
final class LibraryAclRules
{
    /**
     * A library's `checkout`/`show` role setting of `guest` also admits `user`.
     *
     * `LibraryTable::getRules()` does this in two places with the comment "if it's free
     * to guests, it should also be open to users". It is the single most misread thing
     * about this configuration: `user` is the root every library role descends from, so
     * `guest` does not mean "anonymous visitors" — it means *everyone*.
     */
    private const GUEST = 'guest';

    /** Granted `administrate` on every library, unconditionally. */
    private const ADMINISTRATOR_ROLE = 'lib_administrator';

    /**
     * The rules for one library.
     *
     * Key names are this class's own (`view_role`, `checkout_role`), not the column
     * names, because the two callers read the row differently — one through a
     * `LibraryOptions` object, one through a PDO fetch — and normalising at the edge
     * keeps that difference out of here. A null means the column is null, which emits
     * no rule at all: under default-deny that is *nobody*, not everybody.
     *
     * @param array{view_role: string|null, checkout_role: string|null} $library
     * @return list<array{roles: list<string>, permission: string}>
     */
    public static function forLibrary(array $library): array
    {
        $rules = [];

        if (null !== $library['view_role']) {
            $rules[] = [
                'roles'      => self::rolesFor($library['view_role']),
                'permission' => 'show',
            ];
        }

        if (null !== $library['checkout_role']) {
            $rules[] = [
                'roles'      => self::rolesFor($library['checkout_role']),
                'permission' => 'checkout',
            ];
        }

        $rules[] = [
            'roles'      => [self::ADMINISTRATOR_ROLE],
            'permission' => 'administrate',
        ];

        return $rules;
    }

    /**
     * @return list<string>
     */
    private static function rolesFor(string $role): array
    {
        return self::GUEST === $role ? [$role, 'user'] : [$role];
    }

    /**
     * The same rules in the shape `LibraryTable::getRules()` returns them —
     * `['allow' => [[roles, resourceId, permission], …]]` — so the drift test can
     * compare the two without either side reformatting the other.
     *
     * @param list<array{library_id: int, view_role: string|null, checkout_role: string|null}> $libraries
     * @return array{allow: list<array{0: list<string>, 1: string, 2: string}>}
     */
    public static function asProviderRules(array $libraries): array
    {
        $allow = [];

        foreach ($libraries as $library) {
            foreach (self::forLibrary($library) as $rule) {
                $allow[] = [
                    $rule['roles'],
                    'library_' . $library['library_id'],
                    $rule['permission'],
                ];
            }
        }

        return ['allow' => $allow];
    }
}

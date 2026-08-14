<?php

declare(strict_types=1);

namespace App\Sion;

use function implode;
use function preg_quote;

/**
 * The second path segments that are **actions on** a record rather than slugs **of**
 * one, and so must not be swallowed by a ported `/{sw_id}/{slug}` show route.
 *
 * ## The bug this exists to prevent, which it did not prevent the first time
 *
 * The four entity show pages ported in batch 5 are declared `/{sw_id}/{slug}` with the
 * slug constrained `[a-z0-9-]{1,200}`, copied from the laminas route. That pattern
 * matches `edit`. So on the Symfony front controller — production since 2026-08-11 —
 * `/en/SL500001C/edit` was answered by route `composition.locale` with the
 * **composition show page**, and the laminas `composition-edit` route it should have
 * bridged to was unreachable. Measured 2026-08-13 by reading the footer serving note:
 * **nine** laminas routes were affected, and the guards on all nine never ran.
 *
 *     composition-edit    composition-delete   text-edit             text-delete
 *     publication-edit    publication-delete   publication-upload-cover
 *     publication-create-new-edition           publication-copy-to-main-corpus
 *
 * `config/symfony/routes.php` had noticed the hazard for `association-edit` and
 * dismissed it in the same breath — "the slug constraint would refuse `edit` anyway
 * since the laminas pattern excludes nothing of the sort", a sentence whose two halves
 * contradict each other. The only thing protecting `association-edit` was that it is
 * declared *above* the show block.
 *
 * ## Why laminas does not have the bug, which is the part worth remembering
 *
 * It is not that the laminas constraint is tighter — it is the same string. The two
 * routers **break the tie in opposite directions**. `Laminas\Router\SimpleRouteStack`
 * keeps its routes in a `Laminas\Stdlib\PriorityList`, which for equal priority yields
 * the *last-registered* first; Symfony's `UrlMatcher` takes the *first* match in
 * declaration order. Proved against the real config rather than read off the internals:
 * `composition` is declared at `module/Books/config/module.config.php:1436` and
 * `composition-edit` at 1453, **both** patterns match `/SL500001C/edit`, and the laminas
 * router answers `composition-edit`. So porting a `/{sw_id}[/{slug}]` route without its
 * verb siblings silently inverts which one answers, and nothing about the ported route
 * looks wrong.
 *
 * That is a property of the two routers rather than of these five verbs, so it applies
 * to any laminas parent/child pair a future batch splits across front controllers.
 *
 * ## `association-delete` was a tenth case and was *not* this bug
 *
 * `/SL100319A/delete` used to answer the association show page on both front
 * controllers, and this fix deliberately changed nothing there. Its cause was different:
 * a typo in the laminas constraint, which declared `sw_id` as `SL1[0-9]{4,4}A` — the `1`
 * plus **four** digits — where every valid association identifier is `SL1[0-9]{5,5}A`
 * per `Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_REGEXS`. The route
 * therefore matched no association that existed, so the show route caught the path on
 * laminas too.
 *
 * Repaired separately on 2026-08-14, because it made a delete confirmation reachable for
 * the first time on a live site and wanted the delete path exercised rather than a digit
 * changed — see `test/Smoke/AssociationDeleteSmokeTest`. The verb is still reserved here
 * and must stay so: now that the laminas route matches, the Symfony show route swallowing
 * `/{sw_id}/delete` would be the *original* bug with a destructive page behind it.
 *
 * ## Why the list is duplicated here rather than read from the config
 *
 * Same reason `App\Locale\Locales` duplicates the `slm_locale` aliases: reading the
 * merged module configuration from `config/symfony/routes.php` would mean loading every
 * laminas module before the first route is declared, and `/_health` would start paying
 * for it. `test/Integration/ReservedVerbsTest` is what stops the two drifting — it
 * walks the laminas router config for every route whose path is `/:sw_id/<verb>` and
 * fails if this list does not cover it. Adding a laminas verb route without adding it
 * here is therefore a test failure rather than a silently swallowed page.
 */
final class ReservedVerbs
{
    /**
     * Every second segment a laminas `/:sw_id/<verb>` route claims, as of 2026-08-13:
     * five `edit` routes, five `delete` routes and three publication-only actions,
     * across thirteen route definitions.
     *
     * Sorted, because `test/Integration/ReservedVerbsTest` compares it as a set and a
     * stable order keeps the diff of an addition to one line.
     *
     * @var list<non-empty-string>
     */
    public const VERBS = [
        'copy-to-main-corpus',
        'create-new-edition',
        'delete',
        'edit',
        'upload-cover',
    ];

    /**
     * The slug constraint a `/{sw_id}/{slug}` show route should declare: the laminas
     * pattern, minus any whole segment that is one of the verbs above.
     *
     * **The `$` matters and is not decoration.** Without it the lookahead rejects any
     * slug merely *starting* with a verb — `editorial` and `deleted-scenes` are both
     * valid slugs of the shape `[a-z0-9-]{1,200}`. The slug is the final segment of
     * every route that uses this, in both the bare and the `/{_locale}` form, so
     * end-of-subject and end-of-segment are the same position here. A route that put
     * something after the slug would need `(?=/|$)` instead, and does not exist.
     */
    public static function slugPattern(string $slug = '[a-z0-9-]{1,200}'): string
    {
        $verbs = [];
        foreach (self::VERBS as $verb) {
            $verbs[] = preg_quote($verb, '#');
        }

        return '(?!(?:' . implode('|', $verbs) . ')$)' . $slug;
    }
}

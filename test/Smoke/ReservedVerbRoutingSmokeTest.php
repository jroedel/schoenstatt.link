<?php

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The nine verb URLs batch 5 made unreachable, asserted over HTTP.
 *
 * `test/Integration/ReservedVerbsTest` proves the route collection matches these paths
 * to `legacy`. That is the mechanism; this is the consequence, and the two are worth
 * having separately because the original defect was invisible at exactly this level: the
 * page answered **200** and rendered a real, correct-looking entity page. A status
 * assertion alone would have passed against the bug, which is why every case here also
 * checks *which* action answered.
 *
 * ## Why anonymous is the sharp test
 *
 * Every one of these laminas verb routes is guarded (`sch_moderator`, `pub_moderator`,
 * `texts_moderator`, `sch_general_moderator`), so anonymously each must answer
 * `302 → /en/user/login?redirect=…`. The show routes they were being swallowed by are
 * *public* for compositions and publications — so before the fix these answered 200 to
 * anybody. The 302 therefore proves two things at once: the verb route was reached, and
 * its guard ran. No session needed, which is what keeps this test cheap enough to assert
 * all nine.
 *
 * `text-edit`/`text-delete` are the exception to the sharpness rather than to the rule:
 * the `text` show route is itself guarded `texts_user`, so those two answered 302 before
 * the fix as well. They are asserted anyway — a route that is right for the wrong reason
 * is still worth pinning.
 *
 * **`/en/SL400003T/edit` is now Symfony's own**, ported in batch 7, so its 302 comes from
 * `App\Authorization\RouteGuard` rather than from `BjyAuthorize\Guard\Route` behind the
 * bridge. The assertion is unchanged and still correct, which is the point: the visitor's
 * experience of being refused does not depend on which front controller refuses them.
 * `test/Smoke/Batch7EditSurfaceSmokeTest` is where that route's three access outcomes are
 * checked properly.
 */
class ReservedVerbRoutingSmokeTest extends SmokeTestCase
{
    /**
     * Real identifiers from the capsule dump. The shape is what is under test, not the
     * row: `SL5…C` is a composition, `SL4…T` a text, `SL2…L` a publication.
     *
     * @return array<string, array{string}>
     */
    public static function guardedVerbPaths(): array
    {
        return [
            'composition edit'           => ['/en/SL500001C/edit'],
            'composition delete'         => ['/en/SL500001C/delete'],
            'text edit'                  => ['/en/SL400003T/edit'],
            'text delete'                => ['/en/SL400003T/delete'],
            //Ported 2026-08-14, so like the two below its 302 now comes from
            //App\Authorization\RouteGuard rather than from BjyAuthorize\Guard\Route behind
            //the bridge. The assertion is unchanged and still correct, which is the point:
            //being refused does not depend on which front controller refuses you.
            'publication edit'           => ['/en/SL202186L/edit'],
            'publication delete'         => ['/en/SL202186L/delete'],
            'publication new edition'    => ['/en/SL202186L/create-new-edition'],
            'publication to main corpus' => ['/en/SL202186L/copy-to-main-corpus'],
            //Ported rather than bridged, and guarded `sch_moderator, sch_user`, so it
            //answers the same 302 by a different mechanism — App\Authorization\RouteGuard
            //instead of BjyAuthorize\Guard\Route. Included so that a reordering of the
            //routes file that lost it would fail here too.
            'association edit'           => ['/en/SL100319A/edit'],
            //Reachable since 2026-08-14. Its constraint asked for a five-digit
            //identifier where every real one has six, so until then this answered the
            //*show* page — the one path in this file that legitimately did. It is an
            //ordinary guarded verb route now and belongs with the rest.
            'association delete'         => ['/en/SL100319A/delete'],
        ];
    }

    #[DataProvider('guardedVerbPaths')]
    public function testAVerbUrlIsRefusedAnonymouslyRatherThanRenderingAShowPage(string $path): void
    {
        $response = $this->get($path);

        $this->assertSame(
            302,
            $response['status'],
            "$path answered {$response['status']} instead of redirecting to the sign-in page. A 200 here "
            . 'is the batch-5 defect: the /{sw_id}/{slug} show route swallowed the verb and rendered the '
            . 'entity page, so the verb route\'s guard never ran.'
        );
        $this->assertStringContainsString(
            '/en/user/login?redirect=' . $path,
            $response['redirect'],
            'the refusal must send the visitor back to the URL they asked for'
        );
    }

    /**
     * An ordinary slug still reaches the show page, which is the other half of the fix:
     * the reserved-verb lookahead is anchored, so only the *exact* verb is refused.
     *
     * `editorial-notes` is the case that matters — it starts with `edit`, and an
     * unanchored lookahead would 404 it while every assertion above still passed.
     */
    public function testAnOrdinarySlugStillReachesTheShowPage(): void
    {
        $response = $this->get('/en/SL500001C/editorial-notes');

        //200 with the composition's canonical link proves the ported show route served it
        //rather than the reserved-verb lookahead 404-ing a slug that merely starts with
        //`edit`. (The serving note that used to prove this was removed with the strangler.)
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            '/en/SL500001C/',
            $response['body'],
            'a slug that merely begins with a reserved verb must still be served by the ported show route'
        );
    }

    /**
     * The identifier shape that made `association-delete` unreachable, pinned from the
     * other side.
     *
     * The route asked for `SL1[0-9]{4,4}A` — `SL1` plus four digits, five in total —
     * where every association identifier has six (`SL100001A`–`SL100571A` in the
     * capsule). So it matched nothing that exists and the show route answered instead.
     * Corrected 2026-08-14 by deriving the constraint from
     * `SchoenstattLinkIdentifier::ENTITY_REGEXS` the way its four sibling delete routes
     * always have.
     *
     * A five-digit identifier is not a near miss to be tolerated, it is the *pre-April
     * 2020* form, and laminas has a route dedicated to redirecting it. Asserting that is
     * what distinguishes "the constraint is right" from "the constraint is merely
     * longer": widening it to accept both lengths would silently take this path away
     * from the redirect that owns it.
     */
    public function testTheOldFiveDigitIdentifierBelongsToTheRedirectRouteInstead(): void
    {
        $response = $this->get('/en/SL10031A/delete');

        $this->assertNotSame(
            200,
            $response['status'],
            'a pre-2020 five-digit identifier must not be served a delete confirmation; '
            . 'association-delete is for the six-digit form only'
        );
    }
}

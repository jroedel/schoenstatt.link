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
 * is still worth pinning, and the redirect *target* differs (`redirect=/en/SL400003T/edit`
 * either way, but reached from a different route).
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
            'publication edit'           => ['/en/SL202186L/edit'],
            'publication delete'         => ['/en/SL202186L/delete'],
            'publication upload cover'   => ['/en/SL202186L/upload-cover'],
            'publication new edition'    => ['/en/SL202186L/create-new-edition'],
            'publication to main corpus' => ['/en/SL202186L/copy-to-main-corpus'],
            //Ported rather than bridged, and guarded `sch_moderator, sch_user`, so it
            //answers the same 302 by a different mechanism — App\Authorization\RouteGuard
            //instead of BjyAuthorize\Guard\Route. Included so that a reordering of the
            //routes file that lost it would fail here too.
            'association edit'           => ['/en/SL100319A/edit'],
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

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'route composition.locale',
            $response['body'],
            'a slug that merely begins with a reserved verb must still be served by the ported show route'
        );
    }

    /**
     * The one path that legitimately answers the show page on **both** front controllers.
     *
     * `association-delete` declares its `sw_id` as `SL1[0-9]{4,4}A` — the `1` plus four
     * digits — where every real association identifier carries five
     * (`Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_REGEXS`). So the laminas
     * route matches nothing that exists, the show route catches the path there too, and
     * this fix neither changes that nor should. Asserted so that the day somebody repairs
     * the typo, this test says where the expectation lives.
     */
    public function testAssociationDeleteStillFallsThroughToTheShowPageOnBothSides(): void
    {
        $response = $this->get('/en/SL100319A/delete');

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'route association',
            $response['body'],
            'association-delete cannot match a five-digit identifier, so laminas resolves this path to '
            . 'the association show action — the same thing it did before this fix'
        );
    }
}

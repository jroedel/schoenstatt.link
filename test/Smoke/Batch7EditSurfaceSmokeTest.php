<?php

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Batch 7, the edit surface: the entity edit forms served by
 * App\Controller\EntityEditController over App\Sion\EntityEdit.
 *
 * ## What has to be asserted here and cannot be asserted anywhere else
 *
 * These are the first ported routes that **write to the database**, so the three access
 * outcomes matter more than they did for a read-only page: a guard that quietly stopped
 * running would not merely expose a record, it would let the wrong person save one.
 * Every route therefore gets all three — anonymous, signed in without the role, signed in
 * with it — which is step 4 of "Adding a Symfony route" in docs/strangler.md.
 *
 * And every rendering assertion reads the **body**, never just the status. Two independent
 * reasons, both recorded in that document: a Twig syntax error is an empty HTTP 200, and
 * the whole batch-5 defect class was pages that answered 200 while rendering the wrong
 * thing. `SmokeTestCase::assertNotWedged()` already covers the first on every request; the
 * form-field assertions below cover the second.
 */
class Batch7EditSurfaceSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    protected function emailPrefix(): string
    {
        return 'batch7-edit-';
    }

    protected function tearDown(): void
    {
        $this->purgeAccounts();
        $this->purgeMail();
        parent::tearDown();
    }

    /**
     * Each ported edit path, the role its guard names, and a string that only its
     * *rendered form* contains.
     *
     * The marker is a field name rather than a heading on purpose: a heading would survive
     * the renderer losing every input, which is exactly the failure a form port risks.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function editPaths(): array
    {
        return [
            //`text` is guarded texts_moderator, and its form's distinctive field is the
            //markdown body — a Textarea with the CommonMark data-parser attribute.
            'text' => ['/en/SL400003T/edit', 'texts_moderator', 'name="markdownText"'],
        ];
    }

    #[DataProvider('editPaths')]
    public function testAnonymousIsSentToSignIn(string $path, string $role, string $marker): void
    {
        $response = $this->get($path);

        $this->assertSame(302, $response['status'], "$path must refuse an anonymous visitor");
        $this->assertStringContainsString(
            '/en/user/login?redirect=' . $path,
            $response['redirect'],
            'the return trip has to carry the path the visitor asked for'
        );
    }

    #[DataProvider('editPaths')]
    public function testAVisitorWithoutTheRoleIsForbidden(string $path, string $role, string $marker): void
    {
        $jar = $this->newCookieJar();
        //A bare account: registration grants sch_user and nothing else, which is not any
        //of the roles these routes name.
        $this->signIn($jar);

        $response = $this->get($path, false, $jar);

        $this->assertSame(
            403,
            $response['status'],
            "$path must answer 403 — not a redirect — to a signed-in visitor lacking $role"
        );
    }

    #[DataProvider('editPaths')]
    public function testAVisitorWithTheRoleGetsTheForm(string $path, string $role, string $marker): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, [$role]);

        $response = $this->get($path, false, $jar);

        $this->assertSame(200, $response['status'], "$path must render for a holder of $role");
        $this->assertStringContainsString(
            $marker,
            $response['body'],
            'the form itself has to be in the body — a 200 alone would pass against an empty page '
            . 'or against a renderer that emitted no inputs at all'
        );
        //The CSRF token is what makes the POST possible at all, and it comes from the
        //laminas session App\Http\SessionListener started. Its absence would make the form
        //render perfectly and every save fail.
        $this->assertStringContainsString(
            'name="security"',
            $response['body'],
            'a ported edit form must carry its CSRF token'
        );
        //Served by Symfony rather than bridged. laminas sets slm_locale on every response
        //and a ported route never does, which is the discriminator
        //ShrinesSymfonySmokeTest established.
        $this->assertStringNotContainsString(
            'slm_locale',
            $response['headers']['set-cookie'] ?? '',
            'this path must be served by the Symfony kernel, not bridged to laminas'
        );
    }
}

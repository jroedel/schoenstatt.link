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
            //`role` is guarded sch_moderator. Role 255 renders; role 1 does not, and has
            //its own test below.
            'role' => ['/en/roles/255/edit', 'sch_moderator', 'name="roleTitle"'],
            //The two lib_user routes, which carry a *per-row* check on top of the guard:
            //App\Sion\EntityEdit asks the ACL about `library_<id>` with `administrate`. The
            //throwaway account holds lib_user but no per-library grant, so these two are
            //also the batch's only cases where a route-level yes meets a row-level no —
            //which is why their expectation is the not-found redirect rather than a 200.
            //See testAPerRowCheckRefusesALibraryTheAccountDoesNotAdministrate.
            'dictionary entry' => ['/en/dictionary/1/edit', 'dict_administrator', 'name="directTranslation"'],
            //`composition` is guarded sch_moderator/sch_user. Its ChordPro field is
            //distinctive and is inside the untranslated block, so it also proves the
            //`false` argument on those rows did not stop them rendering.
            'composition' => ['/en/SL500001C/edit', 'sch_moderator', 'name="chordProSpec"'],
        ];
    }

    /**
     * The three library-scoped forms, and a field that only each *rendered* form contains.
     *
     * **These need their own test because the shared one cannot reach them.** Their guard
     * admits `lib_user`, which every account holds, but the entity's `acl_resource_id_field`
     * then demands `administrate` on `library_<id>` — so a single-role sign-in gets the
     * row-level refusal, not the form. That is asserted separately above; this signs in with
     * **every** role, which is how `tools/port-baseline.php` renders these pages too.
     *
     * ## Why this test exists at all
     *
     * `collections/collection/edit` shipped broken. Its form factory reads the route match,
     * which a Symfony route has no MvcEvent to answer, so the page was an **empty HTTP 200**
     * — and the only assertion covering it was the anonymous refusal, which passed happily.
     * `App\Books\LibraryScopedForms` is the fix; this is the assertion that would have
     * caught it, and the reason docs/strangler.md says to assert something from the body.
     *
     * @return array<string, array{string, string}>
     */
    public static function libraryScopedPaths(): array
    {
        return [
            'collection' => ['/en/collections/1/edit', 'name="callNumberRegex"'],
            'book'       => ['/en/books/18370/edit', 'name="withinLibraryId"'],
        ];
    }

    #[DataProvider('libraryScopedPaths')]
    public function testALibraryScopedFormRendersForAnAccountThatAdministratesIt(
        string $path,
        string $marker
    ): void {
        $jar   = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $response = $this->get($path, false, $jar);

        $this->assertSame(200, $response['status'], "$path must render for an account with every role");
        $this->assertStringContainsString(
            $marker,
            $response['body'],
            'the form has to be in the body. A 200 alone passed against the empty-200 wedge that '
            . 'App\\Books\\LibraryScopedForms exists to fix.'
        );
        $this->assertStringContainsString('name="security"', $response['body']);
    }

    /**
     * The book form is the only one in the batch with a `<button type="button">` and an
     * inline value read off the form object rather than an element, so both get their own
     * assertions.
     */
    public function testTheBookFormRendersItsButtonAndTheNextCallNumber(): void
    {
        $jar = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->grantEveryRole($email);

        $response = $this->get('/en/books/18370/edit', false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'name="withinLibraryId"',
            $response['body'],
            'the call-number field the button writes into has to be there'
        );
        $this->assertStringContainsString(
            '<button',
            $response['body'],
            'nextWithinLibraryId renders through form_button, not a row — a form_row here would '
            . 'emit an <input type="button"> instead'
        );
        //The inline script interpolates this as a bare JavaScript literal, so a non-numeric
        //value would be a script-injection hazard rather than a cosmetic bug. Asserting the
        //shape is asserting that the coercion happened.
        $this->assertMatchesRegularExpression(
            '/\.val\(\d+\);/',
            $response['body'],
            'next_within_library_id must reach the inline script as a bare integer'
        );
    }

    /**
     * The row-level half of the two Books routes, which no route guard can express.
     *
     * `route/collections/collection/edit` admits `lib_user`, and then the entity's
     * `acl_resource_id_field` sends `App\Sion\EntityEdit` to ask about `library_<id>` with
     * the `administrate` privilege. An account holding `lib_user` and nothing library-specific
     * therefore passes the route guard and fails the row check — and laminas answers that
     * with the *entity* branch, a flash plus a redirect to the index, not a 403.
     *
     * **`lib_user` is a default role**, so no grant is requested here: `user_role` marks
     * `lib_user`, `pub_user`, `sch_user` and `bib_user` `is_default = 1`, and registration
     * hands them to every account. Asking for it explicitly is an *error* rather than a
     * no-op — `grantRoles()` refuses when the row already exists — which is how this was
     * discovered.
     *
     * That is also why this test carries the weight it does. If every signed-in visitor
     * holds `lib_user`, then the route guard on these two Books routes means little more
     * than "signed in", and the per-row check is substantially the whole of the protection.
     * Without this assertion the route would look perfectly healthy while letting any
     * signed-in visitor edit any library's collections.
     */
    public function testAPerRowCheckRefusesALibraryTheAccountDoesNotAdministrate(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get('/en/collections/1/edit', false, $jar);

        $this->assertSame(
            302,
            $response['status'],
            'a lib_user with no grant on this collection\'s library must not reach the form'
        );
        $this->assertStringContainsString(
            '/en/libraries',
            $response['redirect'],
            'the refusal goes to the entity\'s index route, which is how editAction() answers a '
            . 'row-level denial — a 403 here would mean the route guard refused instead, and the '
            . 'row check never ran'
        );
    }

    /**
     * `/roles/1/edit` is a redirect for *everybody*, and that is not a permission problem.
     *
     * `getRole()` answers null for 142 of the 1,468 rows in `sch_roles` — role 1 hangs off
     * an association the projection filters — so `editAction()`'s not-found branch fires
     * even for an account holding every role. Measured against laminas before porting;
     * pinned here because a ported page that started *rendering* this form would be
     * inventing a row the original cannot load.
     */
    public function testARowTheProjectionFiltersIsNotFound(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $response = $this->get('/en/roles/1/edit', false, $jar);

        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString(
            '/en/roles',
            $response['redirect'],
            'the not-found branch redirects to the entity index the spec names'
        );
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

    /**
     * **Only the routes that have such a visitor.** `composition-edit` is guarded
     * `['sch_moderator', 'sch_user']` and `sch_user` is a default role, so there is no
     * signed-in visitor who lacks it — every account gets the form. That is the existing
     * rule, unchanged by the port, and the same one `association-edit` records; asserting a
     * 403 there failed, correctly, and the route is excluded rather than the expectation
     * bent.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function pathsWithARoleNotEveryoneHas(): array
    {
        $paths = self::editPaths();
        unset($paths['composition']);

        return $paths;
    }

    #[DataProvider('pathsWithARoleNotEveryoneHas')]
    public function testAVisitorWithoutTheRoleIsForbidden(string $path, string $role, string $marker): void
    {
        $jar = $this->newCookieJar();
        //A bare account holds the four default roles — lib_user, pub_user, sch_user,
        //bib_user — and none of the roles these routes name.
        $this->signIn($jar);

        $response = $this->get($path, false, $jar);

        $this->assertSame(
            403,
            $response['status'],
            "$path must answer 403 — not a redirect — to a signed-in visitor lacking $role"
        );
    }

    /**
     * The other half of that: a bare account really does reach the composition form, which
     * is worth pinning rather than leaving as an absence. If `sch_user` ever stops being a
     * default role, this fails and says where to look.
     */
    public function testAnySignedInVisitorMayEditAComposition(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get('/en/SL500001C/edit', false, $jar);

        $this->assertSame(
            200,
            $response['status'],
            'route/composition-edit names sch_user, which registration grants, so every signed-in '
            . 'visitor may edit any composition — the existing rule, not something the port introduced'
        );
        $this->assertStringContainsString('name="chordProSpec"', $response['body']);
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

<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * Batch 17: `admin/import-father`, the last HTML page laminas served, on Symfony.
 *
 * The three access outcomes docs/strangler.md asks of a restricted port, then the form
 * itself. What this file cannot do is import anybody: a valid submission is a call to the
 * Patres API, and the capsule's key is a dummy (`docker/local.docker.php`), so the option
 * list is empty here and any real import would answer 401 upstream. The outcome messages
 * are therefore characterized by reading, not by driving — see the controller — and the
 * two submissions tested are the ones that never leave the box: a blank picker and a bad
 * token.
 *
 * ## The blank picker used to be a 500
 *
 * `personId` is `required => false` and filters `''` to null, so on laminas an empty
 * submission reached `importRemotePerson(null)`, which built `/api/persons/` and threw on
 * the answer. The ported page answers a form error instead; that is the one behaviour
 * this port changes and the assertion that pins it.
 */
class ImportFatherSymfonySmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    private const PATH = '/en/admin/import-father';

    protected function emailPrefix(): string
    {
        return 'import-father-';
    }

    protected function tearDown(): void
    {
        $this->purgeAccounts();
        $this->purgeMail();
        parent::tearDown();
    }

    public function testAnAnonymousVisitorIsSentToSignIn(): void
    {
        $response = $this->get(self::PATH);

        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString('/en/user/login?redirect=' . self::PATH, $response['redirect']);
    }

    /** `sch_moderator` reaches `/admin` and is the strongest role that must *not* reach this. */
    public function testAModeratorIsForbidden(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $this->assertSame(403, $this->get(self::PATH, false, $jar)['status']);
    }

    public function testAnAdministratorGetsTheFormFromTwig(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_administrator']);

        $response = $this->get(self::PATH, false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('<h1>Import Schoenstatt Father</h1>', $response['body']);
        $this->assertStringContainsString('<select name="personId"', $response['body']);
        $this->assertStringContainsString('name="security"', $response['body']);
        $this->assertMatchesRegularExpression('/<form[^>]+method="post"/i', $response['body']);
        //selectize is what makes a several-hundred-name picker usable, and the .phtml
        //loads it; a port that forgot the asset would still pass every assertion above
        $this->assertStringContainsString('_selectize.min.js', $response['body']);
        $this->assertStringContainsString("selectize(", $response['body']);

    }

    public function testABlankPickerIsAFormErrorNotAnException(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_administrator']);

        $form = $this->get(self::PATH, false, $jar);
        $this->assertSame(
            1,
            preg_match('/name="security"[^>]*value="([^"]+)"/', $form['body'], $m),
            'could not read the CSRF token out of the form'
        );

        $posted = $this->request('POST', self::PATH, [], false, $jar, [
            'personId' => '',
            'security' => $m[1],
            'submit'   => 'Import',
        ]);

        $this->assertSame(200, $posted['status'], 'a blank picker re-renders the form (it was a 500 on laminas)');
        $this->assertStringContainsString('Please choose a person to import.', $posted['body']);
        $this->assertStringNotContainsString('Fatal error', $posted['body']);
    }

    public function testABadTokenIsReportedAndImportsNothing(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_administrator']);

        $posted = $this->request('POST', self::PATH, [], false, $jar, [
            'personId' => '1',
            'security' => 'not-a-real-token',
            'submit'   => 'Import',
        ]);

        $this->assertSame(200, $posted['status'], 'the laminas action answers 200 to an invalid form, and so does this');
        $this->assertStringContainsString('Error in form submission, please review.', $posted['body']);
    }
}

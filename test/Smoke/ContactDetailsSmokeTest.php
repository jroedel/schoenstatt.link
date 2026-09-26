<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use function preg_match_all;

/**
 * Who sees a person's email address and telephone number on the contact lists.
 *
 * Registration is open and every new account holds `sch_user`, which is what the contact
 * search is guarded by — so "signed in" means "anyone who typed an address", and the search
 * handed that person every assigned person's email and phone. Only whoever may open the
 * person record (`route/persons/person`) may see them now; `_assignments-table.html.twig`
 * decides it, for every list at once. Counted, never printed: these are real addresses in
 * the capsule.
 */
class ContactDetailsSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** Distinct from the other suites' prefixes, or one class's tearDown deletes another's accounts. */
    private const EMAIL_PREFIX = 'contact-details-smoke-';

    /** A blank query lists every assignment (AssignmentSearchController's docblock). */
    private const SEARCH = '/en/assignments/search';

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    public function testANewAccountSeesNoEmailOrTelephone(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get(self::SEARCH, false, $jar);

        $this->assertSame(200, $response['status']);
        $body = $response['body'];
        $this->assertGreaterThan(
            0,
            $this->occurrences('#<td><a href="/en/persons/\d+"#', $body),
            'the search lists nobody'
        );
        $this->assertSame(0, $this->occurrences('#href="mailto:#', $body), 'an email address reached a new account');
        $this->assertSame(0, $this->occurrences('#href="tel:#', $body), 'a telephone number reached a new account');
    }

    public function testAModeratorStillDoes(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $response = $this->get(self::SEARCH, false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertGreaterThan(0, $this->occurrences('#href="mailto:#', $response['body']));
        $this->assertGreaterThan(0, $this->occurrences('#href="tel:#', $response['body']));
    }

    /**
     * The one public page with a contact list is a shrine's. An anonymous visitor gets the
     * policy's "first name and the initials of the last name", and no link to a record they
     * cannot open.
     */
    public function testAnAnonymousVisitorSeesInitialsOnAShrinePage(): void
    {
        $shrine = $this->pdo()->query(
            "SELECT s.AssociationId FROM sch_assignments a JOIN sch_roles r ON r.RoleId = a.RoleId
             JOIN sch_associations s ON s.AssociationId = r.AssociationId
             WHERE s.Kind IN ('sch-shrine', 'sch-wayside-shrine') LIMIT 1"
        )->fetchColumn();
        if (false === $shrine) {
            $this->markTestSkipped('no shrine in this database has a contact person');
        }

        $response = $this->get('/en/associations/' . $shrine, true);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('assignmentsPanel', $response['body']);
        $body = $response['body'];
        $this->assertSame(
            0,
            $this->occurrences('#href="[^"]*/persons/\d+"#', $body),
            'a person link reached an anonymous visitor'
        );
        $this->assertGreaterThan(
            0,
            $this->occurrences('#<td>[^<>]*\b\p{Lu}\.\s*</td>#u', $body),
            'no initials on the page'
        );
    }

    private function occurrences(string $pattern, string $body): int
    {
        return (int) preg_match_all($pattern, $body);
    }
}

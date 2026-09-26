<?php

declare(strict_types=1);

namespace App\Privacy;

use DateTimeImmutable;
use DateTimeZone;
use SionModel\Db\Connection;

use function array_values;
use function iterator_to_array;

/**
 * Everything this application holds about one person or one account, as plain arrays for a
 * JSON export — the privacy policy's right of access.
 *
 * A person is the record in the register (`sch_persons`); an account is someone who signs
 * in (`user`). They are linked by `user.PersID`, and an export of either includes the other.
 * docs/privacy.md lists what each section reads.
 *
 * **What is left out, and why.** The sign-in token and its expiry (a live credential, not
 * data about the person), a borrower link's hash for the same reason, and the edits an
 * account made to *other* records, which are summarised per entity rather than listed — they
 * are about those records, and a moderator's would run to six figures.
 */
final class PersonalData
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @return array<string, mixed>|null null when there is no such person
     */
    public function forPerson(int $personId): ?array
    {
        $person = $this->db->select('SELECT * FROM sch_persons WHERE PersonId = ?', [$personId])->current();
        if (null === $person) {
            return null;
        }

        $accounts = [];
        foreach ($this->rows('SELECT user_id FROM user WHERE PersID = ?', [$personId]) as $row) {
            $accounts[] = $this->account((int) $row['user_id']);
        }

        return $this->envelope([
            'person'       => $person,
            'assignments'  => $this->rows(
                'SELECT a.AssignmentId, r.RoleTitle, s.AssociationName, a.StartDate, a.EndDate
                 FROM sch_assignments a
                 LEFT JOIN sch_roles r ON r.RoleId = a.RoleId
                 LEFT JOIN sch_associations s ON s.AssociationId = r.AssociationId
                 WHERE a.PersonId = ? ORDER BY a.StartDate',
                [$personId]
            ),
            'loans'        => $this->rows(
                'SELECT l.LibraryName, b.title, b.author, c.CheckedOutOn, c.DueOn, c.TimesRenewed,
                        c.LastRenewedOn, c.CheckedInOn, c.AdminNotes
                 FROM lib_checkouts c
                 LEFT JOIN lib_books b ON b.book_id = c.BookId
                 LEFT JOIN lib_libraries l ON l.LibraryId = b.library_id
                 WHERE c.PersonId = ? ORDER BY c.CheckedOutOn',
                [$personId]
            ),
            'borrowerLinks' => $this->rows(
                'SELECT LibraryId, CreatedOn, ExpiresOn, LastUsedOn, RevokedOn
                 FROM lib_borrower_tokens WHERE PersonId = ? ORDER BY CreatedOn',
                [$personId]
            ),
            'relationships' => $this->rows(
                "SELECT r.PredicateKind, r.SubjectEntityId, r.ObjectEntityId, r.PublicNotes
                 FROM relationships r JOIN predicates p ON p.PredicateKind = r.PredicateKind
                 WHERE (p.SubjectEntityKind = 'person' AND r.SubjectEntityId = ?)
                    OR (p.ObjectEntityKind = 'person' AND r.ObjectEntityId = ?)",
                [$personId, $personId]
            ),
            'publicationsNamingThem' => $this->rows(
                'SELECT PublicationId, Title FROM sch_publications WHERE Authors REGEXP ?',
                [AuthorLinks::pattern($personId)]
            ),
            'sources'      => $this->rows(
                "SELECT FieldGroup, SourceClass, SourceUrl, SourceNote, AssertedOn, RecordedOn
                 FROM sch_provenance WHERE Entity = 'person' AND EntityId = ? ORDER BY RecordedOn",
                [$personId]
            ),
            'recordHistory' => $this->rows(
                "SELECT ChangedField, OldValue, NewValue, UpdatedOn FROM sch_changes
                 WHERE ChangedEntity = 'person' AND ChangedIDValue = ? ORDER BY UpdatedOn",
                [$personId]
            ),
            'accounts'     => $accounts,
        ]);
    }

    /**
     * @return array<string, mixed>|null null when there is no such account
     */
    public function forAccount(int $userId): ?array
    {
        $row = $this->db->select('SELECT PersID FROM user WHERE user_id = ?', [$userId])->current();
        if (null === $row) {
            return null;
        }
        $personId = (int) ($row['PersID'] ?? 0);
        if ($personId > 0) {
            $export = $this->forPerson($personId);
            if (null !== $export) {
                return $export;
            }
        }

        return $this->envelope(['accounts' => [$this->account($userId)]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function account(int $userId): array
    {
        return [
            'account'   => $this->db->select(
                'SELECT user_id, username, email, display_name, create_datetime, update_datetime,
                        state, lang, email_verified, PersID
                 FROM user WHERE user_id = ?',
                [$userId]
            )->current(),
            'roles'     => $this->rows(
                'SELECT r.role_id FROM user_role_linker l JOIN user_role r ON r.id = l.role_id
                 WHERE l.user_id = ? ORDER BY r.role_id',
                [$userId]
            ),
            'apiTokens' => $this->rows(
                'SELECT label, issued_on, expires_on, revoked_on FROM user_api_token
                 WHERE user_id = ? ORDER BY issued_on',
                [$userId]
            ),
            'comments'  => $this->rows(
                'SELECT CommentKind, Rating, Comment, Status, CreatedOn FROM comments
                 WHERE CreatedBy = ? ORDER BY CreatedOn',
                [$userId]
            ),
            'editsMade' => $this->rows(
                'SELECT ChangedEntity, COUNT(*) AS edits, MIN(UpdatedOn) AS first, MAX(UpdatedOn) AS last
                 FROM sch_changes WHERE UpdatedBy = ? GROUP BY ChangedEntity ORDER BY ChangedEntity',
                [$userId]
            ),
        ];
    }

    /**
     * @param array<string, mixed> $sections
     * @return array<string, mixed>
     */
    private function envelope(array $sections): array
    {
        return [
            'exportedOn' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            'source'     => 'schoenstatt.link',
            'about'      => 'Everything schoenstatt.link holds about you. See /en/privacy.',
        ] + $sections;
    }

    /**
     * @param list<int|string> $values
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $values): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = array_values(iterator_to_array($this->db->select($sql, $values), false));

        return $rows;
    }
}

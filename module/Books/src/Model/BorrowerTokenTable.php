<?php

declare(strict_types=1);

namespace Books\Model;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use SionModel\Db\Connection;

use function bin2hex;
use function hash;
use function is_string;
use function preg_match;
use function random_bytes;
use SionModel\Db\Sql\Delete;
use SionModel\Db\Sql\Insert;
use SionModel\Db\Sql\Select;
use SionModel\Db\Sql\Update;
use SionModel\Db\Sql\Predicate\Operator;

/**
 * Scoped links that let a borrower see and renew their own books without an account.
 *
 * ## Why not a user account and JUser's magic link
 *
 * JUser already has a passwordless sign-in and reusing it would have been less code.
 * It was rejected on blast radius. This database has **no user-to-person link at
 * all** — `user` holds `user_id`, `email`, `email_verified`, `multi_person_user` and
 * nothing else — so "which borrower is this account?" has no answer to give. And
 * every account inherits `lib_user`, which is `is_default = 1`, so it means "any
 * signed-in account" rather than any particular person: handing borrowers accounts
 * would have handed each of them the whole library area, including every other
 * borrower's checkout list.
 *
 * A token here authorises exactly one person's checkouts at one library. If one
 * leaks, what leaks is one person's book list and the ability to extend their own
 * due dates. That is the whole grant.
 *
 * ## Shape
 *
 * 32 random bytes, hex — the same size JUser\Service\LoginTokenService uses for its
 * web tokens. Stored as a sha256 hex digest and never in the clear, so the row is
 * enough to check a presented link and useless for forging one.
 *
 * **Not single-use**, which is the one place it deliberately differs from a
 * magic-link sign-in. The borrower is expected to open the mail, look at the list,
 * and renew — perhaps over several days, perhaps after replying to the librarian
 * first. Burning the token on first view would make the second click a dead end and
 * generate exactly the confused reply the email is trying to avoid. It expires
 * instead, and a librarian can revoke one early.
 */
class BorrowerTokenTable
{
    /** Matches JUser\Service\LoginTokenService::WEB_TOKEN_BYTES. */
    public const TOKEN_BYTES = 32;

    /** How long a link in an overdue notice keeps working. */
    public const LIFETIME = 'P30D';

    public function __construct(private readonly Connection $adapter)
    {
    }

    /**
     * Mint a link for one person at one library and return the plaintext.
     *
     * The plaintext is returned once and never stored; only its digest is written.
     */
    public function issue(int $personId, int $libraryId, ?DateTimeImmutable $now = null): string
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        $insert = (new Insert('lib_borrower_tokens'))->values([
            'TokenHash' => hash('sha256', $token),
            'PersonId'  => $personId,
            'LibraryId' => $libraryId,
            'CreatedOn' => $now->format('Y-m-d H:i:s'),
            'ExpiresOn' => $now->add(new DateInterval(self::LIFETIME))->format('Y-m-d H:i:s'),
        ]);
        $this->adapter->execute($insert);

        return $token;
    }

    /**
     * Resolve a presented token, or null if it is unknown, expired or revoked.
     *
     * @return array{personId: int, libraryId: int}|null
     */
    public function redeem(string $token, ?DateTimeImmutable $now = null): ?array
    {
        //A malformed token never reaches the database. It cannot match anything, and
        //hashing arbitrary request input to go looking is work an unauthenticated
        //caller should not be able to ask for by the thousand.
        if (1 !== preg_match('/^[0-9a-f]{' . (self::TOKEN_BYTES * 2) . '}$/', $token)) {
            return null;
        }
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $select = (new Select('lib_borrower_tokens'))
            ->columns(['PersonId', 'LibraryId'])
            ->where([
                'TokenHash'   => hash('sha256', $token),
                'RevokedOn'   => null,
            ]);
        $select->where(new Operator('ExpiresOn', Operator::GT, $now->format('Y-m-d H:i:s')));
        $row = $this->adapter->select($select)->current();

        if (! is_array($row) || ! isset($row['PersonId'], $row['LibraryId'])) {
            return null;
        }

        $update = (new Update('lib_borrower_tokens'))
            ->set(['LastUsedOn' => $now->format('Y-m-d H:i:s')])
            ->where(['TokenHash' => hash('sha256', $token)]);
        $this->adapter->execute($update);

        return ['personId' => (int) $row['PersonId'], 'libraryId' => (int) $row['LibraryId']];
    }

    /**
     * Revoke every live link for a person, e.g. when a librarian is told an address
     * was wrong or a mailbox is shared.
     */
    public function revokeForPerson(int $personId, ?DateTimeImmutable $now = null): int
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $update = (new Update('lib_borrower_tokens'))
            ->set(['RevokedOn' => $now->format('Y-m-d H:i:s')])
            ->where(['PersonId' => $personId, 'RevokedOn' => null]);

        return $this->adapter->execute($update);
    }

    /**
     * Delete tokens that expired more than a month ago. `bin/console privacy:retention
     * --apply` calls this; docs/privacy.md.
     */
    public function pruneExpired(?DateTimeImmutable $now = null): int
    {
        $delete = new Delete('lib_borrower_tokens');
        $delete->where(new Operator('ExpiresOn', Operator::LT, self::pruneBefore($now)));

        return $this->adapter->execute($delete);
    }

    /** How many rows {@see pruneExpired()} would delete, for a dry run. */
    public function countPrunable(?DateTimeImmutable $now = null): int
    {
        $row = $this->adapter->select(
            'SELECT COUNT(*) AS n FROM lib_borrower_tokens WHERE ExpiresOn < ?',
            [self::pruneBefore($now)]
        )->current();

        return (int) ($row['n'] ?? 0);
    }

    private static function pruneBefore(?DateTimeImmutable $now): string
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $now->sub(new DateInterval('P30D'))->format('Y-m-d H:i:s');
    }
}

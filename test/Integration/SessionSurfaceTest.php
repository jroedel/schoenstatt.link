<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Session\RecordedSession;
use SionModel\Messaging\FlashMessages;
use SionModel\Session\PhpSession;
use SionModel\Validator\Csrf;

use function ksort;
use function unserialize;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Session/RecordedSession.php';

/**
 * Everything laminas-session wrote is still readable.
 *
 * `test/Session/session-surface.php` is the recording, taken while the package was
 * installed; these are the assertions it exists for. A visitor's cookie lives 30 days, so
 * a reader that misses one of these shapes signs that visitor out for a month —
 * `App\Http\SessionListener` records what that looked like on 2026-08-07, when every
 * ported route answered with an empty 200 for the life of the cookie.
 *
 * The recording is bytes, not values, because laminas stored **objects**: each namespace
 * is a serialised `Laminas\Stdlib\ArrayObject` and a flash queue inside one is an
 * `SplQueue`.
 */
final class SessionSurfaceTest extends TestCase
{
    /** @return array<string, array{session: string, values: array<string, mixed>}> */
    private static function recording(): array
    {
        /** @var array<string, array{session: string, values: array<string, mixed>}> $recorded */
        $recorded = require __DIR__ . '/../Session/session-surface.php';

        return $recorded;
    }

    /** @return iterable<string, array{string}> */
    public static function cases(): iterable
    {
        foreach (self::recording() as $label => $_) {
            yield $label => [$label];
        }
    }

    /**
     * Restore a recorded session into `$_SESSION`, which is where PhpSession reads it —
     * exactly as `Laminas\Session\Storage\SessionArrayStorage` did.
     *
     * @return array<string, mixed>
     */
    private function restore(string $label): array
    {
        $case = self::recording()[$label];

        /** @var array<string, mixed> $session */
        $session  = unserialize($case['session']);
        $_SESSION = $session;

        /** @var array<string, mixed> $values */
        $values = $case['values'];

        return $values;
    }

    /**
     * The flash cases, read back through the class that wrote them.
     *
     * A later request is simulated by giving PhpSession an access time after the recorded
     * one — which is what makes the hop real: one hop means the next request reads the
     * message and the one after it does not.
     */
    #[DataProvider('cases')]
    public function testEveryRecordedSessionIsStillReadable(string $label): void
    {
        $expected = $this->restore($label);

        $session = new PhpSession(RecordedSession::NOW + 1);

        //ONE FlashMessages, because the first use of any instance moves every namespace
        //out of the session. A fresh reader per namespace would drain the store on the
        //first call and report nothing for the rest — which is the bug its own docblock
        //describes, and which this test reproduced before it was written this way.
        $recovered = RecordedSession::describe(
            (new FlashMessages($session->bag(FlashMessages::CONTAINER)))->all()
        );

        foreach (self::nonFlashKeys($expected) as $key) {
            $recovered[$key] = RecordedSession::describe(
                $session->bag(self::namespaceFor($key))->get($key)
            );
        }

        //Sorted by key: which namespaces come back is a contract, the order they are
        //traversed in is not. The order of messages *within* a namespace is, and that is
        //preserved by the lists themselves.
        ksort($expected);
        ksort($recovered);

        self::assertSame($expected, $recovered, 'a recorded session no longer reads back');
    }

    /**
     * A CSRF token recorded by laminas still validates, which is the one that would be
     * invisible: a wrong answer here rejects every form submitted across the deploy.
     */
    public function testACsrfTokenWrittenByLaminasStillValidates(): void
    {
        $this->restore('csrf: one token');

        $session = new PhpSession(RecordedSession::NOW + 1);

        $csrf = new Csrf();
        $csrf->setName('csrf');
        $csrf->setSession($session->bag('Laminas_Validator_Csrf_salt_csrf'));

        self::assertTrue(
            $csrf->isValid('deadbeef-abc123'),
            'a token this session issued under laminas-session is no longer accepted, so '
            . 'every form submitted across the deploy would be refused'
        );
        self::assertFalse($csrf->isValid('deadbeef-nosuchtoken'), 'an unknown token id must still be refused');
    }

    /** @param array<string, mixed> $expected @return list<string> */
    private static function nonFlashKeys(array $expected): array
    {
        $keys = [];
        foreach ($expected as $key => $_) {
            if (! in_array($key, ['success', 'default', 'warning', 'error', 'info'], true)) {
                $keys[] = (string) $key;
            }
        }

        return $keys;
    }

    private static function namespaceFor(string $key): string
    {
        return match ($key) {
            'storage'            => 'Laminas_Auth',
            'redirect'           => 'JUser',
            'token'              => 'JUser\ApiToken',
            'tokenList', 'hash'  => 'Laminas_Validator_Csrf_salt_csrf',
            default              => 'JUser',
        };
    }
}

<?php

declare(strict_types=1);

namespace SchoenstattTest\Session;

use JTranslate\I18n\TranslatableMessage;
use JUser\Authentication\SessionIdentity;
use JUser\Page\SignIn;
use JUser\Page\UserAdmin;
use Laminas\Session\Container;
use Laminas\Session\SessionManager;
use Laminas\Session\Storage\SessionArrayStorage;
use SplQueue;

use function get_class;
use function is_array;
use function is_object;
use function is_scalar;
use function iterator_to_array;
use function serialize;
use function sprintf;

/**
 * What this application actually stores in `$_SESSION`, recorded from laminas-session
 * while the package is still installed.
 *
 * ## Why this is bytes and not values
 *
 * laminas-session does not store arrays. Every namespace is a serialised
 * `Laminas\Stdlib\ArrayObject`, alongside a `__Laminas` block holding the expiry metadata
 * and a `_VALID` entry naming `Laminas\Session\Validator\Id`. A replacement that cannot
 * read that **signs every visitor out**, and `cookie_lifetime` is 30 days, so the tail is
 * 30 days long.
 *
 * That is not hypothetical here. `App\Http\SessionListener`'s docblock records 2026-08-07:
 * sessions written before the Zend -> Laminas class renames unserialised to
 * `__PHP_Incomplete_Class` and every ported route answered with an empty 200, for the life
 * of the cookie. This recording exists so that the same thing cannot happen on the way out.
 *
 * So each case records `serialize($_SESSION)` — the bytes PHP's session handler writes —
 * plus the values a reader has to recover from them. The first half is the compatibility
 * contract; the second is what the code is for.
 *
 * ## The four namespaces are the real ones
 *
 * `FlashMessenger` (SionModel), `Laminas_Auth` (the identity, frozen in
 * `JUser\Authentication\SessionIdentity`), `JUser` and `JUser\ApiToken`. The CSRF
 * namespace is per-form and generated, so one representative is recorded.
 */
require_once __DIR__ . '/RecordedSession.php';

const FLASH_CONTAINER = 'FlashMessenger';

final class SessionSurface
{
    /** @return array<string, array{session: string, values: array<string, mixed>}> */
    public static function collect(): array
    {
        $cases = [];

        foreach (self::cases() as $label => $build) {
            //writeClose() marks the storage immutable, and SessionArrayStorage *is* the
            //global $_SESSION — so without this the second case inherits the first's
            //closed storage and throws on its own access-time stamp.
            $_SESSION = [];

            $manager = new SessionManager(null, new SessionArrayStorage());
            $manager->start();

            $values = $build($manager);

            $manager->writeClose();

            $cases[$label] = [
                'session' => serialize(self::sessionArray()),
                'values'  => $values,
            ];
        }

        return $cases;
    }

    /**
     * @return array<string, callable(SessionManager): array<string, mixed>>
     */
    private static function cases(): array
    {
        return [
            'empty' => static fn (SessionManager $m): array => [],

            'flash: one message' => static function (SessionManager $m): array {
                self::flash($m, ['success' => ['You are signed in.']]);

                return ['success' => ['You are signed in.']];
            },

            'flash: every namespace' => static function (SessionManager $m): array {
                $messages = [];
                foreach (['default', 'success', 'warning', 'error', 'info'] as $namespace) {
                    $messages[$namespace] = ['a ' . $namespace . ' message'];
                }
                self::flash($m, $messages);

                return $messages;
            },

            'flash: two in one namespace' => static function (SessionManager $m): array {
                self::flash($m, ['success' => ['You are signed in.', 'but not there']]);

                return ['success' => ['You are signed in.', 'but not there']];
            },

            //The case that makes this bytes rather than strings: a flash message may be a
            //TranslatableMessage, so an OBJECT crosses the request boundary inside the
            //SplQueue. Anything that reads the session has to survive that.
            'flash: a translatable message' => static function (SessionManager $m): array {
                self::flash($m, ['error' => [new TranslatableMessage('Not found', [], 'JUser')]]);

                return ['error' => ['JTranslate\\I18n\\TranslatableMessage(Not found @ JUser)']];
            },

            'csrf: one token' => static function (SessionManager $m): array {
                $session = new Container('Laminas_Validator_Csrf_salt_csrf', $m);
                $session->setExpirationSeconds(300);
                $session->tokenList = ['abc123' => 'deadbeef'];
                $session->hash      = 'deadbeef-abc123';

                return ['tokenList' => ['abc123' => 'deadbeef'], 'hash' => 'deadbeef-abc123'];
            },

            'csrf: two tokens' => static function (SessionManager $m): array {
                $session = new Container('Laminas_Validator_Csrf_salt_csrf', $m);
                $session->setExpirationSeconds(300);
                $session->tokenList = ['abc123' => 'deadbeef', 'def456' => 'cafebabe'];
                $session->hash      = 'cafebabe-def456';

                return [
                    'tokenList' => ['abc123' => 'deadbeef', 'def456' => 'cafebabe'],
                    'hash'      => 'cafebabe-def456',
                ];
            },

            //The one that must never break: the identity. A wrong answer here signs every
            //visitor out on deploy.
            'identity' => static function (SessionManager $m): array {
                $session = new Container(SessionIdentity::SESSION_NAMESPACE, $m);
                $session->storage = 42;

                return ['storage' => 42];
            },

            'juser: post-sign-in destination' => static function (SessionManager $m): array {
                $session = new Container(SignIn::SESSION_NAMESPACE, $m);
                $session->redirect = '/en/admin';

                return ['redirect' => '/en/admin'];
            },

            'juser: a freshly issued api token' => static function (SessionManager $m): array {
                $session = new Container(UserAdmin::TOKEN_NAMESPACE, $m);
                $session->token = 'eyJhbGciOiJIUzI1NiJ9.e30.signature';

                return ['token' => 'eyJhbGciOiJIUzI1NiJ9.e30.signature'];
            },

            'two namespaces at once' => static function (SessionManager $m): array {
                $identity = new Container(SessionIdentity::SESSION_NAMESPACE, $m);
                $identity->storage = 7;
                self::flash($m, ['info' => ['welcome back']]);

                return ['storage' => 7, 'info' => ['welcome back']];
            },
        ];
    }

    /**
     * Write a flash container exactly as laminas-mvc's `FlashMessenger` plugin did.
     *
     * Through laminas' own `Container` rather than through
     * `SionModel\\Messaging\\FlashMessages`, which is the class this recording exists to
     * hold to account: a recording taken through the code under test would only prove that
     * the code agrees with itself. One `SplQueue` per severity, one hop of expiration set
     * on the container, which is the format sitting in every live session.
     *
     * @param array<string, list<mixed>> $messages
     */
    private static function flash(SessionManager $manager, array $messages): void
    {
        $container = new Container(FLASH_CONTAINER, $manager);
        $container->setExpirationHops(1, null);

        foreach ($messages as $namespace => $queued) {
            $queue = new SplQueue();
            foreach ($queued as $message) {
                $queue->push($message);
            }
            $container->{$namespace} = $queue;
        }
    }

    /**
     * `$_SESSION` as a plain array, which is what PHP's handler serialises.
     *
     * The `_REQUEST_ACCESS_TIME` and `_VALID` entries move on every run — a timestamp and
     * a hash of the session id — so they are replaced with fixed values. Everything a
     * reader has to understand is kept; nothing that only records *when* the recording was
     * taken is.
     *
     * @return array<string, mixed>
     */
    private static function sessionArray(): array
    {
        /** @var array<string, mixed> $session */
        $session = (array) $_SESSION;

        if (isset($session['__Laminas']) && is_array($session['__Laminas'])) {
            $meta = $session['__Laminas'];
            if (isset($meta['_REQUEST_ACCESS_TIME'])) {
                $meta['_REQUEST_ACCESS_TIME'] = (float) RecordedSession::NOW;
            }
            if (isset($meta['_VALID'])) {
                $meta['_VALID'] = ['Laminas\Session\Validator\Id' => 'RECORDED'];
            }
            foreach ($meta as $key => $value) {
                if (is_array($value) && isset($value['EXPIRE_HOPS']['ts'])) {
                    $value['EXPIRE_HOPS']['ts'] = (float) RecordedSession::NOW;
                    $meta[$key]                 = $value;
                }
                if (is_array($value) && isset($value['EXPIRE'])) {
                    $value['EXPIRE'] = RecordedSession::NOW + 300;
                    $meta[$key]      = $value;
                }
            }
            $session['__Laminas'] = $meta;
        }

        return $session;
    }
}

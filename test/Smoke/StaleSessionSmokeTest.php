<?php

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Regression for the 2026-08-03 production incident (exception fingerprints
 * 1615c790/35c198d9): a session written before the Zend → Laminas migration
 * holds a serialized Zend\Stdlib\ArrayObject, which now unserializes as
 * __PHP_Incomplete_Class. laminas-session's Container::verifyNamespace()
 * rejects it, the flash messenger in the layout hits it on every page, the
 * error page dies of the same poison, and the visitor gets a blank response
 * on every request for the 30-day session lifetime.
 *
 * JUser\Session\SessionPruner (called from JUser\Module::onBootstrap) drops
 * exactly those values, so the page renders and the rewritten session is
 * clean again.
 *
 * The test plants a poisoned session file directly in the server's session
 * save path — possible only because the suite runs inside the container that
 * serves the app, hence the guards.
 *
 * **Both front controllers have to be driven, and until 2026-08-07 only one
 * was.** `onBootstrap` never runs for a Symfony-served route, so every ported
 * HTML page reproduced the original incident exactly: /en/shrines and
 * /en/wayside-shrines returned an empty 200 to a poisoned session for as long
 * as they had been ported. This test did not notice because the only path it
 * asked for, `/en/`, was laminas-served at the time — and then `welcome` was
 * ported and it started failing, which is how the gap was found.
 * App\Http\SessionListener is the fix; the paths below are what keep it honest,
 * one served by each front controller.
 */
class StaleSessionSmokeTest extends SmokeTestCase
{
    /**
     * A path from each front controller. `/en/` and `/en/shrines` are ported;
     * `/en/timeline` is not, so it still goes through App\Http\LegacyBridge and
     * JUser\Module::onBootstrap. The property is the same for all three, and it is
     * the *pair* that matters: a fix applied to only one side would pass one case.
     *
     * @return array<string, array{0: string}>
     */
    public static function paths(): array
    {
        return [
            'symfony: the front page'   => ['/en/'],
            'symfony: the shrine index' => ['/en/shrines'],
            'laminas: the timeline'     => ['/en/timeline'],
        ];
    }

    #[DataProvider('paths')]
    public function testPreMigrationSessionStillGetsAPage(string $path): void
    {
        $host = (string) parse_url($this->baseUrl(), PHP_URL_HOST);
        if (! in_array($host, ['localhost', '127.0.0.1'], true)) {
            $this->markTestSkipped('needs filesystem access to the server session store');
        }
        $savePath = ini_get('session.save_path') ?: sys_get_temp_dir();
        if (! is_dir($savePath) || ! is_writable($savePath)) {
            $this->markTestSkipped("session save path $savePath is not writable from the test process");
        }

        $sid = $this->validSessionId();
        $file = $savePath . '/sess_' . $sid;
        // What a pre-2026-08-02 visitor's session actually contains.
        file_put_contents($file, 'FlashMessenger|O:23:"Zend\Stdlib\ArrayObject":0:{}');
        // The web server user must be able to read it now and rewrite it at
        // session_write_close (fs.protected_regular blocks O_CREAT opens of
        // another owner's file in the sticky /tmp, so chmod alone is not enough).
        @chown($file, 'www-data');
        chmod($file, 0666);

        try {
            $response = $this->request('GET', $path, ['Cookie: PHPSESSID=' . $sid]);

            self::assertSame(200, $response['status']);
            self::assertStringContainsString(
                '<title>',
                $response['body'],
                "blank body on $path: the stale-session wedge is back — see "
                . 'JUser\Session\SessionPruner, and App\Http\SessionListener for the ported half'
            );
            $rewritten = (string) file_get_contents($file);
            self::assertStringNotContainsString(
                'Zend\Stdlib\ArrayObject',
                $rewritten,
                'the poisoned value survived the request instead of being pruned'
            );
        } finally {
            @unlink($file);
        }
    }

    /**
     * An id the Id session validator will accept against the server's own ini.
     * Hex characters are valid for every session.sid_bits_per_character
     * setting, so only the length needs matching.
     */
    private function validSessionId(): string
    {
        $length = (int) (ini_get('session.sid_length') ?: 32);

        return substr(bin2hex(random_bytes(intdiv($length, 2) + 1)), 0, $length);
    }
}

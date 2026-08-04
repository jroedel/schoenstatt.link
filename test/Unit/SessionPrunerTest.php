<?php

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;
use JUser\Session\SessionPruner;

/**
 * Covers JUser\Session\SessionPruner, the guard that heals sessions written
 * before a class-renaming migration. The 2026 Zend → Laminas migration left
 * returning visitors with serialized Zend\Stdlib\ArrayObject containers that
 * unserialize as __PHP_Incomplete_Class and fatal in laminas-session's
 * verifyNamespace() on every page (production incident 2026-08-03,
 * exception fingerprints 1615c790/35c198d9).
 *
 * The class file is required directly: test/bootstrap.php deliberately avoids
 * vendor/autoload.php so the suite stays valid even when vendor/ is
 * mid-migration.
 */
class SessionPrunerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../module/JUser/src/Session/SessionPruner.php';
    }

    public function testDropsOnlyIncompleteClassValues(): void
    {
        $zendEra = unserialize('O:23:"Zend\Stdlib\ArrayObject":0:{}');
        self::assertInstanceOf(
            \__PHP_Incomplete_Class::class,
            $zendEra,
            'precondition: an unknown class must unserialize as __PHP_Incomplete_Class'
        );

        $session = [
            'FlashMessenger' => $zendEra,
            '__Laminas'      => ['_REQUEST_ACCESS_TIME' => 1.0],
            'locale'         => 'es_ES',
            'healthy'        => new \ArrayObject(['a' => 1]),
            'alsoGone'       => unserialize('O:8:"Gone\Cls":1:{s:1:"x";i:1;}'),
        ];

        $pruned = SessionPruner::pruneIncompleteClassValues($session);

        self::assertSame(2, $pruned);
        self::assertSame(['__Laminas', 'locale', 'healthy'], array_keys($session));
        self::assertSame('es_ES', $session['locale']);
        self::assertSame(['a' => 1], $session['healthy']->getArrayCopy());
    }

    public function testHealthySessionIsUntouched(): void
    {
        $session = [
            '__Laminas' => ['_REQUEST_ACCESS_TIME' => 1.0],
            'counter'   => 7,
        ];
        $before = $session;

        self::assertSame(0, SessionPruner::pruneIncompleteClassValues($session));
        self::assertSame($before, $session);
    }

    public function testEmptySessionIsANoOp(): void
    {
        $session = [];
        self::assertSame(0, SessionPruner::pruneIncompleteClassValues($session));
        self::assertSame([], $session);
    }
}

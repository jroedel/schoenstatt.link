<?php

namespace SchoenstattTest\Integration;

use App\Laminas\ContainerFactory;
use JTranslate\I18n\Translator\TranslatorEventListener;
use JTranslate\Model\TranslationsTable;
use JUser\Host\IdentityInterface;
use JUser\Service\IdentityActingUserProvider;
use App\Services\Container;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Which misses become phrases, and whether the acting user can stop that happening.
 *
 * `TranslatorEventListener` is the **only** path by which a phrase ever enters
 * `trans_phrases`, so what it ignores is not a detail: an ignored phrase is one nobody can
 * ever translate, because the row a translation hangs off is never created. That makes
 * this the foundation an agent-driven translation workflow stands on — an agent can only
 * work on phrases the site has managed to record.
 *
 * Two things are pinned here.
 *
 * **The key locale must be recorded.** The listener used to be built from `getLocales()`,
 * which omits it, so an English page view could never discover a phrase — a string that
 * appeared only on English pages stayed unknown indefinitely, and the "a deleted phrase
 * comes back the next time a page renders it" property silently held only for visitors
 * browsing in Spanish, German, Portuguese or Italian.
 *
 * **An unavailable acting-user service must not stop a write.** The identity is
 * session-backed and *raises* rather than returning nothing in a console process. That
 * used to escape from a write path that only wanted a nullable `modified_by`, killing the
 * write halfway.
 *
 * Neither test writes to the database: the first inspects the queue before it is flushed,
 * the second calls the provider directly.
 */
class PhraseDiscoveryTest extends TestCase
{
    private Container $container;

    private TranslationsTable $table;

    protected function setUp(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';

        $container = ContainerFactory::build($appConfig);
        $this->container = $container;

        try {
            $container->get(\Laminas\Db\Adapter\Adapter::class)->getDriver()->getConnection()->connect();
            /** @var TranslationsTable $table */
            $table = $container->get(TranslationsTable::class);
        } catch (\Throwable $e) {
            self::markTestSkipped('needs the capsule up: ' . $e->getMessage());
        }
        $this->table = $table;
    }

    /**
     * A miss in any configured locale is recorded, including the key locale.
     *
     * The key locale is the case that regressed, and it is asserted alongside the others
     * rather than alone so that a fix which recorded *only* English would still fail.
     */
    public function testAMissInEveryConfiguredLocaleIsRecorded(): void
    {
        $config  = $this->container->get('JTranslate\Config');
        $locales = $config['locales_to_translate'];
        if (! in_array($config['key_locale'], $locales, true)) {
            $locales[] = $config['key_locale'];
        }

        foreach ($locales as $locale) {
            self::assertTrue(
                $this->wouldRecord((string) $locale),
                sprintf(
                    'a missing translation in %s was ignored, so a phrase seen only on a %s page can never '
                    . 'be recorded and therefore never translated. The listener must be built with '
                    . 'getLocales(true).',
                    $locale,
                    $locale
                )
            );
        }

        self::assertContains(
            $config['key_locale'],
            $locales,
            'the fixture is meant to include the key locale; if it does not, this test proves nothing'
        );
    }

    /**
     * A locale the installation does not translate is still ignored.
     *
     * The filter exists for a reason: `Accept-Language` can ask for anything, and misses
     * in a language nobody translates are noise rather than work. Widening it to the key
     * locale must not have widened it to everything.
     */
    public function testAMissInAnUnconfiguredLocaleIsIgnored(): void
    {
        self::assertFalse($this->wouldRecord('fr_CA'), 'French Canadian is not configured and was recorded');
        self::assertFalse($this->wouldRecord('zz_ZZ'), 'a nonexistent locale was recorded');
    }

    /**
     * The provider answers null, rather than raising, when the session cannot exist.
     *
     * Built against a container that throws for the identity, which is what a console
     * process produces — there the failure comes out of
     * `Laminas\Session\Config\SessionConfig` rejecting `session.cache_expire`.
     */
    public function testTheActingUserProviderAnswersNullWhenTheServiceCannotBeBuilt(): void
    {
        $calls     = 0;
        $container = new class ($calls) implements \Psr\Container\ContainerInterface {
            public function __construct(public int &$calls)
            {
            }

            public function get(string $id): mixed
            {
                $this->calls++;

                throw new \RuntimeException("'session.cache_expire' is not a valid sessions-related ini setting.");
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $provider = new IdentityActingUserProvider($container);

        self::assertNull(
            $provider->getActingUserId(),
            'an unavailable identity still raises, so a console write that only wants a '
            . 'nullable modified_by dies halfway through'
        );
        self::assertNull($provider->getActingUserId(), 'the second call behaved differently from the first');
        self::assertSame(
            1,
            $container->calls,
            'the failed lookup was retried. A console command writing a thousand phrases would attempt a '
            . 'thousand doomed container lookups.'
        );
    }

    /**
     * A real request still gets a real answer — the guard must not swallow everything.
     *
     * With no identity in this process the correct answer is null, and it has to arrive by
     * actually consulting the service rather than by the service being unbuildable. If
     * this skips, the assertion above is the only cover and that is worth knowing.
     */
    public function testAnAvailableServiceIsStillConsulted(): void
    {
        try {
            $this->container->get(IdentityInterface::class);
        } catch (\Throwable $e) {
            self::markTestSkipped('the identity is not buildable in this process: ' . $e->getMessage());
        }

        $provider = new IdentityActingUserProvider($this->container);

        self::assertNull($provider->getActingUserId(), 'nobody is signed in, so there is no acting user');
    }

    /**
     * Whether the listener would queue a phrase for this locale, without writing anything.
     */
    private function wouldRecord(string $locale): bool
    {
        $queue = new \ReflectionProperty(TranslationsTable::class, 'newMissingPhrases');
        $queue->setValue($this->table, []);

        $listener = new TranslatorEventListener($this->table, $this->table->getLocales(true));
        //A distinct message per call: reportMissingTranslation() marks a phrase seen in
        //the in-request index, so reusing one string would make every call after the
        //first look ignored.
        $listener->missingTranslation(
            'discovery probe ' . $locale . ' ' . bin2hex(random_bytes(6)),
            $locale,
            'IntegrationTestDomain'
        );

        return [] !== $queue->getValue($this->table);
    }
}

<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Http\CspNonce;
use App\Laminas\HostMessages;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Laminas\ViewHelpers;
use App\Twig\TwigFactory;
use JTranslate\I18n\TranslatableMessage;
use Laminas\Db\Adapter\Adapter;
use JTranslate\I18n\Translator\Translator;
use Laminas\Translator\TranslatorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SionModel\Messaging\FlashMessages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;
use Twig\Environment;

use function is_readable;
use function str_contains;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The layout's message blocks render a TranslatableMessage without ever showing its data
 * to the translator.
 *
 * ## Why this is worth an integration test and not just a unit test
 *
 * TranslatableMessageTest pins the value object and JTranslate's MessageRenderer does the
 * rendering. What neither can pin is the wiring, and the wiring is the part that silently
 * reverts: `now_messages()` and `flash_messages()` in App\Twig\LaminasExtension hand each
 * message to the renderer with the page's translator, and a rewrite that translated the
 * *finished* message instead — as the laminas helpers did before JTranslate replaced them
 * — would file a JWT as a phrase again. Nothing would fail; the message would still render.
 *
 * Measured through the translator itself: `EVENT_MISSING_TRANSLATION` fires for every
 * string looked up that no catalog knows, which is exactly the path by which a phrase
 * enters the table. The spy stops propagation so JTranslate's own listener never runs and
 * the test writes nothing.
 *
 * The "now" path is what is driven, because a flash needs the session container, which a
 * CLI test cannot open; both Twig functions share one renderer and one code path per
 * message, so the property carries over.
 */
class MessengerDataIsNotTranslatedTest extends TestCase
{
    use RequiresApcu;

    private static ?ServiceBridge $bridge = null;

    /** A JWT-shaped string: what must never reach the translator. */
    private const SECRET = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.body.signature';

    /**
     * The translator delegator builds JTranslate's table, which needs the database, and the
     * view helpers reach APCu through the persistent cache — neither of which a bare CI
     * runner has.
     */
    protected function setUp(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no service configuration');
        }
        try {
            /** @var Adapter $adapter */
            $adapter = $this->bridge()->get(Adapter::class);
            $adapter->getDriver()->getConnection()->connect();
        } catch (Throwable $e) {
            self::markTestSkipped('no reachable database: ' . $e->getMessage());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function namespaceProvider(): iterable
    {
        yield 'success' => [FlashMessages::NAMESPACE_SUCCESS];
        yield 'error'   => [FlashMessages::NAMESPACE_ERROR];
    }

    #[DataProvider('namespaceProvider')]
    public function testTheTemplateIsTranslatedAndTheDataIsNot(string $namespace): void
    {
        $this->requireApcu();

        /** @var Translator $translator */
        $translator = $this->bridge()->get(TranslatorInterface::class);
        $asked      = [];
        //The spy replaces the discovery listener for the duration, so this test records
        //what was asked for without writing a phrase row. Restored in the finally below,
        //which matters: the delegator installs the real listener lazily and a test that
        //left the spy in place would silently stop discovery for the rest of the process.
        $translator->clearMissingTranslationListeners();
        $translator->onMissingTranslation(
            static function (string $message) use (&$asked): ?string {
                $asked[] = $message;

                return null;
            }
        );

        try {
            $messages = new HostMessages();
            $messages->now($namespace, new TranslatableMessage('Token issued, copy it now: %s', [self::SECRET]));
            $rendered = $this->twig($messages)->createTemplate('{{ now_messages() }}')->render();
        } finally {
            $translator->clearMissingTranslationListeners();
        }

        self::assertContains(
            'Token issued, copy it now: %s',
            $asked,
            'the template must be what is looked up, or no one can translate it'
        );
        foreach ($asked as $lookup) {
            self::assertStringNotContainsString(
                self::SECRET,
                $lookup,
                'the data reached the translator, which is what files it as a phrase'
            );
        }
        self::assertTrue(str_contains($rendered, self::SECRET), 'the data must still reach the page: ' . $rendered);
        self::assertStringContainsString('class="alert alert-dismissable alert-', $rendered);
    }

    private function twig(HostMessages $messages): Environment
    {
        $bridge   = $this->bridge();
        $requests = new RequestStack();
        $requests->push(Request::create('/en/shrines'));

        return (new TwigFactory())->create(
            $bridge,
            new ViewHelpers($bridge, static fn (): RouteUrl => new RouteUrl('')),
            new RouteUrl(''),
            $requests,
            new CspNonce(),
            $messages
        );
    }

    /** Config caches off, for the reason CacheStatusEndpointTest states: no test may write data/config/. */
    private function bridge(): ServiceBridge
    {
        if (null !== self::$bridge) {
            return self::$bridge;
        }
        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        return self::$bridge = new ServiceBridge($appConfig);
    }
}

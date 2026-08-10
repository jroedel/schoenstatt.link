<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use JTranslate\I18n\TranslatableMessage;
use JTranslate\View\Helper\FlashMessenger as JTranslateFlashMessenger;
use JTranslate\View\Helper\NowMessenger as JTranslateNowMessenger;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_contains;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The two messengers render a TranslatableMessage without ever showing its data to
 * the translator.
 *
 * ## Why this is worth an integration test and not just a unit test
 *
 * TranslatableMessageTest already pins the value object. What it cannot pin is the
 * wiring, and the wiring is the part that silently reverts: the flash-messenger view
 * helper is registered by `Laminas\Mvc\Plugin\FlashMessenger`'s own module, and
 * JTranslate replaces it by overriding the *factory* for that module's service id.
 * Drop that config entry, or move JTranslate above the flash-messenger module in
 * `config/modules.config.php`, and the helper reverts to the laminas one — which
 * translates the finished message, i.e. files the JWT as a phrase again. Nothing
 * would fail; the message would still render.
 *
 * Both front controllers resolve the helper through this same plugin manager (the
 * laminas layout directly, `App\Twig\LaminasExtension::flashMessages()` on the
 * Symfony side), so one assertion covers both.
 */
class MessengerDataIsNotTranslatedTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    /** A JWT-shaped string: what must never reach the translator. */
    private const SECRET = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.body.signature';

    public function testTheFlashMessengerHelperIsJTranslates(): void
    {
        self::assertInstanceOf(
            JTranslateFlashMessenger::class,
            $this->helpers()->get('flashMessenger'),
            'the laminas helper translates the finished message, which is how data becomes a phrase'
        );
    }

    /** Every alias the flash-messenger module registers must reach the same override. */
    public function testEveryAliasResolvesToTheOverride(): void
    {
        foreach (['flashmessenger', 'flashMessenger', 'FlashMessenger'] as $alias) {
            self::assertInstanceOf(
                JTranslateFlashMessenger::class,
                $this->helpers()->get($alias),
                $alias . ' resolves to the laminas helper'
            );
        }
    }

    /** @return iterable<string, array{class-string}> */
    public static function messengerProvider(): iterable
    {
        yield 'flashMessenger' => ['flashMessenger'];
        yield 'nowMessenger'   => ['nowMessenger'];
    }

    #[DataProvider('messengerProvider')]
    public function testTheTemplateIsTranslatedAndTheDataIsNot(string $helperName): void
    {
        $helper = $this->helpers()->get($helperName);
        self::assertTrue(
            $helper instanceof JTranslateFlashMessenger || $helper instanceof JTranslateNowMessenger,
            $helperName . ' is not a JTranslate messenger'
        );

        $asked      = [];
        $translator = new class ($asked) implements TranslatorInterface {
            /** @param list<string> $asked */
            public function __construct(private array &$asked)
            {
            }

            public function translate($message, $textDomain = 'default', $locale = null): string
            {
                $this->asked[] = (string) $message;
                return (string) $message;
            }

            public function translatePlural(
                $singular,
                $plural,
                $number,
                $textDomain = 'default',
                $locale = null
            ): string {
                return (string) $singular;
            }
        };
        $helper->setTranslator($translator);
        //Both helpers reach the escapeHtml helper through the view; outside a render
        //there is none, and NowMessenger dereferences it without a guard.
        $helper->setView(new PhpRenderer());

        $message  = new TranslatableMessage('Token issued, copy it now: %s', [self::SECRET]);
        $rendered = $this->render($helper, $message);

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
        self::assertTrue(
            str_contains($rendered, self::SECRET),
            'the data must still reach the page: ' . $rendered
        );
    }

    /**
     * Both helpers read their messages from a controller plugin, so the message is
     * pushed through the plugin rather than passed to the helper directly.
     */
    private function render(object $helper, TranslatableMessage $message): string
    {
        if ($helper instanceof JTranslateNowMessenger) {
            $helper->getPluginNowMessenger()->setNamespace('success')->addMessage($message);
            return (string) $helper();
        }

        $helper->getPluginFlashMessenger()->setNamespace('success')->addMessage($message);
        return (string) $helper->renderCurrent('success', ['alert']);
    }

    private function helpers(): mixed
    {
        return $this->bridge()->get('ViewHelperManager');
    }

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

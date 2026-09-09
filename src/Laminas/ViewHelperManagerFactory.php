<?php

declare(strict_types=1);

namespace App\Laminas;

use App\View\Helper\DateFormat;
use App\View\Helper\Translate;
use JTranslate\I18n\Translator\Translator;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Helper\Doctype;
use Laminas\View\Helper\Url;
use Laminas\View\HelperPluginManager;
use Laminas\View\Renderer\PhpRenderer;
use Psr\Container\ContainerInterface;

use function is_array;
use function is_string;

/**
 * The `ViewHelperManager`: every laminas view helper the Twig layer still bridges, built
 * from the modules' `view_helpers` config.
 *
 * Replaces `Laminas\Mvc\Service\ViewHelperManagerFactory`, minus what needed a dispatch:
 *
 * - `url` gets the router and no route match. `FormatEntity`, `EditPencil` and
 *   `EditPencilNew` call `$this->view->url()` with a route name and parameters, never
 *   "the current route", so a match was never consulted;
 * - `doctype` reads `view_manager.doctype`, as before — the form element helpers close
 *   void elements as XHTML unless told the document is HTML5;
 * - `basePath` is not configured: nothing bridged calls it, and the application is
 *   served from the docroot;
 * - a bare `PhpRenderer` is attached, because `HelperPluginManager::setRenderer()` is
 *   what gives every helper its `$this->view`, and laminas-mvc did that as a side effect
 *   of building `ViewRenderer`. The renderer has no resolver and renders nothing;
 *   helpers reach it for other helpers (`url`, `escapeHtml`).
 *
 * `translate` and `dateFormat` are registered here since 2026-09, when laminas-i18n — whose
 * helper config supplied them — was removed. {@see \App\View\Helper\Translate} is given
 * the one translator directly rather than through the plugin manager's translator
 * initializer, which looked for laminas-i18n's `TranslatorAwareInterface`.
 */
final class ViewHelperManagerFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): HelperPluginManager
    {
        $config = $container->get('config');
        $config = is_array($config) ? $config : [];

        $helpers = new HelperPluginManager(
            $container,
            is_array($config['view_helpers'] ?? null) ? $config['view_helpers'] : []
        );

        $url = static function () use ($container): Url {
            $helper = new Url();
            $helper->setRouter($container->get('HttpRouter'));

            return $helper;
        };
        $helpers->setFactory(Url::class, $url);
        $helpers->setFactory('laminasviewhelperurl', $url);

        $doctype = static function () use ($config): Doctype {
            $helper = new Doctype();
            $type   = $config['view_manager']['doctype'] ?? null;
            if (is_string($type) && '' !== $type) {
                $helper->setDoctype($type);
            }

            return $helper;
        };
        $helpers->setFactory(Doctype::class, $doctype);
        $helpers->setFactory('laminasviewhelperdoctype', $doctype);

        //`translate` and `dateFormat` came from laminas-i18n's own helper config until
        //2026-09. Registered here now, under both the class name and the lowercase alias
        //the plugin manager normalises to, so `$this->view->translate(...)` inside another
        //helper resolves exactly as it did.
        $translate = static function () use ($container): Translate {
            $helper = new Translate();
            if ($container->has(Translator::class)) {
                /** @var Translator $translator */
                $translator = $container->get(Translator::class);
                $helper->setTranslator($translator);
            }

            return $helper;
        };
        $helpers->setFactory(Translate::class, $translate);
        $helpers->setFactory('translate', $translate);

        $helpers->setFactory(DateFormat::class, static fn (): DateFormat => new DateFormat());
        $helpers->setAlias('dateFormat', DateFormat::class);
        $helpers->setAlias('dateformat', DateFormat::class);

        (new PhpRenderer())->setHelperPluginManager($helpers);

        return $helpers;
    }
}

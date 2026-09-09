<?php

declare(strict_types=1);

namespace App\Laminas;

use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Validator\Translator\Translator as ValidatorTranslator;
use Psr\Container\ContainerInterface;

/**
 * `MvcTranslator` without laminas-mvc-i18n.
 *
 * That package's `Translator` wrapped the laminas-i18n translator so one object could be
 * handed both to the view helpers (which want `Laminas\I18n\Translator\TranslatorInterface`)
 * and to the validators (which want `Laminas\Validator\Translator\TranslatorInterface`).
 * laminas-validator ships exactly that adapter, so this is a one-liner over the canonical
 * translator — the instance {@see TranslatorConfigurator} decorates, which is what gives
 * it its catalogs, its fallback locale and JTranslate's missing-translation listener.
 *
 * Anything that needs more than `translate()` — the catalog read in
 * `App\Twig\LaminasExtension::catalogValue()`, the tests that ask for the fallback locale —
 * asks the container for `TranslatorInterface::class` itself rather than unwrapping this.
 */
final class TranslatorFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ValidatorTranslator
    {
        /** @var TranslatorInterface $translator */
        $translator = $container->get(TranslatorInterface::class);

        return new ValidatorTranslator($translator);
    }
}

<?php

namespace Application;

use Application\Navigation\PageBuilder;

class Module
{
    /**
     * The navigation constants, kept here as the names everything already refers to.
     *
     * The values, and the code that builds the branches they key, live in
     * Application\Navigation\PageBuilder — the Symfony side (App\View\NavigationTree)
     * builds the tree from it. These are aliases so that test/Unit/NavigationDataLabelsTest
     * and everything else keep working unchanged; PageBuilder is where to read what they
     * mean.
     */
    public const PAGES_CACHE_KEYS = PageBuilder::PAGES_CACHE_KEYS;

    public const LOCALE_DEPENDENT_PAGES_CACHE_KEYS = PageBuilder::LOCALE_DEPENDENT_PAGES_CACHE_KEYS;

    public const LABEL_IS_DATA = PageBuilder::LABEL_IS_DATA;

    /**
     * Flag every navigation label that is record content.
     *
     * Delegates to PageBuilder, which is where the rule and its reasoning live.
     * Kept as a static on this class because test/Unit/NavigationDataLabelsTest drives it
     * by name.
     *
     * @param array<string, mixed> $pagesByCacheKey
     * @return array<string, mixed>
     */
    public static function markDataLabels(array $pagesByCacheKey): array
    {
        return PageBuilder::markDataLabels($pagesByCacheKey);
    }

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}

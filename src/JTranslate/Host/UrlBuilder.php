<?php

declare(strict_types=1);

namespace App\JTranslate\Host;

use App\Laminas\HostUrls;
use JTranslate\Host\UrlBuilderInterface;

/**
 * `JTranslate\Host\UrlBuilderInterface` over {@see HostUrls}.
 *
 * An `implements` clause and nothing else; the twin of `App\JUser\Host\UrlBuilder`. Both
 * exist because the two modules declare separate contracts on purpose — JUser depends on
 * JTranslate, so a shared interface would have to live in JTranslate and would make its
 * URL builder JUser's problem to keep compatible.
 *
 * `url()` has no caller on this surface: nothing in the translation GUI sends an email or
 * needs an absolute link. It is implemented rather than thrown from, because the interface
 * promises it and a host that answers "not supported" to a method the contract declares is
 * worse than one that answers it.
 */
final class UrlBuilder implements UrlBuilderInterface
{
    public function __construct(private readonly HostUrls $urls)
    {
    }

    public function path(string $route, array $params = [], array $query = []): string
    {
        return $this->urls->path($route, $params, $query);
    }

    public function url(string $route, array $params = [], array $query = []): string
    {
        return $this->urls->url($route, $params, $query);
    }
}

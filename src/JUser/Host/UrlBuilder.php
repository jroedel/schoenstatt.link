<?php

declare(strict_types=1);

namespace App\JUser\Host;

use App\Laminas\HostUrls;
use JUser\Host\UrlBuilderInterface;

/**
 * `JUser\Host\UrlBuilderInterface` over {@see HostUrls}.
 *
 * An `implements` clause and nothing else. Everything that is not obvious about building
 * one of these links here — the locale prefix, why an empty query is omitted, and why
 * `force_canonical` needs the router's request URI primed on a Symfony-served request —
 * lives in `HostUrls`, which JTranslate's adapter shares. It lived in this file until
 * 2026-09-08, when a second module needed the same three answers.
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

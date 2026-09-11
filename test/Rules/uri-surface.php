<?php

/**
 * What `SionModel\Uri\Http` answers, and what `SionTable::filterUrl()` stores because of it.
 *
 * GENERATED FILE — regenerate with:
 *
 *     docker compose exec -T -u www-data app php test/Rules/regenerate-uri-surface.php
 *
 * `uri` records the six questions the application asks a URI object; `stored` records the
 * row that reaches a URL column, which is the half no form recording can see.
 *
 * See `SchoenstattTest\Rules\UriSurface` for where the corpus comes from and why the 749
 * stored URLs are not in it.
 *
 * @return array<string, array<string, string>>
 */

declare(strict_types=1);

return array (
  'uri' => 
  array (
    'empty' => 'valid=yes absolute=no relative=yes scheme=(none) host=(none) toString=«/»',
    'plain-https' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/path»',
    'plain-http' => 'valid=yes absolute=yes relative=no scheme=«http» host=«example.com» toString=«http://example.com/»',
    'no-trailing-slash' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/»',
    'trailing-slash' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/»',
    'uppercase-scheme' => 'valid=yes absolute=yes relative=no scheme=«HTTPS» host=«example.com» toString=«HTTPS://example.com/path»',
    'uppercase-host' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/path»',
    'uppercase-path' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/Path/To/Thing»',
    'explicit-port' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com:443/path»',
    'nonstandard-port' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com:8443/path»',
    'user-info' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://user:secret@example.com/path»',
    'dot-segments' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/a/./b/../c»',
    'double-slash-path' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com//a//b»',
    'empty-query' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/path»',
    'empty-fragment' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/path»',
    'space-in-path' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/a%20b»',
    'plus-in-query' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/s?q=a+b»',
    'reserved-in-query' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/s?a=1&b=2;c=3»',
    'idn-host' => 'valid=yes absolute=yes relative=no scheme=«https» host=«exämple.de» toString=«https://exämple.de/path»',
    'punycode-host' => 'valid=yes absolute=yes relative=no scheme=«https» host=«xn--exmple-cua.de» toString=«https://xn--exmple-cua.de/path»',
    'ipv4-host' => 'valid=yes absolute=yes relative=no scheme=«http» host=«192.0.2.1» toString=«http://192.0.2.1/path»',
    'ipv6-host' => 'valid=yes absolute=yes relative=no scheme=«http» host=«[2001:db8::1]» toString=«http://[2001:db8::1]/path»',
    'no-scheme' => 'valid=yes absolute=no relative=yes scheme=(none) host=(none) toString=«example.com/path»',
    'scheme-relative' => 'valid=yes absolute=no relative=no scheme=(none) host=«example.com» toString=«//example.com/path»',
    'path-only' => 'valid=yes absolute=no relative=yes scheme=(none) host=(none) toString=«/path/only»',
    'relative-path' => 'valid=yes absolute=no relative=yes scheme=(none) host=(none) toString=«path/only»',
    'mailto' => 'threw SionModel\\Uri\\Exception\\InvalidUriPartException: Scheme "mailto" is not valid or is not accepted by SionModel\\Uri\\Http',
    'javascript' => 'threw SionModel\\Uri\\Exception\\InvalidUriPartException: Scheme "javascript" is not valid or is not accepted by SionModel\\Uri\\Http',
    'ftp' => 'threw SionModel\\Uri\\Exception\\InvalidUriPartException: Scheme "ftp" is not valid or is not accepted by SionModel\\Uri\\Http',
    'not-a-url' => 'valid=yes absolute=no relative=yes scheme=(none) host=(none) toString=«this%20is%20not%20a%20url»',
    'leading-whitespace' => 'valid=yes absolute=no relative=yes scheme=(none) host=(none) toString=«%20%20https://example.com/path»',
    'trailing-whitespace' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/path%20%20»',
    'newline-inside' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/pa%0Ath»',
    'nul-byte' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/pa%00th»',
    'invalid-utf8' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/pa%80th»',
    'stored-percent-encoded' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/files/herbstst%C3%BCrme.pdf»',
    'stored-raw-utf8-path' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/Santu%C3%A1rio-de-Schoenstatt-Santo-%C3%82ngelo-209258836102428/»',
    'stored-long-query' => 'valid=yes absolute=yes relative=no scheme=«http» host=«example.com» toString=«http://example.com/site/index.php?page=shop.product_details&flypage=flypage.tpl&product_id=101&category_id=16&option=com_virtuemart&Itemid=92»',
    'stored-fragment' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/fr/pour-commander/#bestellformular»',
    'stored-hashbang' => 'valid=yes absolute=yes relative=no scheme=«https» host=«example.com» toString=«https://example.com/fr/pour-commander/#!prettyPhoto»',
  ),
  'stored' => 
  array (
    'empty' => 'null',
    'plain-https' => 'url=«https://example.com/path» label=«example.com»',
    'plain-http' => 'url=«http://example.com/» label=«example.com»',
    'no-trailing-slash' => 'url=«https://example.com/» label=«example.com»',
    'trailing-slash' => 'url=«https://example.com/» label=«example.com»',
    'uppercase-scheme' => 'url=«HTTPS://example.com/path» label=«example.com»',
    'uppercase-host' => 'url=«https://example.com/path» label=«example.com»',
    'uppercase-path' => 'url=«https://example.com/Path/To/Thing» label=«example.com»',
    'explicit-port' => 'url=«https://example.com:443/path» label=«example.com»',
    'nonstandard-port' => 'url=«https://example.com:8443/path» label=«example.com»',
    'user-info' => 'url=«https://user:secret@example.com/path» label=«example.com»',
    'dot-segments' => 'url=«https://example.com/a/./b/../c» label=«example.com»',
    'double-slash-path' => 'url=«https://example.com//a//b» label=«example.com»',
    'empty-query' => 'url=«https://example.com/path» label=«example.com»',
    'empty-fragment' => 'url=«https://example.com/path» label=«example.com»',
    'space-in-path' => 'url=«https://example.com/a%20b» label=«example.com»',
    'plus-in-query' => 'url=«https://example.com/s?q=a+b» label=«example.com»',
    'reserved-in-query' => 'url=«https://example.com/s?a=1&b=2;c=3» label=«example.com»',
    'idn-host' => 'url=«https://exämple.de/path» label=«exämple.de»',
    'punycode-host' => 'url=«https://xn--exmple-cua.de/path» label=«xn--exmple-cua.de»',
    'ipv4-host' => 'url=«http://192.0.2.1/path» label=«192.0.2.1»',
    'ipv6-host' => 'url=«http://[2001:db8::1]/path» label=«[2001:db8::1]»',
    'no-scheme' => 'url=«example.com/path» label=(none)',
    'scheme-relative' => 'url=«//example.com/path» label=«example.com»',
    'path-only' => 'url=«/path/only» label=(none)',
    'relative-path' => 'url=«path/only» label=(none)',
    'mailto' => 'threw SionModel\\Uri\\Exception\\InvalidUriPartException: Scheme "mailto" is not valid or is not accepted by SionModel\\Uri\\Http',
    'javascript' => 'threw SionModel\\Uri\\Exception\\InvalidUriPartException: Scheme "javascript" is not valid or is not accepted by SionModel\\Uri\\Http',
    'ftp' => 'threw SionModel\\Uri\\Exception\\InvalidUriPartException: Scheme "ftp" is not valid or is not accepted by SionModel\\Uri\\Http',
    'not-a-url' => 'url=«this%20is%20not%20a%20url» label=(none)',
    'leading-whitespace' => 'url=«%20%20https://example.com/path» label=(none)',
    'trailing-whitespace' => 'url=«https://example.com/path%20%20» label=«example.com»',
    'newline-inside' => 'url=«https://example.com/pa%0Ath» label=«example.com»',
    'nul-byte' => 'url=«https://example.com/pa%00th» label=«example.com»',
    'invalid-utf8' => 'url=«https://example.com/pa%80th» label=«example.com»',
    'stored-percent-encoded' => 'url=«https://example.com/files/herbstst%C3%BCrme.pdf» label=«example.com»',
    'stored-raw-utf8-path' => 'url=«https://example.com/Santu%C3%A1rio-de-Schoenstatt-Santo-%C3%82ngelo-209258836102428/» label=«example.com»',
    'stored-long-query' => 'url=«http://example.com/site/index.php?page=shop.product_details&flypage=flypage.tpl&product_id=101&category_id=16&option=com_virtuemart&Itemid=92» label=«example.com»',
    'stored-fragment' => 'url=«https://example.com/fr/pour-commander/#bestellformular» label=«example.com»',
    'stored-hashbang' => 'url=«https://example.com/fr/pour-commander/#!prettyPhoto» label=«example.com»',
  ),
);

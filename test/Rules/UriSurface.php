<?php

declare(strict_types=1);

namespace SchoenstattTest\Rules;

use SionModel\Uri\Http;
use SionModel\Db\Model\SionTable;
use Throwable;

use function is_array;
use function mb_check_encoding;
use function ord;
use function preg_replace_callback;
use function sprintf;

/**
 * What `SionModel\Uri\Http` answers, and what the application stores because of it.
 *
 * ## Why this is recorded apart from the rules
 *
 * `laminas-uri` was eight files and eighteen references, and fourteen of those were the
 * string `'Laminas\Uri\Http'` carried as a URI validator's `uriHandler` option — which
 * {@see RuleSurface} measured, because the validator is named in a specification, and which
 * left with the package: {@see \SionModel\Validator\Uri} has no handler to choose.
 *
 * The references that matter are the rest. `SionModel\Db\Model\SionTable` builds a `Http`
 * in three places and keeps `$url->toString()` — so laminas-uri is not validating a URL
 * here, it is **rewriting the value that goes into the column**. `filterUrl()` also takes
 * the host as the link's label when the editor left it blank, so a changed `getHost()`
 * changes what a page says as well as what it stores.
 *
 * A replacement that accepts and rejects exactly what laminas accepts and rejects can still
 * be wrong here, by normalising differently: percent-encoding one character more, dropping
 * an empty query, lowercasing a path. Nothing in the form recordings would see it, because
 * the form's value is checked and stored by different code.
 *
 * ## Why the corpus is written out and not taken from the database
 *
 * 749 distinct URLs are stored across seven tables, and they are dull: two schemes, no
 * ports, no user-info, 41 with a query, 2 with a fragment, 12 percent-encoded and one with
 * raw UTF-8 in the path. Committing them would put several hundred people's personal links
 * in a repository that is being prepared to go public (issue #259) and would teach the
 * recording nothing the fifteen shapes below do not. The real set is worth running once at
 * the swap, against both implementations, and saying so in the PR — not worth storing.
 */
final class UriSurface
{
    /**
     * The shapes, each labelled by what it is there to decide.
     *
     * The last five are real shapes taken from the stored set, with the hosts and paths
     * replaced: a percent-encoded path, raw UTF-8 in a path, a long query string, a
     * fragment, and a fragment beginning `#!`.
     *
     * @return array<string, string>
     */
    public static function corpus(): array
    {
        return [
            'empty'                   => '',
            'plain-https'             => 'https://example.com/path',
            'plain-http'              => 'http://example.com',
            'no-trailing-slash'       => 'https://example.com',
            'trailing-slash'          => 'https://example.com/',
            'uppercase-scheme'        => 'HTTPS://example.com/path',
            'uppercase-host'          => 'https://EXAMPLE.COM/path',
            'uppercase-path'          => 'https://example.com/Path/To/Thing',
            'explicit-port'           => 'https://example.com:443/path',
            'nonstandard-port'        => 'https://example.com:8443/path',
            'user-info'               => 'https://user:secret@example.com/path',
            'dot-segments'            => 'https://example.com/a/./b/../c',
            'double-slash-path'       => 'https://example.com//a//b',
            'empty-query'             => 'https://example.com/path?',
            'empty-fragment'          => 'https://example.com/path#',
            'space-in-path'           => 'https://example.com/a b',
            'plus-in-query'           => 'https://example.com/s?q=a+b',
            'reserved-in-query'       => 'https://example.com/s?a=1&b=2;c=3',
            'idn-host'                => 'https://exämple.de/path',
            'punycode-host'           => 'https://xn--exmple-cua.de/path',
            'ipv4-host'               => 'http://192.0.2.1/path',
            'ipv6-host'               => 'http://[2001:db8::1]/path',
            'no-scheme'               => 'example.com/path',
            'scheme-relative'         => '//example.com/path',
            'path-only'               => '/path/only',
            'relative-path'           => 'path/only',
            'mailto'                  => 'mailto:user@example.com',
            'javascript'              => 'javascript:alert(1)',
            'ftp'                     => 'ftp://example.com/file',
            'not-a-url'               => 'this is not a url',
            'leading-whitespace'      => '  https://example.com/path',
            'trailing-whitespace'     => 'https://example.com/path  ',
            'newline-inside'          => "https://example.com/pa\nth",
            'nul-byte'                => "https://example.com/pa\0th",
            'invalid-utf8'            => "https://example.com/pa\x80th",

            //--- shapes taken from the 749 stored URLs, with the identities replaced
            'stored-percent-encoded'  => 'https://example.com/files/herbstst%C3%BCrme.pdf',
            'stored-raw-utf8-path'    => 'https://example.com/Santuário-de-Schoenstatt-Santo-Ângelo-209258836102428/',
            'stored-long-query'       => 'http://example.com/site/index.php?page=shop.product_details&flypage=flypage.tpl&product_id=101&category_id=16&option=com_virtuemart&Itemid=92',
            'stored-fragment'         => 'https://example.com/fr/pour-commander/#bestellformular',
            'stored-hashbang'         => 'https://example.com/fr/pour-commander/#!prettyPhoto',
        ];
    }

    /**
     * `'uri'` => what `Http` answers, `'stored'` => what `SionTable` keeps because of it.
     *
     * @return array<string, array<string, string>>
     */
    public static function collect(): array
    {
        $surface = ['uri' => [], 'stored' => []];

        foreach (self::corpus() as $label => $url) {
            $surface['uri'][$label]    = self::answersFor($url);
            $surface['stored'][$label] = self::storedFor($url);
        }

        return $surface;
    }

    /**
     * The six questions the application asks a `Http`, and the answer to each.
     *
     * `parse()` is exercised by the constructor; `isValid()`, `isAbsolute()` and
     * `isValidRelative()` are what the URI validator branches on, `getHost()` is the label
     * `SionTable` falls back to, and `toString()` is what reaches the column.
     */
    private static function answersFor(string $url): string
    {
        try {
            $uri = new Http($url);

            return self::printable(sprintf(
                'valid=%s absolute=%s relative=%s scheme=%s host=%s toString=%s',
                self::yesNo($uri->isValid()),
                self::yesNo($uri->isAbsolute()),
                self::yesNo($uri->isValidRelative()),
                self::orNone($uri->getScheme()),
                self::orNone($uri->getHost()),
                self::orNone($uri->toString())
            ));
        } catch (Throwable $e) {
            return self::printable('threw ' . $e::class . ': ' . $e->getMessage());
        }
    }

    /**
     * What `SionTable::filterUrl()` returns, which is the row that reaches the database.
     */
    private static function storedFor(string $url): string
    {
        try {
            $stored = SionTable::filterUrl($url);

            if (! is_array($stored)) {
                return self::printable('null');
            }

            return self::printable(sprintf(
                'url=%s label=%s',
                self::orNone($stored['url'] ?? null),
                self::orNone($stored['label'] ?? null)
            ));
        } catch (Throwable $e) {
            return self::printable('threw ' . $e::class . ': ' . $e->getMessage());
        }
    }

    private static function yesNo(bool $answer): string
    {
        return $answer ? 'yes' : 'no';
    }

    private static function orNone(mixed $value): string
    {
        return null === $value || '' === $value ? '(none)' : '«' . $value . '»';
    }

    /**
     * The corpus carries a NUL byte and an invalid UTF-8 sequence on purpose, and either one
     * reaching the generated file makes it unreadable to git and to phpcs alike.
     */
    private static function printable(string $value): string
    {
        $pattern = mb_check_encoding($value, 'UTF-8')
            ? '/[\x00-\x1F\x7F]/'
            : '/[\x00-\x1F\x7F-\xFF]/';

        return preg_replace_callback(
            $pattern,
            static fn(array $match): string => match ($match[0]) {
                "\n"    => '\\n',
                "\r"    => '\\r',
                "\t"    => '\\t',
                default => sprintf('\\x%02X', ord($match[0])),
            },
            $value
        ) ?? $value;
    }
}

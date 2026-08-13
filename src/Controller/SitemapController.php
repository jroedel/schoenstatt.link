<?php

declare(strict_types=1);

namespace App\Controller;

use App\Sitemap\SitemapGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function is_string;

/**
 * `/sitemap.xml` — the sitemap index — and `/sitemap/pages*.xml`, the parts it lists.
 *
 * The laminas action this replaces served `data/sitemap/sitemap.xml` and nothing else,
 * which published 3,022 of the site's 10,974 pages; `App\Sitemap\SitemapGenerator`'s
 * docblock has the numbers and the reasoning. What matters here is the shape of the two
 * responses.
 *
 * ## Both are gzip, and that is a claim about the bytes rather than about the transfer
 *
 * The files are written gzipped, so the response says `Content-Encoding: gzip` and hands
 * the file over untouched — the laminas action did the same. The important consequence is
 * that these responses must never be compressed *again* by anything downstream:
 * docs/strangler.md records that App\Http\GzipListener honours a response's own
 * `Content-Encoding`, and the sitemap is the route that made that necessary.
 *
 * ## No locale prefix, and no ACL
 *
 * `/sitemap.xml` is answered where it is asked. The laminas route is a plain
 * `/sitemap.xml` with a `sitemap` guard entry for `guest` and `user`, and although the
 * router will also assemble `/en/sitemap.xml`, robots.txt has always named the prefixed
 * form and both keep working.
 *
 * The laminas action walked the navigation container directly rather than through the
 * `accept()` the navigation view helper uses, so no page was ever ACL-filtered out of the
 * sitemap. That is reproduced, deliberately: filtering here would quietly change which
 * pages are published, and a sitemap is for a crawler, which is always anonymous anyway.
 */
final class SitemapController
{
    public function __construct(private readonly SitemapGenerator $generator)
    {
    }

    /** The index: a list of the parts, as `<sitemapindex>`. */
    public function index(Request $request): Response
    {
        $path = $this->generator->ensure($request->getSchemeAndHttpHost());

        return $this->file($path);
    }

    /** One part, by the filename the index published. */
    public function part(Request $request): Response
    {
        $filename = $request->attributes->get('filename');
        if (! is_string($filename)) {
            return new Response('Not found.', Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/plain']);
        }

        //A part is only ever requested because the index named it, and the index is written
        //at the same moment the parts are. Ensuring here as well covers the case that
        //matters in practice: a crawler that read the index yesterday, a
        //`cache:flush-persistent` since, and a request for part 3 arriving first.
        $this->generator->ensure($request->getSchemeAndHttpHost());

        $path = $this->generator->partPath($filename);
        if (null === $path) {
            return new Response('Not found.', Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/plain']);
        }

        return $this->file($path);
    }

    /** A written sitemap file, served as the gzip stream it already is. */
    private function file(string $path): BinaryFileResponse
    {
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'text/xml');
        $response->headers->set('Content-Encoding', 'gzip');
        //BinaryFileResponse would otherwise offer the file as a download named `pages_2.xml`
        $response->headers->remove('Content-Disposition');

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Sitemap\SitemapGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function file_exists;

/**
 * `/sitemap.xml` when — and only when — the file is not on disk.
 *
 * ## This is a fallback now, and normally never runs
 *
 * The sitemap is a static file written by `bin/console sitemap:build`, and
 * `public/.htaccess` passes any request whose `REQUEST_FILENAME` exists straight to Apache
 * before the rewrite to `index.php`. So once `public/sitemap.xml` exists, this controller is
 * unreachable for it: Apache serves the bytes, `mod_deflate` compresses them, and PHP is not
 * involved. Confirm which is happening by looking for a `Vary: Accept-Encoding` on the
 * response — Apache sends one, this does not.
 *
 * What is left for it to do is the one case Apache cannot handle: the file is *missing*. That
 * happens on a first deploy, before cron has run once, and if someone deletes the files. The
 * old behaviour there was a 404 for the site's entire sitemap until the next scheduled build,
 * so this builds them, and every request after it is served statically.
 *
 * ## Why it does not check staleness
 *
 * Only existence. Rebuilding here whenever the data had moved would put a 0.6 s navigation
 * walk inside an arbitrary crawler request, and — worse — it would do so on *every* request
 * until something wrote the file, because a request that only reads cannot know another one
 * is already building. Freshness is `sitemap:build`'s job, on a schedule, where a slow run
 * costs nobody a response. This one's job is to make sure a URL a crawler already knows never
 * answers 404.
 *
 * ## No gzip, no locale prefix, no ACL check of its own
 *
 * The files are plain XML now; the `Content-Encoding: gzip` the previous version set by hand
 * is gone, and with it the requirement that nothing downstream compress the response again.
 * `/sitemap.xml` is still answered where it is asked rather than redirected to `/en/`, because
 * a sitemap has no locale. The pages *inside* it are filtered to what a guest may reach — see
 * App\Sitemap\GuestAccess — which is a change from the previous version, where nothing was.
 */
final class SitemapController
{
    public function __construct(
        private readonly SitemapGenerator $generator,
        private readonly string $docroot,
        private readonly string $canonicalBaseUrl
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $path = $this->docroot . '/' . SitemapGenerator::INDEX;

        if (! file_exists($path)) {
            //A sitemap must list canonical URLs, so the configured host wins over the one
            //this request happens to have arrived on; the request is only the fallback for a
            //deployment that has not set it.
            $baseUrl = '' !== $this->canonicalBaseUrl
                ? $this->canonicalBaseUrl
                : $request->getSchemeAndHttpHost();

            $this->generator->build($baseUrl);
        }

        if (! file_exists($path)) {
            return new Response(
                "Sitemap unavailable.\n",
                Response::HTTP_SERVICE_UNAVAILABLE,
                ['Content-Type' => 'text/plain']
            );
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'text/xml');
        //BinaryFileResponse would otherwise offer the file as a download
        $response->headers->remove('Content-Disposition');

        return $response;
    }
}

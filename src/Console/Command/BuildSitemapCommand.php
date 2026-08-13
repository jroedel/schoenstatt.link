<?php

declare(strict_types=1);

namespace App\Console\Command;

use App\Sitemap\SitemapGenerator;
use Closure;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function count;
use function implode;
use function is_string;
use function microtime;
use function rtrim;
use function sprintf;

/**
 * `sitemap:build` — write the sitemap files Apache serves.
 *
 * ## Why this is a command and not a request
 *
 * Because the files are static now. `public/.htaccess` serves any existing file directly, so
 * a GET for `/sitemap.xml` never reaches PHP once the file is there, which means nothing in a
 * request can notice that the data changed. Something outside the request path has to look,
 * and this is it — from cron:
 *
 *     * /15 * * * *  cd ~/public_html/schoenstatt.link && php bin/console sitemap:build
 *
 * ## The early exit is the point
 *
 * Running this every fifteen minutes is only reasonable because the common case costs one
 * `MAX(UpdatedOn)` over an indexed column: `SitemapGenerator::isStale()` compares the newest
 * change row against the index file's mtime and this returns before building anything. The
 * expensive half — the whole navigation container, about 0.6 s — is behind a closure so that
 * a run which has nothing to do does not even construct it.
 *
 * That check is a database column against a filesystem timestamp rather than a cache flag on
 * purpose. An APCu segment belongs to the SAPI that created it, so a stamp written here would
 * be invisible to the web server; these two are the only clocks both SAPIs share.
 *
 * ## `--url` and why config wins over a request
 *
 * A sitemap must list canonical URLs, so the host comes from `sion_model.canonical_base_url`
 * and not from whatever hostname a request happened to arrive on. `--url` overrides it, which
 * is what the smoke tests use to build a capsule-local sitemap.
 */
#[AsCommand(
    name: 'sitemap:build',
    description: 'Write public/sitemap*.xml when the catalogue has changed since the last build'
)]
final class BuildSitemapCommand extends Command
{
    /**
     * @param Closure(): SitemapGenerator $generator deferred so a no-op run builds no
     *        ServiceBridge, loads no modules and touches no navigation
     */
    public function __construct(
        private readonly Closure $generator,
        private readonly string $canonicalBaseUrl
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Rebuild even if nothing has changed')
            ->addOption(
                'url',
                'u',
                InputOption::VALUE_REQUIRED,
                'Base URL to publish, no trailing slash; defaults to sion_model.canonical_base_url'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseUrl = $input->getOption('url');
        $baseUrl = is_string($baseUrl) && '' !== $baseUrl ? rtrim($baseUrl, '/') : $this->canonicalBaseUrl;

        if ('' === $baseUrl) {
            $output->writeln(
                '<error>No base URL: set sion_model.canonical_base_url or pass --url.</error>'
            );

            return self::FAILURE;
        }

        try {
            $generator = ($this->generator)();

            if (! $input->getOption('force') && ! $generator->isStale()) {
                if ($output->isVerbose()) {
                    $output->writeln('Sitemap is current; nothing to do.');
                }

                return self::SUCCESS;
            }

            $startedAt = microtime(true);
            $written   = $generator->build($baseUrl);
        } catch (Throwable $e) {
            //A cron failure nobody reads is how a sitemap goes stale for a year, so this
            //says what broke rather than only failing.
            $output->writeln(sprintf('<error>Sitemap build failed: %s</error>', $e->getMessage()));

            return self::FAILURE;
        }

        $output->writeln(sprintf(
            'Wrote %d sitemap files in %.2fs: %s',
            count($written),
            microtime(true) - $startedAt,
            implode(', ', $written)
        ));

        return self::SUCCESS;
    }
}

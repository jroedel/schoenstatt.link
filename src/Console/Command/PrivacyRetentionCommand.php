<?php

declare(strict_types=1);

namespace App\Console\Command;

use App\Privacy\ContactRetention;
use Books\Model\BorrowerTokenTable;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_column;
use function count;
use function sprintf;

/**
 * `privacy:retention` — erase contact data the privacy policy says we no longer keep.
 *
 * A dry run by default: it lists who is due and changes nothing. `--apply` erases, then
 * flushes the web server's persistent cache through `cache:flush-persistent`, because the
 * site would otherwise go on serving the erased values from APCu, which a console process
 * cannot reach. A failed flush fails the run, although the erasure has already committed:
 * a cron job that reports success while the site still shows an address would be wrong.
 *
 * The rule itself is {@see ContactRetention}; it also deletes borrower links that expired
 * over a month ago ({@see BorrowerTokenTable::pruneExpired()}). The schedule is a cron entry,
 * docs/DEPLOY.md.
 * Output names person ids and dates, never a name or an address.
 */
#[AsCommand(
    name: 'privacy:retention',
    description: 'Erase contact data past the privacy policy\'s retention period (dry run unless --apply)'
)]
final class PrivacyRetentionCommand extends Command
{
    /**
     * @param Closure(): ContactRetention $retention deferred, so `bin/console list`
     *        opens no database connection
     * @param Closure(): BorrowerTokenTable $borrowerTokens expired borrower links go too
     */
    public function __construct(
        private readonly Closure $retention,
        private readonly Closure $borrowerTokens
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Erase; without it, only report');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var ContactRetention $retention */
        $retention = ($this->retention)();
        /** @var BorrowerTokenTable $borrowerTokens */
        $borrowerTokens = ($this->borrowerTokens)();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $due = $retention->due($now);
        $personIds = array_column($due, 'personId');
        $changeRows = $retention->dueChangeRows($now, $personIds);
        $expiredLinks = $borrowerTokens->countPrunable($now);

        $output->writeln(sprintf(
            'Retention %d years: before %s UTC.',
            ContactRetention::YEARS,
            $retention->cutoff($now)->format('Y-m-d H:i')
        ));
        foreach ($due as $person) {
            $output->writeln(sprintf('  person %-6d last activity %s', $person['personId'], $person['lastActivity']));
        }
        $output->writeln(sprintf(
            '%d person(s) with contact data due; %d change-log row(s) holding a contact value due; '
            . '%d borrower link(s) expired over a month ago.',
            count($due),
            $changeRows,
            $expiredLinks
        ));

        if (! $input->getOption('apply')) {
            $output->writeln('Dry run: nothing changed. --apply erases.');

            return Command::SUCCESS;
        }
        $pruned = $borrowerTokens->pruneExpired($now);
        if ([] === $due && 0 === $changeRows) {
            $output->writeln(sprintf('Deleted %d expired borrower link(s).', $pruned));

            return Command::SUCCESS;
        }

        $done = $retention->apply($now, $personIds);
        $output->writeln(sprintf(
            'Erased the contact data of %d person(s), blanked %d change-log row(s), '
            . 'deleted %d expired borrower link(s).',
            $done['persons'],
            $done['changeRows'],
            $pruned
        ));

        $application = $this->getApplication();
        if (null === $application) {
            $output->writeln('<error>No console application to flush the web cache through.</error>');

            return Command::FAILURE;
        }
        $flush = $application->find('cache:flush-persistent');

        return $flush->run(new ArrayInput([]), $output);
    }
}

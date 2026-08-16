<?php

declare(strict_types=1);

namespace App\Console\Command;

use Books\Mailing\BooksMailer;
use Books\Model\LibraryTable;
use Closure;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function is_array;
use function sprintf;

/**
 * `books:send-notices` — overdue notices, without an API key.
 *
 * ## Why this exists
 *
 * The notices were sent by cron fetching a URL with `?key=…` in it, so a shared
 * secret that never rotates was written to the web server's access log on every run
 * and sat in the crontab in clear text. The endpoint gained header authentication
 * first, which removed the log leak; this removes the secret from the schedule
 * altogether. A cron entry on the server needs no credential to talk to its own
 * database.
 *
 * It is also the honest home for the work. Sending mail is not a page: nothing about
 * it wants a request, a session, or an HTTP status, and the only reason it was a URL
 * is that in 2020 there was no console to put it in.
 *
 * ## The acting user
 *
 * `SionTable` stamps changes with an acting user taken from the session, and a
 * console process has none. Left alone, the mailing report writes a row attributing
 * the send to nobody in particular via a provider that expects an identity to exist.
 * `setActingUserId(null)` states the truth — this was done by the system, not by a
 * person — and is the same accommodation `jtranslate:retire` makes.
 */
#[AsCommand(
    name: 'books:send-notices',
    description: 'Email overdue-book notices for a library, with no API key involved'
)]
final class SendBookNoticesCommand extends Command
{
    /**
     * @param Closure(): BooksMailer $mailer deferred: --list and a bad --library
     *        should not build a mail transport
     * @param Closure(): LibraryTable $libraryTable
     */
    public function __construct(
        private readonly Closure $mailer,
        private readonly Closure $libraryTable
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('library', 'l', InputOption::VALUE_REQUIRED, 'Library id to send notices for')
            ->addOption('list', null, InputOption::VALUE_NONE, 'List libraries with their ids and exit')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Report who would be emailed and send nothing'
            )
            ->addOption(
                'all-borrowers',
                null,
                InputOption::VALUE_NONE,
                'Include borrowers with nothing overdue (default: overdue only)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var LibraryTable $libraryTable */
        $libraryTable = ($this->libraryTable)();

        if ($input->getOption('list')) {
            foreach ($libraryTable->getLibraries() as $library) {
                $output->writeln(sprintf('  %-4d %s', $library['libraryId'], $library['name']));
            }

            return self::SUCCESS;
        }

        $libraryId = (int) $input->getOption('library');
        if ($libraryId <= 0) {
            $output->writeln('<error>--library is required. Use --list to see the ids.</error>');

            return self::INVALID;
        }
        $library = $libraryTable->getObject('library', $libraryId);
        if (! is_array($library)) {
            $output->writeln(sprintf('<error>No library with id %d. Use --list.</error>', $libraryId));

            return self::INVALID;
        }

        $onlyOverdue = ! $input->getOption('all-borrowers');
        $simulate    = (bool) $input->getOption('dry-run');

        /** @var BooksMailer $mailer */
        $mailer = ($this->mailer)();
        //No session here, so no acting user. State that rather than letting the provider
        //reach for an identity a console process cannot have; setActingUserId lives on
        //SionTable, which LibraryTable extends.
        $mailer->getLibraryTable()->setActingUserId(null);

        $borrowers = $mailer->sendBookNotices($libraryId, $onlyOverdue, $simulate);
        $borrowers = is_array($borrowers) ? $borrowers : [];

        if ([] === $borrowers) {
            $output->writeln(sprintf(
                'Nothing to send: no borrower of %s has %s.',
                $library['name'],
                $onlyOverdue ? 'an overdue book' : 'a book out'
            ));

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        foreach ($borrowers as $borrower) {
            $status = $borrower['mailingStatus'] ?? null;
            $line = sprintf(
                '  %-28s %s',
                (string) ($borrower['fullFriendlyName'] ?? $borrower['firstName'] ?? '(unnamed)'),
                (string) ($borrower['email'] ?? '(no address)')
            );
            if ($simulate) {
                $output->writeln($line . '   would be emailed');
                continue;
            }
            if (BooksMailer::STATUS_SUCCESSFULLY_SENT === $status) {
                $sent++;
                $output->writeln('<info>' . $line . '   sent</info>');
            } else {
                $failed++;
                $output->writeln('<error>' . $line . '   FAILED</error>');
            }
        }

        if ($simulate) {
            $output->writeln(sprintf(
                "\n%d borrower(s) would be emailed for %s. Nothing was sent.",
                count($borrowers),
                $library['name']
            ));

            return self::SUCCESS;
        }

        $output->writeln(sprintf("\n%d sent, %d failed, for %s.", $sent, $failed, $library['name']));

        //A refused address must not fail the whole run — the other notices went out, and
        //a non-zero exit here would make cron mail an operator about a mailbox that has
        //been full for a year. The per-borrower FAILED lines are the report.
        return self::SUCCESS;
    }
}

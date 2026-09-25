<?php

declare(strict_types=1);

namespace App\Console\Command;

use App\Privacy\PersonalData;
use Closure;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function json_encode;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * `privacy:export` — everything held about a person or an account, as JSON on stdout.
 *
 * The answer to a subject-access request that arrives by mail; a signed-in account can
 * download its own from `/user/my-data`. Redirect it to a file and send that — the output
 * is personal data, so it should not sit in a shared scrollback or a log.
 */
#[AsCommand(
    name: 'privacy:export',
    description: 'Print everything held about a person or an account, as JSON'
)]
final class PrivacyExportCommand extends Command
{
    /** @param Closure(): PersonalData $data */
    public function __construct(private readonly Closure $data)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('person', null, InputOption::VALUE_REQUIRED, 'Person id (sch_persons.PersonId)')
            ->addOption('account', null, InputOption::VALUE_REQUIRED, 'Account id (user.user_id)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$personId, $userId] = PrivacyEraseCommand::target($input);
        if (null === $personId && null === $userId) {
            $output->writeln('<error>Name exactly one of --person and --account.</error>');

            return Command::INVALID;
        }

        /** @var PersonalData $data */
        $data = ($this->data)();
        $export = null !== $personId ? $data->forPerson($personId) : $data->forAccount((int) $userId);
        if (null === $export) {
            $output->writeln(sprintf(
                '<error>There is no %s %d.</error>',
                null !== $personId ? 'person' : 'account',
                $personId ?? $userId
            ));

            return Command::FAILURE;
        }

        $output->writeln(json_encode(
            $export,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Command;

use App\Privacy\Erasure;
use Closure;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ctype_digit;
use function implode;
use function is_string;
use function sprintf;

/**
 * `privacy:erase` — the right to be forgotten, for a person or an account.
 *
 * A dry run by default: it prints what would go, and the free text that names the person
 * for someone to edit by hand. `--apply` erases in one transaction and then flushes the web
 * cache through `cache:flush-persistent`, as `privacy:retention` does and for the same
 * reason. The rules are {@see Erasure}'s. Irreversible except from a backup, so run the dry
 * run first and read it.
 *
 * Partial erasure — "only my phone number" — is an edit, not this command: a moderator
 * clears the field, and privacy:retention blanks its old values in the change log once they
 * are five years old.
 */
#[AsCommand(
    name: 'privacy:erase',
    description: 'Erase a person or an account (dry run unless --apply)'
)]
final class PrivacyEraseCommand extends Command
{
    /** @param Closure(): Erasure $erasure */
    public function __construct(private readonly Closure $erasure)
    {
        parent::__construct();
    }

    /**
     * `--person` / `--account`, exactly one, as positive integers; [null, null] otherwise.
     *
     * @return array{0: ?int, 1: ?int}
     */
    public static function target(InputInterface $input): array
    {
        $person = $input->getOption('person');
        $account = $input->getOption('account');
        $valid = static fn (mixed $v): bool => is_string($v) && ctype_digit($v) && (int) $v > 0;
        if ($valid($person) && null === $account) {
            return [(int) $person, null];
        }
        if ($valid($account) && null === $person) {
            return [null, (int) $account];
        }

        return [null, null];
    }

    protected function configure(): void
    {
        $this
            ->addOption('person', null, InputOption::VALUE_REQUIRED, 'Person id; their accounts go with them')
            ->addOption('account', null, InputOption::VALUE_REQUIRED, 'Account id; its person goes with it, if linked')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Erase; without it, only report');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$personId, $userId] = self::target($input);
        if (null === $personId && null === $userId) {
            $output->writeln('<error>Name exactly one of --person and --account.</error>');

            return Command::INVALID;
        }

        /** @var Erasure $erasure */
        $erasure = ($this->erasure)();
        try {
            $plan = $erasure->plan($personId, $userId);
        } catch (RuntimeException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        if (null !== $plan['person']) {
            $output->writeln(sprintf('Person %d: %s.', $plan['person']['id'], $plan['person']['action']));
        }
        $output->writeln(sprintf(
            'Accounts: %s.',
            [] === $plan['accounts'] ? 'none' : implode(', ', $plan['accounts'])
        ));
        foreach ($plan['counts'] as $what => $n) {
            $output->writeln(sprintf('  %-40s %d', $what, $n));
        }
        if ([] !== $plan['mentions']) {
            $output->writeln('Free text naming them, to edit by hand (not changed by this command):');
            foreach ($plan['mentions'] as $mention) {
                $output->writeln(sprintf('  %s.%s #%s', $mention['table'], $mention['column'], $mention['id']));
            }
        }

        if (! $input->getOption('apply')) {
            $output->writeln('Dry run: nothing changed. --apply erases, and cannot be undone.');

            return Command::SUCCESS;
        }

        $erasure->apply($personId, $userId);
        $output->writeln('Erased.');

        $application = $this->getApplication();
        if (null === $application) {
            $output->writeln('<error>No console application to flush the web cache through.</error>');

            return Command::FAILURE;
        }

        return $application->find('cache:flush-persistent')->run(new ArrayInput([]), $output);
    }
}

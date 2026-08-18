<?php

declare(strict_types=1);

namespace App\Console\Command;

use App\Books\Import\ImportPlan;
use App\Books\Import\ImportStorage;
use App\Books\Import\LegacyColumnMapping;
use App\Books\Import\LibraryImporter;
use App\Books\Import\PlannedRow;
use Books\Model\LibraryTable;
use Closure;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

use function array_slice;
use function count;
use function implode;
use function is_array;
use function is_string;
use function sprintf;

/**
 * `books:import` — the same import the web page runs, without the eight-minute request.
 *
 * ## Why a command as well as a page
 *
 * Because the page cannot always finish. Planning Colegio Mayor takes about nine seconds
 * and applying a complete import of PUC is 16,383 rows of writes, each of which
 * `SionTable::updateEntity()` brackets with two `getObject()` calls; production runs
 * `max_execution_time=240`. A librarian discovering that limit halfway through a
 * destructive import is the failure this exists to avoid — and until the engine came out
 * of the controller there was no way to offer the alternative, because the import only
 * existed as a rendered page.
 *
 * It is also how an import is run *for* somebody: the page needs `administrate` on the
 * library, a console on the server needs no account at all.
 *
 * ## `--apply` is not a flag you can pass by accident
 *
 * Without it the command prints what would happen and stops. With it, and without
 * `--force`, it prints the same summary and asks — because the number that matters is
 * the one on the "inactivate" line, and a complete import of a library whose spreadsheet
 * is a year out of date retires the difference.
 */
#[AsCommand(
    name: 'books:import',
    description: 'Preview or run a library spreadsheet import, with no time limit'
)]
final class ImportLibraryBooksCommand extends Command
{
    /** Rows of each kind printed before the command starts counting instead. */
    private const ROWS_SHOWN = 20;

    /**
     * @param Closure(): LibraryTable $libraryTable
     * @param Closure(): LibraryImporter $importer deferred: `--list` should not open a
     *        spreadsheet reader, and Symfony builds every command to print the list
     */
    public function __construct(
        private readonly Closure $libraryTable,
        private readonly Closure $importer,
        private readonly ImportStorage $storage
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('import', InputArgument::OPTIONAL, 'Import id, as shown by --list')
            ->addOption('list', null, InputOption::VALUE_NONE, 'List imports with their ids and exit')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Actually perform the import')
            ->addOption('force', null, InputOption::VALUE_NONE, 'With --apply, do not ask for confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var LibraryTable $table */
        $table = ($this->libraryTable)();

        if ($input->getOption('list')) {
            $this->listImports($table, $output);

            return self::SUCCESS;
        }

        $importId = (int) $input->getArgument('import');
        if ($importId <= 0) {
            $output->writeln('<error>An import id is required. Use --list to see them.</error>');

            return self::INVALID;
        }

        $import = $table->getLibraryImport($importId);
        if (! is_array($import)) {
            $output->writeln(sprintf('<error>No import with id %d. Use --list.</error>', $importId));

            return self::INVALID;
        }
        $status = $import['status'] ?? null;
        if (LibraryTable::IMPORT_STATUS_COMPLETED === $status) {
            $output->writeln(sprintf('<error>Import %d has already been run.</error>', $importId));

            return self::INVALID;
        }
        if (LibraryTable::IMPORT_STATUS_ABANDONED === $status) {
            $output->writeln(sprintf('<error>Import %d was abandoned and cannot be run.</error>', $importId));

            return self::INVALID;
        }

        $filePath = is_string($import['filePath'] ?? null) ? $import['filePath'] : '';
        //The same restriction the page makes, and for the same reason: an import row can
        //name any path a 2017 text box accepted, and this process runs as the deploy
        //account. Only files this application stored are opened.
        if (! $this->storage->owns($filePath) || ! $this->storage->exists($filePath)) {
            $output->writeln(sprintf('<error>The file for import %d cannot be read: %s</error>', $importId, $filePath));

            return self::INVALID;
        }

        $libraryId = (int) ($import['libraryId'] ?? 0);
        //A console process has no session and therefore no acting user. Saying so beats
        //letting SionTable's provider reach for an identity that cannot exist — the same
        //accommodation books:send-notices and jtranslate:retire make.
        $table->setActingUserId(null);

        /** @var LibraryImporter $importer */
        $importer = ($this->importer)();
        $plan     = $importer->plan(
            $libraryId,
            $filePath,
            is_string($import['worksheet'] ?? null) ? $import['worksheet'] : '',
            LegacyColumnMapping::forward($import['columnMapping'] ?? null),
            true === ($import['isCompleteImport'] ?? false)
        );

        $this->report($plan, $import, $output);

        if (! $plan->canApply()) {
            $output->writeln('<error>This import cannot be run until the problems above are fixed.</error>');

            return self::FAILURE;
        }
        if (! $input->getOption('apply')) {
            $output->writeln("\nNothing was changed. Pass --apply to perform this import.");

            return self::SUCCESS;
        }
        if (! $this->confirmed($input, $output, $plan->statistics()['inactivate'])) {
            $output->writeln('Nothing was changed.');

            return self::SUCCESS;
        }

        $result = $importer->apply($libraryId, $plan);
        $table->updateEntity('library-import', $importId, [
            'booksCreated'     => $result->created,
            'booksUpdated'     => $result->updated,
            'booksInactivated' => $result->inactivated,
            'status'           => LibraryTable::IMPORT_STATUS_COMPLETED,
        ], []);

        $output->writeln(sprintf(
            "\n<info>Done: %d created, %d changed, %d marked inactive, %d collections created.</info>",
            $result->created,
            $result->updated,
            $result->inactivated,
            count($result->newCollections)
        ));

        return self::SUCCESS;
    }

    private function listImports(LibraryTable $table, OutputInterface $output): void
    {
        //No setLibraryId() call: a fresh console process has none set, and
        //getLibraryImports() lists every library's imports when it is absent — which is
        //what --list wants. Setting it to null to "make sure" is what the signature says
        //is an int.
        $imports = $table->getLibraryImports();
        if (! is_array($imports) || [] === $imports) {
            $output->writeln('  (no imports)');

            return;
        }
        foreach ($imports as $import) {
            $output->writeln(sprintf(
                '  %-5d library %-3d %-10s %s',
                (int) ($import['importId'] ?? 0),
                (int) ($import['libraryId'] ?? 0),
                (string) ($import['status'] ?? ''),
                (string) ($import['name'] ?? '')
            ));
        }
    }

    /** @param array<string, mixed> $import */
    private function report(ImportPlan $plan, array $import, OutputInterface $output): void
    {
        $statistics = $plan->statistics();
        $output->writeln(sprintf('Import %s', (string) ($import['name'] ?? '')));
        $output->writeln(sprintf('  worksheet         %s', (string) ($import['worksheet'] ?? '')));
        $output->writeln(sprintf('  columns mapped    %d', $plan->map->count()));
        $unused = $plan->map->unusedColumns();
        if ([] !== $unused) {
            $output->writeln(sprintf('  columns ignored   %s', implode(', ', $unused)));
        }
        $output->writeln(sprintf('  rows in sheet     %d', $plan->sheetRowCount));
        $output->writeln(sprintf('  create            %d', $statistics[PlannedRow::CREATE]));
        $output->writeln(sprintf(
            '  change            %d  (%d matched but unchanged)',
            $statistics[PlannedRow::UPDATE] - $statistics['update-unchanged'],
            $statistics['update-unchanged']
        ));
        $output->writeln(sprintf('  <comment>inactivate        %d</comment>', $statistics[PlannedRow::INACTIVATE]));
        $output->writeln(sprintf('  new collections   %d', $statistics[PlannedRow::CREATE_COLLECTION]));
        $output->writeln(sprintf('  <error>errors            %d</error>', $statistics[PlannedRow::ERROR]));

        foreach (array_slice($plan->errors(), 0, self::ROWS_SHOWN) as $row) {
            $output->writeln(sprintf(
                '    row %-6s barcode %-8s %s %s',
                (string) $row->rowNumber,
                (string) $row->withinLibraryId,
                (string) $row->reason,
                [] === $row->reasonParams ? '' : '(' . implode(', ', $row->reasonParams) . ')'
            ));
        }
        $hidden = count($plan->errors()) - self::ROWS_SHOWN;
        if ($hidden > 0) {
            $output->writeln(sprintf('    ... and %d more', $hidden));
        }

        foreach ($plan->blockers as $blocker) {
            $output->writeln(sprintf(
                '  <error>%s %s</error>',
                $blocker['reason'],
                implode(', ', $blocker['params'])
            ));
        }
    }

    private function confirmed(InputInterface $input, OutputInterface $output, int $inactivations): bool
    {
        if ($input->getOption('force')) {
            return true;
        }
        /** @var QuestionHelper $helper */
        $helper   = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            sprintf(
                "\nThis will write to the catalogue%s. Continue? [y/N] ",
                $inactivations > 0 ? sprintf(', marking %d books inactive', $inactivations) : ''
            ),
            false
        );

        return (bool) $helper->ask($input, $output, $question);
    }
}

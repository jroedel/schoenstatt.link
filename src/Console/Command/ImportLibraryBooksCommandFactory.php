<?php

declare(strict_types=1);

namespace App\Console\Command;

use App\Books\Import\ImportStorage;
use App\Books\Import\LibraryImporter;
use Books\Model\LibraryTable;
use Books\Model\PublicationsTable;
use Books\Service\SpreadsheetReader;
use Psr\Container\ContainerInterface;

/**
 * Builds `books:import`, resolving as little as possible while doing it.
 *
 * The importer arrives as a closure for the reason SendBookNoticesCommandFactory gives:
 * `bin/console list` instantiates every registered command to print one line each, and
 * building a spreadsheet reader and two SionTables for that is a cost paid on every
 * console invocation in the project. `--list` does not need one either.
 */
class ImportLibraryBooksCommandFactory
{
    public function __invoke(ContainerInterface $container): ImportLibraryBooksCommand
    {
        return new ImportLibraryBooksCommand(
            static fn (): LibraryTable => $container->get(LibraryTable::class),
            static fn (): LibraryImporter => new LibraryImporter(
                $container->get(LibraryTable::class),
                $container->get(PublicationsTable::class),
                $container->get(SpreadsheetReader::class)
            ),
            new ImportStorage()
        );
    }
}

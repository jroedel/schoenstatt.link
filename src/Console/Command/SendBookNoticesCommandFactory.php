<?php

declare(strict_types=1);

namespace App\Console\Command;

use Books\Mailing\BooksMailer;
use Books\Model\LibraryTable;
use Psr\Container\ContainerInterface;

/**
 * Builds `books:send-notices`, resolving as little as possible while doing it.
 *
 * Both dependencies arrive as closures. `bin/console` instantiates the command that
 * is being run, but Symfony's application also builds every registered command to
 * list them, and building a mail transport — an SMTP connection's worth of
 * configuration — merely so `bin/console list` can print one line would be a cost
 * paid on every console invocation in the project.
 */
class SendBookNoticesCommandFactory
{
    public function __invoke(ContainerInterface $container): SendBookNoticesCommand
    {
        return new SendBookNoticesCommand(
            static fn (): BooksMailer => $container->get(BooksMailer::class),
            static fn (): LibraryTable => $container->get(LibraryTable::class)
        );
    }
}

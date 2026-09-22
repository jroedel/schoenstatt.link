<?php

/**
 * Drives the tables no page and no test reaches, so their statements reach the recording.
 *
 * Run by tools/sql-surface.sh. Four tables — `files`, `lib_imports`, `mailings`,
 * `user_api_token` — are read or written only behind an authenticated session or a fixture
 * no driver sets up, and a table missing from `test/Db/sql-surface.txt` has no contract at
 * all: its SQL can change during the laminas-db replacement and nothing would say so.
 *
 * **Calling the table methods directly is not a compromise here.** The recording is a set of
 * statement *shapes*, not a sequence, and a method called from the console builds exactly
 * the statement it builds from a controller — the query is assembled in the table class,
 * which neither knows nor cares what called it. What driving from the console does not
 * capture is *how many times* a page issues a statement, and that was never in the recording.
 *
 * Everything runs inside a transaction that is rolled back. The general log records a
 * statement when it executes, not when it commits, so a write's shape is captured while the
 * row it would have written is discarded — which is what lets `mailings` be in the recording
 * without this script leaving a send-log entry behind on every run. All four tables are
 * InnoDB; a MyISAM one would ignore the rollback and keep the row.
 */

declare(strict_types=1);

use App\Laminas\ContainerFactory;
use Books\Model\LibraryTable;
use JUser\Model\ApiTokenTable;
use Laminas\Db\Adapter\Adapter;
use SionModel\Db\Model\FilesTable;
use SionModel\Mailing\Mailer;
use Symfony\Component\Mime\Email;

chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';

//A console process defaults to en_US_POSIX, which is not a key in any nameByLocale array;
//the same reason SchoenstattTest\Container\ContainerSurface pins it.
Locale::setDefault('en_US');

/** @var array<string,mixed> $appConfig */
$appConfig = require 'config/application.config.php';
$container = ContainerFactory::build($appConfig);

/** @var Adapter $adapter */
$adapter    = $container->get(Adapter::class);
$connection = $adapter->getDriver()->getConnection();

$connection->beginTransaction();

try {
    /** @var FilesTable $files */
    $files = $container->get(FilesTable::class);
    $files->getFiles();
    $files->getFile(1);

    /** @var ApiTokenTable $tokens */
    $tokens = $container->get(ApiTokenTable::class);
    $tokens->getTokensForUser(1);
    $tokens->getToken(1);
    $tokens->isLive('no-such-jti', 1);
    $tokens->pruneExpired(1);

    /** @var LibraryTable $libraries */
    $libraries = $container->get(LibraryTable::class);
    $libraries->getLibraryImports();
    $libraries->getLibraryImport(1);

    //`reportMailing()` returns early without a table, and the container builds this Mailer
    //without one — `SionModel\Service\MailerFactory` passes four arguments and the fifth is
    //optional, so "a mailer without a table sends without reporting", as the class says.
    //`Books\Mailing\BooksMailer` is the only caller that reports, because it passes its
    //library table up. Setting one here is what puts the `mailings` INSERT in the recording.
    /** @var Mailer $mailer */
    $mailer = $container->get(Mailer::class);
    $mailer->setSionTable($libraries);
    $mailer->reportMailing(
        (new Email())
            ->from('sql-surface@example.com')
            ->to('sql-surface@example.com')
            ->subject('sql-surface')
            ->html('<p>sql-surface</p>')
    );
} finally {
    $connection->rollback();
}

echo "drove files, user_api_token, lib_imports and mailings; rolled back\n";

<?php

/**
 * Drives the tables no page and no test reaches, so their statements reach the recording.
 *
 * Run by tools/sql-surface.sh. Four tables — `files`, `lib_imports`, `mailings`,
 * `user_api_token` — are read or written only behind an authenticated session or a fixture
 * no driver sets up, and a table missing from `test/Db/sql-surface.txt` has no contract at
 * all: its SQL can change and nothing would say so.
 *
 * It also drives the reads and writes whose presence in the recording depends on the
 * *state* of the database or of the cache rather than on the code — a phrase that is
 * missing, a cached list that happens to be warm. Those are the ones that make `--check`
 * flap, and a flapping gate teaches people to regenerate the recording.
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
use Books\Model\PublicationsTable;
use JTranslate\Model\TranslationsTable;
use JUser\Model\ApiTokenTable;
use SionModel\Db\Connection;
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

$connection = $container->get(Connection::class);
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

    //**A cached read is in the recording only when its cache happens to be cold**, and this
    //one made `--check` flap twice on 2026-09-22: `getMergedPublicationIds()` is read by the
    //sitemap and nothing else, and any publication write expires it — so the run after a
    //smoke suite issued the statement and the run after that did not, with no code change
    //between them. A gate that flaps is not a gate, and the obvious way to quiet it is to
    //regenerate the recording, which is exactly what must not happen. Expiring the key first
    //makes the read cold on every run.
    /** @var PublicationsTable $publications */
    $publications = $container->get(PublicationsTable::class);
    $publications->removeDependentCacheItems('publication');
    $publications->getMergedPublicationIds();

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

    //**Phrase discovery only writes when a phrase is missing**, and against a database that
    //already holds every phrase the pass renders it writes nothing at all — so four statement
    //shapes came and went with the state of `trans_phrases` rather than with the code. A
    //phrase nobody will ever render is missing on every run, which makes them constant.
    /** @var TranslationsTable $translations */
    $translations = $container->get(TranslationsTable::class);
    //The acting-user provider reads a session, and there is none here; see
    //writeMissingPhrasesToDb(), which resolves it once before the first write.
    $translations->setActingUserId(null);
    $translations->reportMissingTranslation([
        'message'     => 'sql-surface: a phrase no page renders',
        'text_domain' => 'default',
        'locale'      => 'en_US',
    ]);
    $translations->writeMissingPhrasesToDb('sql-surface');

    //And the translation write paths, which the insert above leaves untouched: writing one
    //is the `ON DUPLICATE KEY UPDATE`, retracting it is the DELETE.
    $phrase = $connection
        ->select("SELECT `translation_phrase_id` FROM `trans_phrases` WHERE `origin_route` = 'sql-surface'")
        ->current();
    if (null !== $phrase) {
        $phraseId = (int) $phrase['translation_phrase_id'];
        $translations->updatePhrase($phraseId, ['de_DE' => 'sql-surface']);
        $translations->updatePhrase($phraseId, ['de_DE' => null]);
    }
} finally {
    $connection->rollBack();
}

echo "drove files, user_api_token, lib_imports, mailings, the merged-publication read and the phrase writes; rolled back\n";

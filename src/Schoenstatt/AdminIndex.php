<?php

declare(strict_types=1);

namespace App\Schoenstatt;

use JTranslate\Model\TranslationsTable;
use SionModel\Service\ProblemService;

use function count;

/**
 * The content of the admin landing page: which pages it links to, and the two
 * counters it puts a badge on.
 *
 * A copy of `Schoenstatt\Controller\AdminController::indexAction()`, and copied for
 * the reason docs/strangler.md gives: sharing would mean *rewriting* the laminas
 * action, which is still live behind the canary. Adding a row to its literal array,
 * as the kernel-switch move did, is not that — it is the one edit shape the
 * duplication tolerates, because the parity test catches a half-done one.
 * `test/Integration/AdminIndexParityTest` is what makes the duplicate safe: it
 * drives the laminas action and compares both halves against this class. When the
 * laminas route is deleted, the action goes and the test goes with it.
 *
 * The `pages` list is route names, not URLs, exactly as the original — the template
 * both filters them through the ACL and assembles them, and it can only do that
 * from the name.
 */
final class AdminIndex
{
    /**
     * Route name => link text, in the order the page lists them.
     *
     * Verbatim from the laminas action. The ACL filtering the original applies per
     * item (`isAllowed('route/' . $route)`) is left to the template, which is where
     * it lives on the laminas side too.
     *
     * @var array<string, string>
     */
    public const PAGES = [
        'persons/create'               => 'Add new person',
        'associations/create'          => 'Add new association',
        'assignments/create'           => 'Add new assignment',
        'roles/create'                 => 'Add new role',
        'sion-model/view-changes'      => 'View Changes',
        'admin/import-father'          => 'Import Schoenstatt Father',
        'juser'                        => 'User Management',
        'jtranslate'                   => 'Manage Translations',
        'sion-model/data-problems'     => 'Data problems',
        'admin/literature-maintenance' => 'Literature maintenance',
        //the Symfony-kernel canary toggle, moved here from the navbar 2026-08-11.
        //`route/kernel-switch` is sch_administrator-only, so the template's per-item
        //ACL check hides it from the moderators and translators who also reach this
        //page — the same filtering that already hides six of the ten links above.
        'kernel-switch'                => 'Switch kernel',
    ];

    /**
     * The two counters, keyed by the route they decorate.
     *
     * Both types are the original's and are kept rather than tidied: the
     * translation count is cast to string and the problem count is left an int.
     * `TwbBundleBadge` renders either the same way, and the parity test compares
     * these values with the laminas action's — so normalising them here would make
     * that comparison lie about what the laminas page produces.
     *
     * @return array{jtranslate: string, 'sion-model/data-problems': int}
     */
    public static function badges(TranslationsTable $translations, ProblemService $problems): array
    {
        return [
            'jtranslate'               => (string) $translations->getOutstandingTranslationCount(),
            'sion-model/data-problems' => count($problems->getCurrentProblems()),
        ];
    }
}

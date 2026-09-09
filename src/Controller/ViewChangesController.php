<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\ServiceBridge;
use SionModel\Db\Model\SionTable;
use SionModel\Service\ChangesCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use UnexpectedValueException;

use function is_array;
use function is_numeric;
use function is_string;

/**
 * GET /sm/view-changes — every recent edit to the database, newest first.
 *
 * Guarded by `route/sion-model/view-changes`: `sch_general_moderator` and
 * `view_changes`, 3 effective roles of 43.
 *
 * ## It could not be ported until two other things were true
 *
 * **The page had to work.** It exhausted a 512 MB limit before rendering anything, so
 * there was no laminas rendering to compare a port against — and a port is verified by
 * that comparison. Fixed first, in SionModel; see docs/laminas-exit.md.
 *
 * **`formatEntity` had to cover the types this page actually meets.** Its entity column
 * formats whatever type each change row names, and `sch_changes` holds 18,243
 * `publication` rows and 1,317 `role` rows — the two App\Laminas\EntityFormatter used
 * to refuse. Both are reproduced now, which is what unblocked this.
 *
 * ## The read of the config is the laminas action's, reproduced
 *
 * `changes_max_rows` bounds the fetch *and* the display, and `changes_show_all` picks
 * between every registered table and the single `changes_model`. Both are read the same
 * way `SionModelController::viewChangesAction()` reads them, because the two front
 * controllers must not disagree about how much of the database a page reads.
 */
final class ViewChangesController
{
    /** What viewChangesAction() falls back to when `changes_max_rows` is unset or non-numeric. */
    private const DEFAULT_MAX_ROWS = 500;

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $config   = $this->sionModelConfig();
        $maxRows  = $this->maxRows($config);
        $changes  = $this->changes($config, $maxRows);

        return new Response($this->twig->render('sion-model/view-changes.html.twig', [
            //view-changes.phtml sets headTitle('View Changes'); the <h1> is its own and
            //says something different, which is reproduced rather than reconciled
            'page_title'  => 'View Changes',
            'changes'     => $changes,
            'max_rows'    => $maxRows,
            'show_entity' => true,
        ]));
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int|string, array<string, mixed>>
     */
    private function changes(array $config, int $maxRows): array
    {
        //`! isset || truthy` — the laminas action's test, which treats an absent key as
        //"show all" rather than as false
        if (! isset($config['changes_show_all']) || $config['changes_show_all']) {
            /** @var ChangesCollector $collector */
            $collector = $this->laminas->get(ChangesCollector::class);

            return $collector->getAllChanges($maxRows);
        }

        $model = $config['changes_model'] ?? null;
        if (! is_string($model) || ! $this->laminas->has($model)) {
            throw new UnexpectedValueException('The \'changes_model\' configuration is incorrect.');
        }
        /** @var SionTable $table */
        $table = $this->laminas->get($model);

        return $table->getChanges($maxRows);
    }

    /** @param array<string, mixed> $config */
    private function maxRows(array $config): int
    {
        $configured = $config['changes_max_rows'] ?? null;

        return is_numeric($configured) ? (int) $configured : self::DEFAULT_MAX_ROWS;
    }

    /** @return array<string, mixed> */
    private function sionModelConfig(): array
    {
        $config = $this->laminas->config()['sion_model'] ?? null;

        return is_array($config) ? $config : [];
    }
}

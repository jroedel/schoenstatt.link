<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\ServiceBridge;
use Books\Form\TextSearchForm;
use Books\Model\EventTextTable;
use JTranslate\Controller\Plugin\NowMessenger;
use Laminas\Db\Sql\Predicate\Like;
use Laminas\Db\Sql\Predicate\Operator;
use Laminas\Db\Sql\Predicate\Predicate;
use Laminas\Db\Sql\Predicate\PredicateSet;
use SionModel\Entity\Entity;
use SionModel\Service\EntitiesService;
use SionModel\Text\Text;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_array;
use function is_string;
use function sprintf;

/**
 * GET /texts — full-text search over the Fr. Kentenich corpus.
 *
 * Guarded `texts_user`, which is the sharpest guard in this batch and the reason the
 * page reads the way it does: the corpus is not public, and its own "work in progress"
 * message explains that an account whose email matches one recorded on
 * schoenstatt-fathers.link is what gets you in.
 *
 * The laminas route is `texts` and its action is `searchAction`, which renders
 * `books/texts/index` — **not** its own `indexAction`, which is a different query that
 * no route reaches. Only the search action is ported; `texts/create` and
 * `texts/jk-import` stay on laminas.
 *
 * ## The size of this page
 *
 * A matching query returns whole texts, and the template prints an excerpt of each with
 * the search term highlighted. Measured on laminas: `/en/texts?search=Bund` is
 * **1,014,522 bytes**. That is the existing behaviour and this reproduces it rather than
 * paginating — `MAX_SEARCH_RESULTS` exists on the publication search and deliberately not
 * here — but it is worth knowing before adding this path to anything that fetches it in
 * a loop.
 *
 * The excerpting is done here rather than in the template because
 * `SionModel\Text\Text::excerpt()` and `::highlight()` are static methods, and reaching a
 * static from Twig would mean either a new function on the extension for a single caller
 * or exposing arbitrary static calls. The map is keyed by `textId` the way
 * LiteratureController keys its cover images.
 */
final class TextsController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $form = $this->form();
        $form->setData($request->query->all());

        $objects = null;
        $search  = null;
        if ($form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            if ([] !== $data) {
                $table = $this->table();
                //`updateColumns` maps entity field names onto database columns, and the
                //predicate is built against the *columns* — searchAction() reads the map
                //rather than naming them, so a renamed column moves both at once.
                //
                //Read through EntitiesService rather than the table:
                //`SionTable::getEntitySpecification()` is **protected**, so the laminas
                //controller reaches it as an inherited method and nothing outside the
                //class can. EntitiesService is the public door to the same merged config
                //and is what App\Laminas\EntityFormatter already uses.
                $fieldMap = $this->entitySpec()->updateColumns;

                $where = new PredicateSet();
                if (isset($data['search'])) {
                    $search     = (string) $data['search'];
                    $searchLike = sprintf('%%%s%%', $search);
                    $clause     = new Predicate();
                    $clause->addPredicates([
                        new Like($fieldMap['title'], $searchLike),
                        new Like($fieldMap['markdownText'], $searchLike),
                        new Like($fieldMap['publicNotes'], $searchLike),
                        //an exact match on the id, so a bare number finds one text
                        new Operator($fieldMap['textId'], Operator::OPERATOR_EQUAL_TO, $search),
                    ], PredicateSet::OP_OR);
                    $where->addPredicate($clause);
                }

                /** @var array<mixed> $objects */
                $objects = $table->queryObjects('text', $where);
            }
        }

        if (is_array($objects) && [] === $objects) {
            $this->nowMessage(NowMessenger::NAMESPACE_INFO, 'No results found.');
        }

        return new Response($this->twig->render('books/texts-index.html.twig', [
            //searchAction() calls no headTitle(), and `texts` is not in the navigation
            //config, so no title and no breadcrumb trail
            'page_title' => '',
            'form'       => $form,
            'objects'    => $objects,
            'search'     => $search,
            'excerpts'   => $this->excerpts($objects, $search),
        ]));
    }

    /**
     * One highlighted excerpt per result, or an empty map when there is no query.
     *
     * `Text::excerpt()` takes a radius of 100 characters either side and `::highlight()`
     * wraps each occurrence in `<mark>`; both are the .phtml's own calls with its own
     * options. **The result is markup and is printed raw** — `highlight()` inserts tags
     * into text it does not escape, which is a pre-existing property of the laminas page
     * rather than something this port introduces. The corpus is imported from published
     * writings by an administrator, not user-submitted.
     *
     * @param array<mixed>|null $objects
     * @return array<int|string, string>
     */
    private function excerpts(?array $objects, ?string $search): array
    {
        if (null === $objects || null === $search || '' === $search) {
            return [];
        }

        $excerpts = [];
        foreach ($objects as $object) {
            if (! is_array($object) || ! isset($object['textId'])) {
                continue;
            }
            $plain = $object['plainText'] ?? '';
            $excerpts[(int) $object['textId']] = (string) Text::highlight(
                Text::excerpt(is_string($plain) ? $plain : '', $search),
                $search,
                ['format' => '<mark>\1</mark>']
            );
        }

        return $excerpts;
    }

    /**
     * The search form, constructed directly — `searchAction()` does `new TextSearchForm()`
     * and the class has no factory and no dependencies. Its `inLanguage` select is
     * declared with no value options and the action never populates them, so it renders
     * empty; the .phtml does not render it at all.
     *
     * @return TextSearchForm
     */
    private function form(): TextSearchForm
    {
        return new TextSearchForm();
    }

    private function table(): EventTextTable
    {
        /** @var EventTextTable $table */
        $table = $this->laminas->get(EventTextTable::class);

        return $table;
    }

    /** The `text` entity specification, out of the merged config. */
    private function entitySpec(): Entity
    {
        /** @var EntitiesService $entities */
        $entities = $this->laminas->get(EntitiesService::class);

        return $entities->getEntities()['text'];
    }

    /** @see LiteratureController::nowMessage() */
    private function nowMessage(string $namespace, string $message): void
    {
        /** @var NowMessenger $messenger */
        $messenger = $this->laminas->get('ControllerPluginManager')->get('nowMessenger');
        $messenger->setNamespace($namespace)->addMessage($message);
    }
}

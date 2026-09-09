<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Books\Model\DictionaryTable;
use App\Json;
use Spatie\SchemaOrg\BaseType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Markup;

use function is_array;
use function is_string;

/**
 * GET /dictionary and GET /dictionary/{inLanguage} — the Fr. Kentenich translation
 * dictionaries.
 *
 * Both guarded `['guest', 'user']`. Two very different pages behind one controller,
 * because that is how the laminas side has it and because the index is not a page at
 * all: `books/dictionary/index.phtml` is a file containing `<?php` and nothing else,
 * so /dictionary renders the layout around an empty body. Reproduced exactly —
 * inventing a landing page here would be a feature, not a port, and the dictionaries
 * are reached from /literature anyway.
 *
 * ## The language page
 *
 * `inLanguageAction()` in four steps, all reproduced: look the language up in
 * `getAvailableDictionaryLanguages()` and bounce to the index if it is not one;
 * fetch the active entries; build the schema.org payload; and register a visit.
 *
 * The **visit registration is a write on a GET**, and it is what the "Total views"
 * line at the foot of the page counts. Kept: a ported route that stopped writing it
 * would silently stop counting, and nothing would fail.
 *
 * The unknown-language branch differs from laminas in one visible way, and it is the
 * flash message: laminas sets "Language not found" in the error namespace and this
 * cannot, because a Symfony-served route has no flash messenger to write to — the
 * layout's flash_messages() only *reads* what a laminas action left in the session.
 * The redirect itself is identical. Recorded rather than papered over; adding a
 * writable flash bridge is its own change.
 */
final class DictionaryController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    /** GET /dictionary — the layout, and nothing inside it. */
    public function index(Request $request): Response
    {
        return new Response($this->twig->render('books/dictionary.html.twig', [
            //no headTitle(), not in the `navigation` config: no title prefix, no trail
            'page_title' => '',
        ]));
    }

    /** GET /dictionary/{inLanguage} — every active entry in one dictionary. */
    public function inLanguage(Request $request): Response
    {
        $inLanguage = (string) $request->attributes->get('inLanguage');

        /** @var DictionaryTable $table */
        $table = $this->laminas->get(DictionaryTable::class);

        //One row per available dictionary: `inLanguage`, `locale` and a count. Annotated
        //because DictionaryTable::getAvailableDictionaryLanguages() carries no return
        //type at all, which PHPStan reads as a list of strings and then declares the
        //whole lookup below dead.
        /** @var list<array{inLanguage?: string, locale?: string}> $available */
        $available  = $table->getAvailableDictionaryLanguages();
        $dictionary = null;
        foreach ($available as $row) {
            if ($inLanguage === ($row['inLanguage'] ?? null)) {
                $dictionary = $row;
                break;
            }
        }
        if (null === $dictionary) {
            return new RedirectResponse($this->urls->path('dictionary'), Response::HTTP_FOUND);
        }

        //SionTable::queryObjects() documents $query as PredicateInterface[] while its
        //body handles a plain field => value array; inLanguageAction() passes this exact
        //array. The docblock is wrong, not the call.
        //@phpstan-ignore argument.type
        $objects = $table->queryObjects('dictionary-entry', [
            'locale'   => $dictionary['locale'] ?? '',
            'isActive' => true,
        ]);

        $visitKey = 'dictionary-' . ($dictionary['locale'] ?? '');
        $table->registerVisit($visitKey, 0);
        $visits = $table->getVisitCounts($visitKey, [0])[0] ?? ['total' => 0, 'pastMonth' => 0];

        $inLanguageName = $table->getLanguageName($inLanguage);

        return new Response($this->twig->render('books/dictionary-in-language.html.twig', [
            //`page_title` and the breadcrumbs are built in the template, not here: every
            //one of these strings goes through translate(), and translate() has to run
            //where the page's text domain is in scope. What the controller supplies is
            //the raw material — the format string, the language name and the three URLs.
            'title_format'     => DictionaryTable::DICTIONARY_TITLE_FORMAT,
            'literature_url'   => $this->urls->path('publications'),
            'dictionary_url'   => $this->urls->path('dictionary/inLanguage', ['inLanguage' => $inLanguage]),
            'in_language'      => $inLanguage,
            'in_language_name' => $inLanguageName,
            'objects'          => $objects,
            'visits'           => $visits,
            'schemata'         => new Markup(
                Json::encode($this->combineSchema($table->getDictionarySchema($inLanguage), $objects)),
                'UTF-8'
            ),
        ]));
    }

    /**
     * `DictionaryController::combineSchema()`, verbatim: the dictionary's own schema
     * followed by each entry's, for the one `<script type="application/ld+json">` at
     * the foot of the page. An entry with no schema is skipped rather than nulled.
     *
     * @param array<int|string, array<string, mixed>> $entryObjects
     * @return list<array<string, mixed>>
     */
    private function combineSchema(BaseType $dictionarySchema, array $entryObjects): array
    {
        $schemata = [$dictionarySchema->toArray()];
        foreach ($entryObjects as $object) {
            if (isset($object['schema']) && $object['schema'] instanceof BaseType) {
                $schemata[] = $object['schema']->toArray();
            }
        }

        return $schemata;
    }
}

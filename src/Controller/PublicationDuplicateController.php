<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\HostMessages;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Sion\EntityShow;
use App\Sion\SiteWideIdentifier;
use Books\Form\CopyToMainCorpusForm;
use Books\Form\CreateNewEditionForm;
use Books\Model\PublicationsTable;
use SionModel\Form\Form;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
use Schoenstatt\Validator\SchoenstattLinkIdentifier as IdentifierValidator;
use SionModel\Messaging\FlashMessages;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_string;

/**
 * The two actions that make a new publication row out of an existing one:
 * `publication-copy-to-main-corpus` and `publication-create-new-edition`. Ported
 * 2026-09-08 (batch 16), the last two per-row publication routes on laminas.
 *
 * ## One controller, because they are one shape
 *
 * Both open with the same row, both render a two-element confirmation form, both write
 * on a valid POST and redirect to the result, and both answer a bad token with a
 * re-rendered form and a 400. What differs is the precondition (only a data-sourced
 * publication can be copied; any publication can have an edition), the write
 * (`copyPublicationToMainCorpus()` versus `createNewEdition()`), and where the redirect
 * goes (the new publication's page versus its edit form).
 *
 * ## What the port changed, on both front controllers
 *
 * `createNewEditionAction()` **wrote on a plain GET** until this port — `createEntity()`
 * then a redirect, with no method check, no token and no confirmation — exactly the shape
 * the copy action had until 2026-08-14 and the same hazard: a browser prefetching the
 * "Add another edition" link a moderator hovered over filed a publication. The field list
 * moved to `PublicationsTable::createNewEdition()` and the laminas action confirms now
 * too, so the hazard is closed under the canary as well as here.
 *
 * ## What the port changed, on this side only
 *
 * - **The per-row ACL check runs for both actions.** The copy action always had one, by
 *   way of `parent::showAction()`. The new-edition action never did — `getEntityObject()`
 *   makes none — so a moderator refused a publication's own page could still clone it
 *   into an edit form and read every field there. `EntityShow::row()` asks the row's
 *   `show` permission for both, which is the check the show page makes.
 * - **The confirmation registers no visit.** The copy action's confirmation did, as a
 *   by-product of reusing `showAction()`, and built a comment form nothing rendered. A
 *   confirmation is not a page view of the publication; `row()` is the half of `load()`
 *   without either.
 *
 * ## Status codes
 *
 * 400 on a failed token, as the laminas copy action answers and as JTranslate's and the
 * library delete answer — not the 401 `EntityDeleteController` reproduces, which
 * docs/BACKLOG.md records as wrong.
 */
final class PublicationDuplicateController
{
    private const ENTITY = 'publication';

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly EntityShow $show,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly HostMessages $messages
    ) {
    }

    /** GET confirms, POST copies a data-sourced publication into the main corpus. */
    public function copyToMainCorpus(Request $request): Response
    {
        $entity = $this->publication($request);
        if ($entity instanceof Response) {
            return $entity;
        }

        //`empty()` rather than `! isset()`, as the laminas action: a data-sourced row
        //carries the source's name, and an empty string would be as good as none
        if (empty($entity['dataSource'])) {
            $this->messages->flash(
                FlashMessages::NAMESPACE_ERROR,
                'Only data-sourced publications can be copied.'
            );

            return new RedirectResponse($this->publicationUrl($entity));
        }

        $form   = new CopyToMainCorpusForm();
        $status = Response::HTTP_OK;

        if ($request->isMethod('POST')) {
            if ($this->confirmed($form, $request)) {
                $newId = $this->table()->copyPublicationToMainCorpus($entity['publicationId']);
                $this->messages->flash(
                    FlashMessages::NAMESPACE_SUCCESS,
                    'Publication copied into main literature corpus.'
                );

                return new RedirectResponse(
                    $this->urls->path('publication', ['sw_id' => $this->identifier((int) $newId)])
                );
            }
            $status = $this->expired();
        }

        return new Response(
            $this->twig->render('books/publication-copy-to-main-corpus.html.twig', [
                'page_title'  => 'Copy into main corpus',
                'form'        => $form,
                'form_action' => $request->getRequestUri(),
                'entity'      => $entity,
                'cancel_url'  => $this->publicationUrl($entity),
            ]),
            $status
        );
    }

    /** GET confirms, POST creates a new edition and opens it for editing. */
    public function createNewEdition(Request $request): Response
    {
        $entity = $this->publication($request);
        if ($entity instanceof Response) {
            return $entity;
        }

        $form   = new CreateNewEditionForm();
        $status = Response::HTTP_OK;

        if ($request->isMethod('POST')) {
            if ($this->confirmed($form, $request)) {
                $newId = $this->table()->createNewEdition((int) $entity['publicationId']);

                return new RedirectResponse(
                    $this->urls->path('publication-edit', ['sw_id' => $this->identifier($newId)])
                );
            }
            $status = $this->expired();
        }

        return new Response(
            $this->twig->render('books/publication-new-edition.html.twig', [
                'page_title'  => 'Add another edition',
                'form'        => $form,
                'form_action' => $request->getRequestUri(),
                'entity'      => $entity,
                'cancel_url'  => $this->publicationUrl($entity),
            ]),
            $status
        );
    }

    /**
     * The publication named by `sw_id`, or the response that stands in for it.
     *
     * A malformed identifier is a 404 (the route constraint makes that unreachable, but
     * the controller does not rely on it); a missing, unprojectable or refused row is
     * what `SionController::showAction()` does — a flash and a redirect to the literature
     * index — with `EntityShow::deniedMessage()` choosing between "not found" and "denied".
     *
     * @return array<string, mixed>|Response
     */
    private function publication(Request $request): array|Response
    {
        $swId = $request->attributes->get('sw_id');
        if (! is_string($swId)) {
            return $this->notFound();
        }

        $id = SiteWideIdentifier::toId(IdentifierValidator::ENTITY_PUBLICATION, $swId);
        if (null === $id) {
            return $this->notFound();
        }

        $entity = $this->show->row(self::ENTITY, $id);
        if (null === $entity) {
            $this->messages->flash(FlashMessages::NAMESPACE_ERROR, $this->show->deniedMessage(self::ENTITY, $id));

            return new RedirectResponse($this->urls->path('publications'));
        }

        return $entity;
    }

    /**
     * Whether the POST carries a valid token.
     *
     * Both forms declare `security` and `submit` and nothing else, so validity *is* the
     * CSRF check. Read through `isValid()` rather than the validator directly so that the
     * form is what decides — which is also what the fuzz harness measures.
     */
    private function confirmed(Form $form, Request $request): bool
    {
        /** @var array<string, mixed> $posted */
        $posted = $request->request->all();
        $form->setData($posted);

        return $form->isValid();
    }

    /**
     * An expired or forged token re-renders the confirmation rather than writing. The
     * message is a *now* message — it belongs on the page being returned, and a flash
     * would surface on whatever page came next instead.
     */
    private function expired(): int
    {
        $this->messages->now(FlashMessages::NAMESPACE_ERROR, 'Your confirmation expired. Please try again.');

        return Response::HTTP_BAD_REQUEST;
    }

    /** @param array<string, mixed> $entity */
    private function publicationUrl(array $entity): string
    {
        $params = [];
        if (is_string($entity['identifier'] ?? null)) {
            $params['sw_id'] = $entity['identifier'];
        }
        if (is_string($entity['slug'] ?? null)) {
            $params['slug'] = $entity['slug'];
        }

        return $this->urls->path('publication', $params);
    }

    /** `SL2…L` for a publication id — what every redirect on this surface is keyed by. */
    private function identifier(int $publicationId): string
    {
        $identifier = (new ToSchoenstattLinkIdentifier('publication'))->filter($publicationId);

        return is_string($identifier) ? $identifier : (string) $publicationId;
    }

    private function table(): PublicationsTable
    {
        /** @var PublicationsTable $table */
        $table = $this->laminas->get(PublicationsTable::class);

        return $table;
    }

    private function notFound(): Response
    {
        return new Response(
            'Publication not found.',
            Response::HTTP_NOT_FOUND,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }
}

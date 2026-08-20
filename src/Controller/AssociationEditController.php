<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Laminas\Form\FormInterface;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use RuntimeException;
use Schoenstatt\Filter\SchoenstattLinkIdentifier as IdentifierFilter;
use Schoenstatt\Form\AssociationForm;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Validator\SchoenstattLinkIdentifier as IdentifierValidator;
use Schoenstatt\Validator\TimeZone;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_array;
use function is_string;

/**
 * GET|POST /{sw_id}/edit — the shrine (and association) edit form.
 *
 * **The first form route on the Symfony kernel**, and the one `docs/strangler.md`
 * called the largest single thing standing between here and the end of the
 * migration. Two claims in that document turned out to be one claim too many:
 *
 * - "There is no form layer on the Symfony side" was true, and
 *   App\Form\BootstrapFormRenderer is now it.
 * - "`JUser\Module::onBootstrap()`'s `GlobalAdapterFeature::setStaticAdapter()` has
 *   to be dealt with before the first form route moves" was true of *some* forms and
 *   not this one. Only `CreateRoleForm`, `EditUserForm`, `DeleteUserForm` and
 *   `EditPhraseForm` read the static adapter, through a `NoRecordExists` validator;
 *   `AssociationForm` and `SionForm` never touched it, so this route was portable
 *   while the user forms were not. **Settled 2026-08-14**: all four take the adapter
 *   as a constructor argument and the static registry no longer exists, so nothing
 *   about JUser blocks a form route now.
 *
 * ## Why it is safe to serve this one
 *
 * `route/association-edit` is guarded `['sch_moderator', 'sch_user']` and
 * App\Authorization\RouteGuard enforces exactly that declaration, so the page refuses
 * the same visitors it refuses on laminas. Worth knowing while reading that guard:
 * registration grants `sch_user`, so "signed in" and "may edit an association" are
 * the same thing on this site today. Porting the route does not change that, but it
 * is the ceiling an API bot inherits — see docs/api-v3.md.
 *
 * ## The form is the application's, not a copy
 *
 * The form comes from the laminas container through the ServiceBridge, so it is the
 * same `AssociationForm` instance-shape the laminas controller renders, with its
 * value options populated by `AssociationFormFactory` and its validation rules
 * coming from App\Schoenstatt\Association\AssociationInputFilterSpec. Nothing about
 * validation is reimplemented here; that is the whole point of the arrangement, and
 * `test/Integration/AssociationValidationParityTest` is what keeps it honest.
 *
 * CSRF works for the same reason it works on laminas: App\Http\SessionListener has
 * already started the laminas session, so `Laminas\Validator\Csrf` finds its
 * container. A token minted by the laminas rendering is therefore accepted by this
 * one and vice versa — which matters while production serves one and the capsule the
 * other.
 */
final class AssociationEditController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $swId = $request->attributes->get('sw_id');
        if (! is_string($swId)) {
            return $this->notFound();
        }

        $id = $this->associationIdOf($swId);
        if (null === $id) {
            return $this->notFound();
        }

        /** @var SchoenstattTable $table */
        $table  = $this->laminas->get(SchoenstattTable::class);
        $entity = $table->getAssociation($id);
        if (! is_array($entity) || [] === $entity) {
            return $this->notFound();
        }

        $form = $this->form($entity);

        if ($request->isMethod('POST')) {
            //->all() rather than the request object, to match
            //SionController::getPostDataForEditAction(), which hands the form
            //`getPost()->toArray()`.
            $form->setData($request->request->all());

            if ($form->isValid()) {
                /** @var array<string, mixed> $data */
                $data    = $form->getData();
                $updated = $table->updateEntity('association', $id, $data);

                $this->flash(
                    FlashMessenger::NAMESPACE_SUCCESS,
                    'Association successfully updated.'
                );

                $identifier = is_array($updated) && isset($updated['identifier'])
                    ? (string) $updated['identifier']
                    : $swId;

                return new RedirectResponse(
                    $this->urls->path('association', ['sw_id' => $identifier]),
                    Response::HTTP_FOUND
                );
            }
        } else {
            $form->setData($entity);
        }

        return new Response($this->twig->render('schoenstatt/association-edit.html.twig', [
            'page_title'  => 'Edit Association',
            'breadcrumbs' => [
                ['label' => 'Associations', 'href' => $this->urls->path('associations')],
                //'translate' => false because this label is the record's own name.
                //The layout translates crumbs, and a translator miss is what files a
                //phrase — so translating here would put one association name into the
                //phrase table per association edited, permanently, and stale the
                //moment a moderator renames it.
                [
                    'label'     => (string) ($entity['name'] ?? ''),
                    'href'      => $this->urls->path(
                        'association',
                        ['sw_id' => (string) ($entity['identifier'] ?? $swId)]
                    ),
                    'translate' => false,
                ],
            ],
            'form'        => $form,
            'form_action' => $this->urls->path('association-edit', ['sw_id' => $swId]),
            'entity'      => $entity,
        ]));
    }

    /**
     * The form the laminas controller would have built, including the two
     * adjustments `Schoenstatt\Controller\AssociationsController::editAction()` makes
     * after `SionController` hands it back.
     *
     * @param array<string, mixed> $entity
     * @return FormInterface<array<string, mixed>>
     */
    private function form(array $entity): FormInterface
    {
        $form = $this->laminas->get(AssociationForm::class);
        if (! $form instanceof FormInterface) {
            throw new RuntimeException('The container did not return an AssociationForm.');
        }

        //Narrow the time-zone dropdown to the association's country, exactly as
        //AssociationsController::editAction() does. Display only: the *validation*
        //domain stays the full IANA list, because
        //App\Schoenstatt\Association\AssociationFieldDomains reads it from the source
        //rather than from this element. A shrine whose stored zone is outside its
        //country's list therefore still saves.
        $country = $entity['country'] ?? null;
        if (is_string($country) && '' !== $country) {
            $zones = TimeZone::getTimeZoneValueOptions($country);
            if ([] !== $zones) {
                $element = $form->get('timeZoneId');
                if ($element instanceof \Laminas\Form\Element\Select) {
                    $element->setValueOptions($zones);
                }
            }
        }

        //The shrine-specific relabelling of publicNotes, also from editAction().
        $kind = $entity['kind'] ?? null;
        if ('sch-shrine' === $kind || 'sch-wayside-shrine' === $kind) {
            $notes = $form->get('publicNotes');
            $notes->setLabel('First-time visitor information');
            $notes->setAttribute(
                'placeholder',
                "Turn right at the first driveway after getting off the highway.\n\n"
                . "**Confession**: By appointment, please don't hesitate to call."
            );
        }

        return $form;
    }

    /**
     * The association id behind a site-wide identifier, or null when the identifier
     * is not one.
     *
     * The route constraint already refuses anything that is not shaped like an
     * association id, so this is the second of two checks rather than the only one —
     * but `AssociationsController::getEntityIdParam()` *throws* on a bad identifier
     * and a Symfony route should answer 404, so the validator is consulted rather
     * than trusted to have been.
     */
    private function associationIdOf(string $swId): ?int
    {
        $validator = new IdentifierValidator(IdentifierValidator::ENTITY_ASSOCIATION);
        if (! $validator->isValid($swId)) {
            return null;
        }

        $filtered = (new IdentifierFilter(IdentifierValidator::ENTITY_ASSOCIATION))->filter($swId);

        return is_int($filtered) ? $filtered : (is_numeric($filtered) ? (int) $filtered : null);
    }

    /**
     * A flash message in the laminas session, so that the page redirected *to* —
     * which is still served by laminas — finds it where its layout looks.
     */
    private function flash(string $namespace, string $message): void
    {
        $messenger = new FlashMessenger();
        $messenger->setNamespace($namespace)->addMessage($message);
    }

    private function notFound(): Response
    {
        return new Response(
            'Association not found.',
            Response::HTTP_NOT_FOUND,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\HostMessages;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use JTranslate\I18n\TranslatableMessage;
use Schoenstatt\Form\ImportFatherForm;
use Schoenstatt\Service\PatresGateway;
use Schoenstatt\Service\PatresLookupFailed;
use SionModel\Messaging\FlashMessages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function array_keys;
use function implode;

/**
 * GET/POST /admin/import-father — copy a Schoenstatt Father's record from Patres into
 * `sch_persons`. Ported 2026-09-08 (batch 17): **the last HTML page laminas served**.
 *
 * ## What the page is
 *
 * One select and a button. The select's options are the Patres person list, fetched over
 * HTTP by `Schoenstatt\FathersValueOptions` when the laminas factory builds
 * `ImportFatherForm` — and fetched *silently*: a failed request yields an empty list, so
 * on a host without a working API key the page renders an empty picker rather than an
 * error (the capsule's key is a dummy, and that is what it shows). The form comes from
 * the laminas container for exactly that reason: the option list is the form's whole
 * content and the factory is where it is assembled.
 *
 * ## Three outcomes on a valid POST, reproduced from `AdminController::importFatherAction()`
 *
 * `PatresGateway::importRemotePerson()` returns `false` only when the Patres record fails
 * the person input filter; an existing import is *refreshed*, reported through the
 * by-reference flag; and a new row is the plain success. Until 2026-08-17 the first was
 * reported as "Person already exists", which it never meant — see the gateway. All three
 * are *now* messages: the page re-renders with the result, it does not redirect.
 *
 * ## One thing this does that the laminas action did not
 *
 * An empty submission — the form's `personId` is `required => false` and filters `''` to
 * null — reached `importRemotePerson(null)` on laminas, which built a request for
 * `/api/persons/` and threw on whatever came back: a 500 for leaving the picker blank.
 * Here it is a form error and a re-render. Everything else the gateway throws (a network
 * failure, a non-200 from Patres) still propagates, because the error handler reporting it
 * is the right outcome for a remote service being down.
 *
 * ## Status codes
 *
 * 200 on every re-render, including an invalid token, as the laminas action answers — the
 * form is the visitor's to correct and nothing branches on the code.
 */
final class ImportFatherController
{
    private const TEMPLATE = 'schoenstatt/import-father.html.twig';

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly HostMessages $messages
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var ImportFatherForm $form */
        $form = $this->laminas->get(ImportFatherForm::class);

        if ($request->isMethod('POST')) {
            /** @var array<string, mixed> $posted */
            $posted = $request->request->all();
            $form->setData($posted);

            if ($form->isValid()) {
                /** @var array<string, mixed> $data */
                $data = $form->getData();
                $this->import($data['personId'] ?? null);
            } else {
                $this->messages->now(FlashMessages::NAMESPACE_ERROR, 'Error in form submission, please review.');
            }
        }

        return new Response($this->twig->render(self::TEMPLATE, [
            'page_title'  => 'Import Schoenstatt Father',
            'form'        => $form,
            'form_action' => $this->urls->path('admin/import-father'),
        ]));
    }

    /** The three outcomes, plus the blank picker that used to be a 500. */
    private function import(mixed $personId): void
    {
        if (null === $personId || '' === $personId) {
            $this->messages->now(FlashMessages::NAMESPACE_ERROR, 'Please choose a person to import.');

            return;
        }

        /** @var PatresGateway $gateway */
        $gateway = $this->laminas->get(PatresGateway::class);

        $overwroteExisting = false;

        try {
            $result = $gateway->importRemotePerson($personId, [], $overwroteExisting);
        } catch (PatresLookupFailed $e) {
            //The same gap the checkout page had: the branch below answers a record patres
            //*refused*, and said nothing about a record patres could not be **asked**
            //about. The picker is built from a different patres endpoint and empties
            //itself silently when that one is unreachable, so this page can offer a person
            //it then cannot fetch — which is exactly what happened at a lending desk on
            //2026-09-10, on the checkout side, as a 500.
            $this->messages->now(FlashMessages::NAMESPACE_ERROR, new TranslatableMessage(
                'Patres could not be reached for this person, so nothing was imported. '
                . 'This is a problem at their end — try again in a few minutes.'
            ));

            return;
        }

        if (false === $result) {
            $fields = array_keys($gateway->getLastRemotePersonMessages());
            $this->messages->now(FlashMessages::NAMESPACE_ERROR, new TranslatableMessage(
                'The record for this person in Patres could not be imported because it '
                . 'is not valid. Fields at fault: %s. Ask Patres to correct it, then try again.',
                [[] === $fields ? '(none reported)' : implode(', ', $fields)]
            ));

            return;
        }

        if ($overwroteExisting) {
            $this->messages->now(
                FlashMessages::NAMESPACE_SUCCESS,
                'This person had already been imported. Their record has been '
                . 'refreshed from Patres, replacing what was stored here.'
            );

            return;
        }

        $this->messages->now(FlashMessages::NAMESPACE_SUCCESS, 'Person successfully imported.');
    }
}

<?php

declare(strict_types=1);

namespace App\Laminas;

use BjyAuthorize\View\Helper\IsAllowed;
use JTranslate\View\Helper\Flag;
use JUser\View\Helper\ZfcUserDisplayName;
use Laminas\Mvc\Plugin\FlashMessenger\View\Helper\FlashMessenger;
use Laminas\View\HelperPluginManager;
use SionModel\View\Helper\Email;
use SionModel\View\Helper\FormatUrlObject;
use SionModel\View\Helper\Telephone;

/**
 * The laminas view helpers a Symfony-served route may reuse — and, by being the
 * only way to reach one, the ones it may not.
 *
 * The migration exists to remove laminas-view, so reusing any of it needs a
 * reason. The reason is the one App\Laminas\ServiceBridge already gives for reusing
 * laminas services: rewriting every dependency at the moment its route moves turns
 * one migration into many. Each helper exposed below was measured to resolve and
 * run with **no MvcEvent** — the four formatters reach the renderer for nothing but
 * its escapers, and IsAllowed and ZfcUserDisplayName reach past it entirely, to the
 * ACL and the authentication service. The libphonenumber formatting behind
 * `telephone` and the translated country-name table behind `flag` are real logic
 * that would otherwise be duplicated and drift.
 *
 * The list is short because most of laminas-view cannot work here. Anything that
 * assembles a URL — `formatEntity`, `formatAssociation`, `formatPerson`,
 * `editPencil`, the whole `navigation` family, `localeUrl`, `routeName`,
 * `libraryInfo` — reaches `$this->view->url()`, whose helper wants a RouteMatch off
 * the MvcEvent, and fails with "Call to a member function getRouteMatch() on null"
 * from deep inside laminas-view, on a page that is otherwise rendering fine. Those
 * are reimplemented on this side instead, against App\Laminas\RouteUrl. Exposing
 * this as a typed method per helper rather than a `get(string $name)` is what makes
 * that a compile-time fact rather than a rule someone has to remember.
 *
 * Priming note: HelperPluginManager injects a renderer into its helpers only once a
 * renderer has claimed it, which PhpRenderer does in its own constructor. So
 * `ViewRenderer` must be pulled out of the container before any helper is used, or
 * `$this->view` is null and even `flag` fatals on escapeHtmlAttr(). That single
 * `get()` is the whole of laminas-view instantiated here: no MvcEvent, no view
 * model, no layout, no rendering.
 */
final class ViewHelpers
{
    private ?HelperPluginManager $helpers = null;

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    public function flag(): Flag
    {
        /** @var Flag $helper */
        $helper = $this->helpers()->get('flag');

        return $helper;
    }

    public function email(): Email
    {
        /** @var Email $helper */
        $helper = $this->helpers()->get('email');

        return $helper;
    }

    public function telephone(): Telephone
    {
        /** @var Telephone $helper */
        $helper = $this->helpers()->get('telephone');

        return $helper;
    }

    public function formatUrlObject(): FormatUrlObject
    {
        /** @var FormatUrlObject $helper */
        $helper = $this->helpers()->get('formatUrlObject');

        return $helper;
    }

    /**
     * Session-backed, which is the point: a laminas action that sets a flash and
     * redirects to a ported page would otherwise lose it silently.
     */
    public function flashMessenger(): FlashMessenger
    {
        /** @var FlashMessenger $helper */
        $helper = $this->helpers()->get('flashMessenger');

        return $helper;
    }

    public function isAllowed(): IsAllowed
    {
        /** @var IsAllowed $helper */
        $helper = $this->helpers()->get('isAllowed');

        return $helper;
    }

    public function displayName(): ZfcUserDisplayName
    {
        /** @var ZfcUserDisplayName $helper */
        $helper = $this->helpers()->get('zfcUserDisplayName');

        return $helper;
    }

    private function helpers(): HelperPluginManager
    {
        if (null !== $this->helpers) {
            return $this->helpers;
        }

        //ordering, not decoration: PhpRenderer::__construct() calls
        //HelperPluginManager::setRenderer($this), and without that every helper's
        //$this->view is null
        $this->laminas->get('ViewRenderer');

        /** @var HelperPluginManager $helpers */
        $helpers = $this->laminas->get('ViewHelperManager');

        return $this->helpers = $helpers;
    }
}

<?php

namespace Application\View\Helper;

use Laminas\Http\Request;
use Laminas\Uri\Http as HttpUri;
use Laminas\View\Helper\AbstractHelper;

/**
 * Exposes the current request's URI to templates.
 *
 * This replaces the `getHelperPluginManager()->getServiceLocator()->get('request')`
 * idiom that layout.phtml used to reach the request with: that shim is deprecated
 * in laminas-servicemanager 3 and gone in laminas-view 3.
 *
 * Deliberately *not* `Laminas\View\Helper\ServerUrl`: that helper re-detects the
 * scheme from `$_SERVER` with different rules (it wants `HTTPS === 'on'` exactly
 * and ignores `X-Forwarded-Proto` unless `useProxy` is enabled), so behind a
 * TLS-terminating proxy it can report `http` where the request object reports
 * `https`. The canonical/alternate link tags in layout.phtml depend on that
 * scheme, so this helper keeps the request object as the single source of truth.
 */
class RequestUri extends AbstractHelper
{
    public function __construct(private readonly Request $request)
    {
    }

    public function __invoke(): HttpUri
    {
        return $this->request->getUri();
    }
}

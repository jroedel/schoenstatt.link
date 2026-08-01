<?php

namespace Application\Listener;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\MvcEvent;

/**
 * Minimal CORS support for the public read-only APIs, replacing the abandoned
 * zfr/zfr-cors package (both the upstream repo and the fork we pinned were
 * deleted from GitHub).
 *
 * Routes opt in with a `'cors' => true` route default and get
 * `Access-Control-Allow-Origin: *` on their responses. Preflight OPTIONS
 * requests to any /api/ path are answered before routing, because several API
 * routes are Method-typed and would 404 an OPTIONS request; the preflight
 * grant is generous, but the actual response only carries the allow-origin
 * header on routes that opted in.
 */
class CorsListener extends AbstractListenerAggregate
{
    const MAX_AGE = '120';

    public function attach(EventManagerInterface $events, $priority = 1)
    {
        //before routing, so OPTIONS preflights can't 404 on Method-typed routes
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoutePreflight'], 100);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_FINISH, [$this, 'onFinish'], 100);
    }

    /**
     * Answer cross-origin preflight requests for API paths, short-circuiting the MVC cycle
     *
     * @return Response|null
     */
    public function onRoutePreflight(MvcEvent $e)
    {
        $request = $e->getRequest();
        if (! $request instanceof Request
            || Request::METHOD_OPTIONS !== $request->getMethod()
            || ! $request->getHeader('Origin')
            || false === strpos($request->getUri()->getPath(), '/api/')
        ) {
            return null;
        }
        /** @var Response $response */
        $response = $e->getResponse();
        $response->setStatusCode(Response::STATUS_CODE_204);
        $response->getHeaders()
            ->addHeaderLine('Access-Control-Allow-Origin', '*')
            ->addHeaderLine('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->addHeaderLine('Access-Control-Allow-Headers', 'Authorization, Content-Type, Origin')
            ->addHeaderLine('Access-Control-Max-Age', self::MAX_AGE);
        return $response;
    }

    /**
     * Add the allow-origin header to responses of routes that opted in
     */
    public function onFinish(MvcEvent $e)
    {
        $routeMatch = $e->getRouteMatch();
        if (! $routeMatch || ! $routeMatch->getParam('cors', false)) {
            return;
        }
        $request = $e->getRequest();
        if (! $request instanceof Request || ! $request->getHeader('Origin')) {
            return;
        }
        $response = $e->getResponse();
        if ($response instanceof Response) {
            $response->getHeaders()
                ->addHeaderLine('Access-Control-Allow-Origin', '*')
                ->addHeaderLine('Vary', 'Origin');
        }
    }
}

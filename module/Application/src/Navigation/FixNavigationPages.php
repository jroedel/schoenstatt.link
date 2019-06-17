<?php
/**
 * BjyAuthorize Module (https://github.com/bjyoungblood/BjyAuthorize)
 *
 * @link https://github.com/bjyoungblood/BjyAuthorize for the canonical source repository
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Navigation;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\MvcEvent;
use Zend\Navigation\Page\Mvc;
use Zend\Navigation\Navigation;

class FixNavigationPages implements ListenerAggregateInterface
{
    /**
     * @var callable[] An array with callback functions or methods.
     */
    protected $listeners = [];

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, array($this, 'onRoute'), -5000);
    }

    /**
     * {@inheritDoc}
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    /**
     * Injects the router and route match into each Mvc page in the navigation
     *
     * @param MvcEvent $event
     *
     * @return void
     */
    public function onRoute(MvcEvent $event)
    {
        $router = $event->getRouter();
        $matches = $event->getRouteMatch();
        $navigation = $event->getApplication()->getServiceManager()->get(Navigation::class);
        self::injectMvcPages($navigation->getPages(), $router, $matches);
    }
    
    protected static function injectMvcPages($pages, $router, $matches)
    {
        foreach ($pages as $page) {
            if ($page instanceof Mvc) {
                $page->setRouter($router);
                $page->setRouteMatch($matches);
                self::injectMvcPages($page->getPages(), $router, $matches);
            }
        }
    }
}

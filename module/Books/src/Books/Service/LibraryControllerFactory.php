<?php

namespace Books\Service;

use Books\Controller\LibraryController;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class LibraryControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $services)
    {
        $serviceLocator = $services->getServiceLocator();
        //$eBookTable           = $serviceLocator->get('Books\Model\EBookTable');
        //$bookTable            = $serviceLocator->get('Books\Model\BookTable');
        //$transport      = $serviceLocator->get('PhlyContactMailTransport');

        $controller = new LibraryController();
        //$controller->setBookTable($bookTable);
        //$controller->setEBookTable($eBookTable);
        //$controller->setContactForm($form);
        //$controller->setMessage($message);
        //$controller->setMailTransport($transport);

        return $controller;
    }
}
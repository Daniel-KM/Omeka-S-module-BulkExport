<?php
namespace Import\Service\Controller;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $class = $requestedName.'Controller';
        $controller = new $class($serviceLocator);
        return $controller;
    }
}

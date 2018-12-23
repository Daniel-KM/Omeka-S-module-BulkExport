<?php
namespace Import\Service\Plugin;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PluginManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $pluginManager = new $requestedName($serviceLocator);
        return $pluginManager;
    }
}

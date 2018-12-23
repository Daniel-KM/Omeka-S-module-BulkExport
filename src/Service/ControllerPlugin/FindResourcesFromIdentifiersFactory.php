<?php
namespace BulkImport\Service\ControllerPlugin;

use BulkImport\Mvc\Controller\Plugin\FindResourcesFromIdentifiers;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class FindResourcesFromIdentifiersFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        return new FindResourcesFromIdentifiers(
            $services->get('Omeka\Connection'),
            $services->get('ControllerPluginManager')->get('api')
        );
    }
}

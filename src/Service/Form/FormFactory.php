<?php
namespace BulkExport\Service\Form;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class FormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new $requestedName(null, $options);
        return $form
            ->setServiceLocator($services);
    }
}

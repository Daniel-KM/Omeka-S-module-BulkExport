<?php
namespace BulkImport\Service\Form;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class FormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $form = new $requestedName(null, $options);
        $form->setServiceLocator($serviceLocator);
        return $form;
    }
}

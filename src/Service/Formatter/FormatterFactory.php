<?php
namespace BulkExport\Service\Formatter;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class FormatterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $formatter = new $requestedName;
        return $formatter
            ->setServiceLocator($services);
    }
}

<?php
namespace BulkExport\Service\ViewHelper;

use BulkExport\View\Helper\ListFormatters;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ListFormattersFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ListFormatters(
            $services->get('BulkExport\Formatter\Manager'),
            $services->get('Config')['formatters']['aliases']
        );
    }
}

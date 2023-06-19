<?php declare(strict_types=1);

namespace BulkExport\Service\ViewHelper;

use BulkExport\View\Helper\BulkExporters;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class BulkExportersFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new BulkExporters(
            $services->get(\BulkExport\Formatter\Manager::class),
            $services->get('Config')['formatters']['aliases']
        );
    }
}

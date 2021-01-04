<?php declare(strict_types=1);

namespace BulkExport\Service\ControllerPlugin;

use BulkExport\Mvc\Controller\Plugin\ExportFormatter;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ExportFormatterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ExportFormatter(
            $services->get(\BulkExport\Formatter\Manager::class)
        );
    }
}

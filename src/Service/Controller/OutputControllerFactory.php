<?php declare(strict_types=1);
namespace BulkExport\Service\Controller;

use BulkExport\Controller\OutputController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class OutputControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new OutputController(
            $services->get(\BulkExport\Formatter\Manager::class)
        );
    }
}

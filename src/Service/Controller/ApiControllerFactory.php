<?php declare(strict_types=1);

namespace BulkExport\Service\Controller;

use BulkExport\Controller\ApiController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ApiControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ApiController(
            $services->get('Omeka\Paginator'),
            $services->get('Omeka\ApiManager')
        );
    }
}

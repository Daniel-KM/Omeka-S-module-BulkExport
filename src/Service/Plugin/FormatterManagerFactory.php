<?php
namespace BulkExport\Service\Plugin;

use BulkExport\Formatter\Manager;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\Service\Exception\ConfigException;

class FormatterManagerFactory implements FactoryInterface
{
    /**
     * Create the output format manager service.
     *
     * @return Manager
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        if (empty($config['formatters'])) {
            throw new ConfigException('Missing output format configuration'); // @translate
        }
        return new Manager($services, $config['formatters']);
    }
}

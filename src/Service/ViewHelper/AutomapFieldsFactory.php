<?php
namespace BulkExport\Service\ViewHelper;

use BulkExport\View\Helper\AutomapFields;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class AutomapFieldsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $filepath = '/data/mappings/fields_to_metadata.php';
        $map = require dirname(dirname(dirname(__DIR__))) . $filepath;
        $viewHelpers = $services->get('ViewHelperManager');
        return new AutomapFields(
            $map,
            $viewHelpers->get('api'),
            $viewHelpers->get('translate')
        );
    }
}

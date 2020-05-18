<?php
namespace BulkExport\Service\Form;

use BulkExport\Form\SiteSettingsFieldset;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class SiteSettingsFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $formatters = $services->get('Config')['formatters']['aliases'];
        $formatterManager = $services->get('BulkExport\Formatter\Manager');
        foreach (array_keys($formatters) as $formatter) {
            $formatters[$formatter] = $formatterManager->get($formatter)->getLabel();
        }

        $fieldset = new SiteSettingsFieldset(null, $options);
        return $fieldset
            ->setFormatters($formatters);
    }
}

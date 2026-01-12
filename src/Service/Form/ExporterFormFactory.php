<?php declare(strict_types=1);

namespace BulkExport\Service\Form;

use BulkExport\Form\ExporterForm;
use BulkExport\Formatter\Manager as FormatterManager;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ExporterFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $formatterOptions = [];
        $formatterManager = $services->get(FormatterManager::class);
        $formatters = $formatterManager->getPlugins();
        foreach ($formatters as $key => $formatter) {
            $formatterOptions[$key] = $formatter->getLabel();
        }

        $form = new ExporterForm(null, $options ?? []);
        return $form
            ->setFormatterOptions($formatterOptions);
    }
}

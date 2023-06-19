<?php declare(strict_types=1);

namespace BulkExport\Service\Form;

use BulkExport\Form\ExporterForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ExporterFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $writerOptions = [];
        $writerManager = $services->get(\BulkExport\Writer\Manager::class);
        $writers = $writerManager->getPlugins();
        foreach ($writers as $key => $writer) {
            $writerOptions[$key] = $writer->getLabel();
        }

        $form = new ExporterForm(null, $options ?? []);
        return $form
            ->setWriterOptions($writerOptions);
    }
}

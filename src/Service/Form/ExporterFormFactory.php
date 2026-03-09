<?php declare(strict_types=1);

namespace BulkExport\Service\Form;

use BulkExport\Form\ExporterForm;
use BulkExport\Formatter\Manager as FormatterManager;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ExporterFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $formatterManager = $services->get(FormatterManager::class);
        $config = $services->get('Config')['formatters'] ?? [];
        $aliases = $config['aliases'] ?? [];

        // Use aliases as keys so the saved value matches
        // formatter_forms and formatter lookup.
        $formatterOptions = [];
        foreach ($aliases as $alias => $class) {
            $formatter = $formatterManager->get($alias);
            if ($formatter) {
                $formatterOptions[$alias] = $formatter->getLabel();
            }
        }

        $form = new ExporterForm(null, $options ?? []);
        return $form
            ->setFormatterOptions($formatterOptions);
    }
}

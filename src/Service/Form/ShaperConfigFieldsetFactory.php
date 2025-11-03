<?php declare(strict_types=1);

namespace BulkExport\Service\Form;

use BulkExport\Form\ShaperConfigFieldset;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ShaperConfigFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new ShaperConfigFieldset(null, $options ?? []);
        return $form
            ->setApiManager($services->get('Omeka\ApiManager'))
        ;
    }
}

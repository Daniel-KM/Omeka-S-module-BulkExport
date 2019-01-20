<?php
namespace BulkExport\Processor;

use ArrayObject;
use BulkExport\Form\Processor\ItemSetProcessorConfigForm;
use BulkExport\Form\Processor\ItemSetProcessorParamsForm;

class ItemSetProcessor extends ResourceProcessor
{
    protected $resourceType = 'item_sets';

    protected $resourceLabel = 'Item sets'; // @translate

    protected $configFormClass = ItemSetProcessorConfigForm::class;

    protected $paramsFormClass = ItemSetProcessorParamsForm::class;

    protected function handleFormSpecific(ArrayObject $args, array $values)
    {
        $this->handleFormItemSet($args, $values);
    }

    protected function baseSpecific(ArrayObject $resource)
    {
        $this->baseItemSet($resource);
    }

    protected function fillSpecific(ArrayObject $resource, $target, array $values)
    {
        switch ($target['target']) {
            case $this->fillItemSet($resource, $target, $values):
                return true;
            default:
                return false;
        }
    }

    protected function checkEntity(ArrayObject $resource)
    {
        return $this->checkItemSet($resource);
    }
}

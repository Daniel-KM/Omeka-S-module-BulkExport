<?php
namespace BulkImport\Processor;

use ArrayObject;
use BulkImport\Form\ItemProcessorConfigForm;
use BulkImport\Form\ItemProcessorParamsForm;

class ItemProcessor extends ResourceProcessor
{
    protected $resourceType = 'items';

    protected $resourceLabel = 'Items'; // @translate

    protected $configFormClass = ItemProcessorConfigForm::class;

    protected $paramsFormClass = ItemProcessorParamsForm::class;

    protected function handleFormSpecific(ArrayObject $args, array $values)
    {
        $this->handleFormItem($args, $values);
    }

    protected function baseSpecific(ArrayObject $resource)
    {
        $this->baseItem($resource);
    }

    protected function fillSpecific(ArrayObject $resource, $target, array $values)
    {
        switch ($target['target']) {
            case $this->fillItem($resource, $target, $values):
                return true;
            default:
                return false;
        }
    }

    protected function checkResource(ArrayObject $resource)
    {
        return $this->checkItem($resource);
    }
}

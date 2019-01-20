<?php
namespace BulkExport\Processor;

use ArrayObject;
use BulkExport\Form\Processor\MediaProcessorConfigForm;
use BulkExport\Form\Processor\MediaProcessorParamsForm;

class MediaProcessor extends ResourceProcessor
{
    protected $resourceType = 'media';

    protected $resourceLabel = 'Media'; // @translate

    protected $configFormClass = MediaProcessorConfigForm::class;

    protected $paramsFormClass = MediaProcessorParamsForm::class;

    protected function handleFormSpecific(ArrayObject $args, array $values)
    {
        $this->handleFormMedia($args, $values);
    }

    protected function baseSpecific(ArrayObject $resource)
    {
        $this->baseMedia($resource);
    }

    protected function fillSpecific(ArrayObject $resource, $target, array $values)
    {
        switch ($target['target']) {
            case $this->fillMedia($resource, $target, $values):
                return true;
            default:
                return false;
        }
    }

    protected function checkEntity(ArrayObject $resource)
    {
        return $this->checkMedia($resource);
    }
}

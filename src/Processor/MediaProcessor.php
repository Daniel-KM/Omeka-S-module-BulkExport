<?php
namespace BulkImport\Processor;

use ArrayObject;
use BulkImport\Form\MediaProcessorConfigForm;
use BulkImport\Form\MediaProcessorParamsForm;
use Log\Stdlib\PsrMessage;
use Zend\Log\Logger;

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
        switch ($target) {
            case $this->fillMedia($resource, $target, $values):
                return true;
            default:
                return false;
        }
    }

    protected function checkResource(ArrayObject $resource)
    {
        if (empty($resource['o:item']['o:id'])) {
            $this->logger->log(
                Logger::ERR,
                new PsrMessage('Skipped media index {index}: no item is set', // @translate
                    ['index' => $this->indexRow]
                )
            );
            return false;
        }
        return true;
    }
}

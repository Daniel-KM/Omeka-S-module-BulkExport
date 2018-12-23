<?php
namespace BulkImport\Processor;

use ArrayObject;
use BulkImport\Form\ResourceProcessorConfigForm;
use BulkImport\Form\ResourceProcessorParamsForm;
use BulkImport\Log\Logger;

class ResourceProcessor extends AbstractResourceProcessor
{
    protected $resourceType = 'resources';

    protected $resourceLabel = 'Mixed resources'; // @translate

    protected $configFormClass = ResourceProcessorConfigForm::class;

    protected $paramsFormClass = ResourceProcessorParamsForm::class;

    protected function handleFormSpecific(ArrayObject $args, array $values)
    {
        if (isset($values['resource_type'])) {
            $args['resource_type'] = $values['resource_type'];
        }
        $this->handleFormItem($args, $values);
        $this->handleFormItemSet($args, $values);
        $this->handleFormMedia($args, $values);
    }

    protected function handleFormItem(ArrayObject $args, array $values)
    {
        if (isset($values['o:item_set'])) {
            $args['o:item_set'] = $values['o:item_set'];
        }
    }

    protected function handleFormItemSet(ArrayObject $args, array $values)
    {
        if (isset($values['o:is_open'])) {
            $args['o:is_open'] = $values['o:is_open'] !== 'false';
        }
    }

    protected function handleFormMedia(ArrayObject $args, array $values)
    {
        if (isset($values['o:item'])) {
            $args['o:item'] = $values['o:item'];
        }
    }

    protected function baseSpecific(ArrayObject $resource)
    {
        $resource['resource_type'] = $this->getParam('resource_type');
        $this->baseItem($resource);
        $this->baseItemSet($resource);
        $this->baseMedia($resource);
    }

    protected function baseItem(ArrayObject $resource)
    {
        $itemSetIds = $this->getParam('o:item_set', []);
        foreach ($itemSetIds as $itemSetId) {
            $resource['o:item_set'][] = ['o:id' => $itemSetId];
        }
        $resource['o:media'] = [];
    }

    protected function baseItemSet(ArrayObject $resource)
    {
        $isOpen = $this->getParam('o:is_open', null);
        $resource['o:is_open'] = $isOpen;
    }

    protected function baseMedia(ArrayObject $resource)
    {
        $itemId = $this->getParam('o:item', null);
        $resource['o:item'] = ['o:id' => $itemId];
    }

    protected function fillSpecific(ArrayObject $resource, $target, array $values)
    {
        switch ($target) {
            case 'resource_type':
                $value = array_pop($values);
                if (in_array($value, ['items', 'item_sets', 'media'])) {
                    $resource['resource_type'] = $value;
                }
                return true;
            case $this->fillItem($resource, $target, $values):
                return true;
            case $this->fillItemSet($resource, $target, $values):
                return true;
            case $this->fillMedia($resource, $target, $values):
                return true;
            default:
                return false;
        }
    }

    protected function fillItem(ArrayObject $resource, $target, array $values)
    {
        switch ($target) {
            case 'o:item_set':
                foreach ($values as $value) {
                    $resource['o:item_set'][] = ['o:id' => $value];
                }
                return true;
            case 'url':
                foreach ($values as $value) {
                    $media = [];
                    $media['o:is_public'] = true;
                    $media['o:ingester'] = 'url';
                    $media['ingest_url'] = $value;
                    $resource['o:media'][] = $media;
                }
                return true;
            case 'sideload':
                foreach ($values as $value) {
                    $media = [];
                    $media['o:is_public'] = true;
                    $media['o:ingester'] = 'sideload';
                    $media['ingest_filename'] = $value;
                    $resource['o:media'][] = $media;
                }
                return true;
        }
        return false;
    }

    protected function fillItemSet(ArrayObject $resource, $target, array $values)
    {
        switch ($target) {
            case 'o:is_open':
                $value = array_pop($values);
                $resource['o:is_open'] = in_array(strtolower($value), ['false', 'no', 'off', 'closed'])
                    ? false
                    : (bool) $value;
                return true;
        }
        return false;
    }

    protected function fillMedia(ArrayObject $resource, $target, array $values)
    {
        switch ($target) {
            case 'o:item':
                $value = array_pop($values);
                $resource['o:item'] = ['o:id' => $value];
                return true;
            case 'url':
                $value = array_pop($values);
                $resource['o:ingester'] = 'url';
                $resource['ingest_url'] = $value;
                return true;
            case 'sideload':
                $value = array_pop($values);
                $resource['o:ingester'] = 'sideload';
                $resource['ingest_filename'] = $value;
                return true;
        }
    }

    protected function checkResource(ArrayObject $resource)
    {
        if (empty($resource['resource_type'])) {
            $this->logger->log(
                Logger::ERR,
                sprintf('Skipped resource index %s: no resource type set.',  // @translate
                    $this->indexRow
                )
            );
            return false;
        }
        if (!in_array($resource['resource_type'], ['items', 'item_sets', 'media'])) {
            $this->logger->log(
                Logger::ERR,
                sprintf(
                    'Skipped resource index %s: resource type "%s" not managed.', // @translate
                    $this->indexRow,
                    $resource['resource_type']
                )
            );
            return false;
        }
        return true;
    }

    protected function createEntities(array $data)
    {
        $resourceType = $this->getResourceType();
        if ($resourceType !== 'resources') {
            $this->createResources($resourceType, $data);
            return;
        }

        if (empty($data)) {
            return;
        }

        // Create all resources, but keep order, so create resources by type.
        $datas = [];
        $previousResourceType = $data[0]['resource_type'];
        foreach ($data as $dataResource) {
            if ($previousResourceType !== $dataResource['resource_type']) {
                $this->createResources($previousResourceType, $datas);
                $previousResourceType = $dataResource['resource_type'];
                $datas = [];
            }
            $datas[] = $dataResource;
        }
        if ($datas) {
            $this->createResources($previousResourceType, $datas);
        }
    }
}

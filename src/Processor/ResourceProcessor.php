<?php
namespace BulkImport\Processor;

use ArrayObject;
use BulkImport\Form\Processor\ResourceProcessorConfigForm;
use BulkImport\Form\Processor\ResourceProcessorParamsForm;

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
            $ids = $this->findResourcesFromIdentifiers($values['o:item_set'], 'o:id', 'item_sets');
            foreach ($ids as $id) {
                $args['o:item_set'][] = ['o:id' => $id];
            }
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
            $id = $this->findResourceFromIdentifier($values['o:item'], 'o:id', 'items');
            if ($id) {
                $args['o:item'] = ['o:id' => $id];
            }
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
        $resource['o:item_set'] = $this->getParam('o:item_set', []);
        $resource['o:media'] = [];
    }

    protected function baseItemSet(ArrayObject $resource)
    {
        $isOpen = $this->getParam('o:is_open', null);
        $resource['o:is_open'] = $isOpen;
    }

    protected function baseMedia(ArrayObject $resource)
    {
        $resource['o:item'] = $this->getParam('o:item') ?: ['o:id' => null];
    }

    protected function fillSpecific(ArrayObject $resource, $target, array $values)
    {
        static $resourceTypes;

        if (is_null($resourceTypes)) {
            $translate = $this->getServiceLocator()->get('ViewHelperManager')->get('translate');
            $resourceTypes = [
                'item' => 'items',
                'itemset' => 'item_sets',
                'media' => 'media',
                'items' => 'items',
                'itemsets' => 'item_sets',
                'medias' => 'media',
                'collection' => 'item_sets',
                'collections' => 'item_sets',
                'file' => 'media',
                'files' => 'media',
                $translate('item') => 'items',
                $translate('itemset') => 'item_sets',
                $translate('media') => 'media',
                $translate('items') => 'items',
                $translate('itemsets') => 'item_sets',
                $translate('medias') => 'media',
                $translate('collection') => 'item_sets',
                $translate('collections') => 'item_sets',
                $translate('file') => 'media',
                $translate('files') => 'media',
            ];
        }

        // When the resource type is known, don't fill other resources. But if
        // is not known yet, fill the item first. It fixes the issues with the
        // target that are the same for media of item and media (that is a
        // special case where two or more resources are created from one
        // entry).
        $resourceType = empty($resource['resource_type']) ? true : $resource['resource_type'];

        switch ($target['target']) {
            case 'resource_type':
                $value = array_pop($values);
                $resourceType = preg_replace('~[^a-z]~', '', strtolower($value));
                if (isset($resourceTypes[$resourceType])) {
                    $resource['resource_type'] = $resourceTypes[$resourceType];
                }
                return true;
            case $resourceType == 'items' && $this->fillItem($resource, $target, $values):
                return true;
            case $resourceType == 'item_sets' && $this->fillItemSet($resource, $target, $values):
                return true;
            case $resourceType == 'media' && $this->fillMedia($resource, $target, $values):
                return true;
            default:
                return false;
        }
    }

    protected function fillItem(ArrayObject $resource, $target, array $values)
    {
        switch ($target['target']) {
            case 'o:item_set':
                $identifierName = isset($target['target_data']) ? $target['target_data'] : $this->identifierNames;
                $ids = $this->findResourcesFromIdentifiers($values, $identifierName, 'item_sets');
                foreach ($ids as $id) {
                    $resource['o:item_set'][] = [
                        'o:id' => $id,
                        'checked_id' => true,
                    ];
                }
                return true;
            case 'url':
                foreach ($values as $value) {
                    $media = [];
                    $media['o:ingester'] = 'url';
                    $media['ingest_url'] = $value;
                    $media['o:source'] = $value;
                    $this->appendRelated($resource, $media);
                }
                return true;
            case 'file':
                foreach ($values as $value) {
                    $media = [];
                    if ($this->isUrl($value)) {
                        $media['o:ingester'] = 'url';
                        $media['ingest_url'] = $value;
                    } else {
                        $media['o:ingester'] = 'sideload';
                        $media['ingest_filename'] = $value;
                    }
                    $media['o:source'] = $value;
                    $this->appendRelated($resource, $media);
                }
                return true;
            case 'html':
                foreach ($values as $value) {
                    $media = [];
                    $media['o:ingester'] = 'html';
                    $media['html'] = $value;
                    $this->appendRelated($resource, $media);
                }
                return true;
            case 'o:media':
                if (isset($target["target_data"])) {
                    if (isset($target['target_data_value'])) {
                        foreach ($values as $value) {
                            $resourceProperty = $target['target_data_value'];
                            $resourceProperty['@value'] = $value;
                            $media = [];
                            $media[$target['target_data']][] = $resourceProperty;
                            $this->appendRelated($resource, $media, 'o:media', 'dcterms:title');
                        }
                        return true;
                    } else {
                        $value = array_pop($values);
                        $media = [];
                        $media[$target['target_data']] = $value;
                        $this->appendRelated($resource, $media, 'o:media', $target['target_data']);
                        return true;
                    }
                }
                break;
        }
        return false;
    }

    protected function fillItemSet(ArrayObject $resource, $target, array $values)
    {
        switch ($target['target']) {
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
        switch ($target['target']) {
            case 'o:item':
                $value = array_pop($values);
                $identifierName = isset($target["target_data"]) ? $target["target_data"] : $this->identifierNames;
                $id = $this->findResourceFromIdentifier($value, $identifierName, 'items');
                $resource['o:item'] = [
                    'o:id' => $id,
                    'checked_id' => true,
                ];
                return true;
            case 'url':
                $value = array_pop($values);
                $resource['o:ingester'] = 'url';
                $resource['ingest_url'] = $value;
                $resource['o:source'] = $value;
                return true;
            case 'file':
                $value = array_pop($values);
                if ($this->isUrl($value)) {
                    $resource['o:ingester'] = 'url';
                    $resource['ingest_url'] = $value;
                } else {
                    $resource['o:ingester'] = 'sideload';
                    $resource['ingest_filename'] = $value;
                }
                $resource['o:source'] = $value;
                return true;
            case 'html':
                $value = array_pop($values);
                $resource['o:ingester'] = 'html';
                $resource['html'] = $value;
                return true;
        }
    }

    /**
     * Append an attached resource to a resource, checking if it exists already.
     *
     * It allows to fill multiple media of an items, or any other related
     * resource, in multiple steps, for example the url, then the title.
     * Note: it requires that all elements to be set, in the same order, when
     * they are multiple.
     *
     * @param ArrayObject $resource
     * @param array $related
     * @param string $term
     * @param string $check
     */
    protected function appendRelated(
        ArrayObject $resource,
        array $related,
        $metadata = 'o:media',
        $check = 'o:ingester'
    ) {
        if (!empty($resource[$metadata])) {
            foreach ($resource[$metadata] as $key => $values) {
                if (!array_key_exists($check, $values)) {
                    // Use the last data set.
                    $resource[$metadata][$key] = $related + $resource[$metadata][$key];
                    return;
                }
            }
        }
        $resource[$metadata][] = $related;
    }

    protected function checkEntity(ArrayObject $resource)
    {
        if (empty($resource['resource_type'])) {
            $this->logger->err(
                'Skipped resource index #{index}: no resource type set',  // @translate
                ['index' => $this->indexResource]
            );
            return false;
        }

        if (!in_array($resource['resource_type'], ['items', 'item_sets', 'media'])) {
            $this->logger->err(
                'Skipped resource index #{index}: resource type "{resource_type}" not managed', // @translate
                [
                    'index' => $this->indexResource,
                    'resource_type' => $resource['resource_type'],
                ]
            );
            return false;
        }

        switch ($resource['resource_type']) {
            case 'items':
                if (!$this->checkItem($resource)) {
                    return false;
                }
                break;
            case 'item_sets':
                if (!$this->checkItemSet($resource)) {
                    return false;
                }
                break;
            case 'media':
                if (!$this->checkMedia($resource)) {
                    return false;
                }
                break;
        }

        return parent::checkEntity($resource);
    }

    protected function checkItem(ArrayObject $resource)
    {
        // Media of an item are public by default.
        foreach ($resource['o:media'] as &$media) {
            if (!array_key_exists('o:is_public', $media) || is_null($media['o:is_public'])) {
                $media['o:is_public'] = true;
            }
        }

        unset($resource['o:item']);
        return true;
    }

    protected function checkItemSet(ArrayObject $resource)
    {
        unset($resource['o:item']);
        unset($resource['o:item_set']);
        unset($resource['o:media']);
        return true;
    }

    protected function checkMedia(ArrayObject $resource)
    {
        // When a resource type is unknown before the end of the filling of an
        // entry, fillItem() is called for item first, and there are some common
        // fields with media (the file related ones), so they should be moved
        // here.
        if (!empty($resource['o:media'])) {
            foreach ($resource['o:media'] as $media) {
                $resource += $media;
            }
        }

        if (empty($resource['o:item']['o:id'])) {
            $this->logger->err(
                'Skipped media index #{index}: no item is set', // @translate
                ['index' => $this->indexResource]
            );
            return false;
        }

        unset($resource['o:item_set']);
        unset($resource['o:media']);
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

        // Process all resources, but keep order, so process them by type.
        // Useless when the batch is 1.
        // TODO Create an option for full order by id for items, then media.
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

<?php
namespace BulkExport\Formatter;

use BulkExport\Traits\ListTermsTrait;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class Csv extends AbstractFormatter
{
    use ListTermsTrait;

    protected $label = 'csv';
    protected $extension = 'csv';
    protected $responseHeaders = [
        'Content-type' => 'text/csv',
    ];
    protected $defaultOptions = [
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '\\',
        'separator' => ' | ',
    ];

    protected function process()
    {
        $file = $this->isOutput ? $this->output : 'php://temp';
        $handle = fopen($file, 'w+');
        if (!$handle) {
            $this->hasError = true;
            return false;
        }

        if ($this->isQuery) {
            $this->resourceIds = $this->api->search($this->resourceType, $this->query, ['returnScalar' => 'id'])->getContent();
            $this->isId = true;
        }

        // TODO Add a check for the separator in the values.

        // First loop to get all headers.
        $rowHeaders = $this->prepareHeaders();

        fputcsv($handle, array_keys($rowHeaders), $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);

        $outputRowForResource = function (AbstractResourceEntityRepresentation $resource) use ($rowHeaders, $handle) {
            $row = $this->prepareRow($resource, $rowHeaders);
            // Do a diff to avoid issue if a resource was update during process.
            // Order the row according to headers, keeping empty values.
            $row = array_values(array_replace($rowHeaders, array_intersect_key($row, $rowHeaders)));
            fputcsv($handle, $row, $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);
        };

        // Second loop to fill each row.
        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
        if ($this->isId) {
            foreach ($this->resourceIds as $resourceId) {
                try {
                    $resource = $this->api->read($this->resourceType, ['id' => $resourceId])->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    continue;
                }
                $outputRowForResource($resource);
            }
        } else {
            array_walk($this->resources, $outputRowForResource);
        }

        if ($this->isOutput) {
            fclose($handle);
            return null;
        }

        rewind($handle);
        $this->content = stream_get_contents($handle);
        fclose($handle);
    }

    /**
     * Get all headers used in all resources.
     *
     * @return array Associative array with keys as headers and null as value.
     */
    protected function prepareHeaders()
    {
        $rowHeaders = [
            'id' => true,
            'url' => true,
            'Resource type' => false,
            'Resource class' => true,
            'Item sets' => false,
            'Item' => false,
            'Media' => false,
            'Media type' => false,
            'Size' => false,
            'Url' => false,
        ];
        // TODO Get only the used properties of the resources.
        $rowHeaders += array_fill_keys(array_keys($this->getPropertiesByTerm()), false);

        $resourceTypes = [];

        // TODO Get all data from one or two sql requests (and rights checks) (see AbstractSpreadsheet).

        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
        if ($this->isId) {
            foreach ($this->resourceIds as $key => $resourceId) {
                try {
                    $resource = $this->api->read($this->resourceType, ['id' => $resourceId])->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    unset($this->resourceIds[$key]);
                    continue;
                }
                $resourceTypes[$resource->resourceName()] = true;
                $rowHeaders = array_replace($rowHeaders, array_fill_keys(array_keys($resource->values()), true));
            }
        } else {
            foreach ($this->resources as $resource) {
                $resourceTypes[$resource->resourceName()] = true;
                $rowHeaders = array_replace($rowHeaders, array_fill_keys(array_keys($resource->values()), true));
            }
        }

        $resourceTypes = array_filter($resourceTypes);
        if (count($resourceTypes) > 1) {
            $rowHeaders['Resource type'] = true;
        }
        foreach (array_keys($resourceTypes) as $resourceType) {
            switch ($resourceType) {
                case 'items':
                    $rowHeaders = array_replace($rowHeaders, [
                        'Item sets' => true,
                        'Media' => true,
                    ]);
                    break;
                case 'media':
                    $rowHeaders = array_replace($rowHeaders, [
                        'Item' => true,
                        'Media type' => true,
                        'Size' => true,
                        'Url' => true,
                    ]);
                    break;
                default:
                    break;
            }
        }

        return array_fill_keys(array_keys(array_filter($rowHeaders)), null);
    }

    protected function prepareRow(AbstractResourceEntityRepresentation $resource, array $rowHeaders)
    {
        $row = [];
        $row['id'] = $resource->id();
        $row['url'] = $resource->url(null, true);
        // Manage an exception.
        if (array_key_exists('Resource type', $rowHeaders)) {
            $row['Resource type'] = basename(get_class($resource));
        }
        $resourceClass = $resource->resourceClass();
        $row['Resource class'] = $resourceClass ? $resourceClass->term() : '';

        $resourceName = $resource->resourceName();
        switch ($resourceName) {
            case 'items':
                /** @var \Omeka\Api\Representation\ItemRepresentation @resource */
                $urls = [];
                foreach ($resource->itemSets() as $itemSet) {
                    $urls[] = $itemSet->displayTitle();
                }
                $row['Item sets'] = implode($this->options['separator'], array_filter($urls));

                $urls = [];
                /** @var \Omeka\Api\Representation\MediaRepresentation $media*/
                foreach ($resource->media() as $media) {
                    // TODO Manage all types of media.
                    $urls[] = $media->originalUrl();
                }
                $row['Media'] = implode($this->options['separator'], array_filter($urls));
                break;

            case 'media':
                /* @var \Omeka\Api\Representation\MediaRepresentation @resource */
                $row['Item'] = $resource->item()->url();
                $row['Media type'] = $resource->mediaType();
                $row['Size'] = $resource->size();
                $row['Url'] = $resource->originalUrl();
                break;

            case 'item_sets':
                /* @var \Omeka\Api\Representation\ItemSetRepresentation @resource */
                // Nothing to do.
                break;

            default:
                break;
        }

        foreach ($resource->values() as $term => $values) {
            $row[$term] = implode($this->options['separator'], $values['values']);
        }

        return $row;
    }
}

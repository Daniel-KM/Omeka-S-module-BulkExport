<?php
namespace BulkExport\Formatter;

use BulkExport\Traits\ListTermsTrait;
use BulkExport\Traits\MetadataToStringTrait;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

abstract class AbstractSpreadsheetFormatter extends AbstractFormatter
{
    use ListTermsTrait;
    use MetadataToStringTrait;

    protected $defaultOptionsSpreadsheet = [
        'separator' => ' | ',
        'has_separator' => true,
        'format_generic' => 'raw',
        'format_resource' => 'url_title',
        'format_resource_property' => 'dcterms:identifier',
        'format_uri' => 'uri_label',
    ];

    public function format($resources, $output = null, array $options = [])
    {
        return parent::format($resources, $output, $options + $this->defaultOptionsSpreadsheet);
    }

    /**
     * Get all headers used in all resources.
     *
     * @return array Associative array with keys as headers and null as value.
     */
    protected function prepareHeaders()
    {
        $rowHeaders = [
            'o:id' => true,
            'url' => true,
            'resource_type' => false,
            'o:resource_class' => true,
            'o:item_set[dcterms:title]' => false,
            'o:item[dcterms:title]' => false,
            'o:media[o:id]' => false,
            'o:media[media_type]' => false,
            'o:media[size]' => false,
            'o:media[original_url]' => false,
            'o:resource[o:id]' => false,
            'o:resource[dcterms:identifier]' => false,
            'o:resource[dcterms:title]' => false,
        ];
        // TODO Get only the used properties of the resources.
        $rowHeaders += array_fill_keys(array_keys($this->getPropertiesByTerm()), false);

        // TODO Manage mixed resource types.
        if ($this->resourceType == 'annotations') {
            foreach (array_keys($this->getUsedPropertiesByTerm([\Annotate\Entity\AnnotationBody::class])) as $property) {
                $rowHeaders['oa:hasBody[' . $property . ']'] = false;
            }
            foreach (array_keys($this->getUsedPropertiesByTerm([\Annotate\Entity\AnnotationTarget::class])) as $property) {
                $rowHeaders['oa:hasTarget[' . $property . ']'] = false;
            }
        }

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
                if ($this->resourceType == 'annotations') {
                    foreach (array_keys($this->getUsedPropertiesByTerm([\Annotate\Entity\AnnotationBody::class])) as $property) {
                        $rowHeaders['oa:hasBody[' . $property . ']'] = true;
                    }
                    foreach (array_keys($this->getUsedPropertiesByTerm([\Annotate\Entity\AnnotationTarget::class])) as $property) {
                        $rowHeaders['oa:hasTarget[' . $property . ']'] = true;
                    }
                }
            }
        } else {
            foreach ($this->resources as $resource) {
                $resourceTypes[$resource->resourceName()] = true;
                $rowHeaders = array_replace($rowHeaders, array_fill_keys(array_keys($resource->values()), true));
                if ($this->resourceType == 'annotations') {
                    foreach (array_keys($this->getUsedPropertiesByTerm([\Annotate\Entity\AnnotationBody::class])) as $property) {
                        $rowHeaders['oa:hasBody[' . $property . ']'] = true;
                    }
                    foreach (array_keys($this->getUsedPropertiesByTerm([\Annotate\Entity\AnnotationTarget::class])) as $property) {
                        $rowHeaders['oa:hasTarget[' . $property . ']'] = true;
                    }
                }
            }
        }

        $resourceTypes = array_filter($resourceTypes);
        if (count($resourceTypes) > 1) {
            $rowHeaders['resource_type'] = true;
        }
        foreach (array_keys($resourceTypes) as $resourceType) {
            switch ($resourceType) {
                case 'items':
                    $rowHeaders = array_replace($rowHeaders, [
                        'o:item_set[dcterms:title]' => true,
                        'o:media[o:id]' => true,
                        'o:media[original_url]' => true,
                    ]);
                    break;
                case 'media':
                    $rowHeaders = array_replace($rowHeaders, [
                        'o:item[dcterms:title]' => true,
                        'o:media[media_type]' => true,
                        'o:media[size]' => true,
                        'o:media[original_url]' => true,
                    ]);
                    break;
                case 'annotations':
                    $rowHeaders = array_replace($rowHeaders, [
                        'o:resource[o:id]' => true,
                        'o:resource[dcterms:identifier]' => true,
                        'o:resource[dcterms:title]' => true,
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
        $row['o:id'] = $resource->id();
        $row['url'] = $resource->url(null, true);
        // Manage an exception.
        if (array_key_exists('resource_type', $rowHeaders)) {
            $row['resource_type'] = basename(get_class($resource));
        }
        $resourceClass = $resource->resourceClass();
        $row['o:resource_class'] = $resourceClass ? $resourceClass->term() : '';

        $resourceName = $resource->resourceName();
        switch ($resourceName) {
            case 'items':
                /** @var \Omeka\Api\Representation\ItemRepresentation $resource */
                $values = $this->stringMetadata($resource, 'o:item_set[dcterms:title]');
                $row['o:item_set[dcterms:title]'] = implode($this->options['separator'], $values);
                $values = $this->stringMetadata($resource, 'o:media[o:id]');
                $row['o:media[o:id]'] = implode($this->options['separator'], $values);
                $values = $this->stringMetadata($resource, 'o:media[original_url]');
                $row['o:media[original_url]'] = implode($this->options['separator'], $values);
                break;

            case 'media':
                /** @var \Omeka\Api\Representation\MediaRepresentation $resource */
                $row['o:item[dcterms:title]'] = $resource->item()->url();
                $row['o:media[media_type]'] = $resource->mediaType();
                $row['o:media[size]'] = $resource->size();
                $row['o:media[original_url]'] = $resource->originalUrl();
                break;

            case 'item_sets':
                /** @var \Omeka\Api\Representation\ItemSetRepresentation $resource */
                // Nothing to do.
                break;

            case 'annotations':
                /** @var \Annotate\Api\Representation\AnnotationRepresentation $resource */
                $values = $this->stringMetadata($resource, 'o:resource[o:id]');
                $row['o:resource[o:id]'] = implode($this->options['separator'], $values);
                $values = $this->stringMetadata($resource, 'o:resource[dcterms:identifier]');
                $row['o:resource[dcterms:identifier]'] = implode($this->options['separator'], $values);
                $values = $this->stringMetadata($resource, 'o:resource[dcterms:title]');
                $row['o:resource[dcterms:title]'] = implode($this->options['separator'], $values);
                break;

            default:
                break;
        }

        foreach (array_keys($resource->values()) as $term) {
            $values = $this->stringMetadata($resource, $term);
            $row[$term] = implode($this->options['separator'], $values);
        }

        if ($this->resourceType == 'annotations') {
            foreach (array_keys($rowHeaders) as $metadata) {
                if (in_array(strtok($metadata, '['), ['oa:hasBody', 'oa:hasTarget'])) {
                    $values = $this->stringMetadata($resource, $metadata);
                    $row[$metadata] = implode($this->options['separator'], $values);
                }
            }
        }

        return $row;
    }
}

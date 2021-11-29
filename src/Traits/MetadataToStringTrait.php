<?php declare(strict_types=1);

namespace BulkExport\Traits;

use Omeka\Api\Representation\AbstractRepresentation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Omeka\Api\Representation\ValueRepresentation;

trait MetadataToStringTrait
{
    /**
     * Get the list of string representations of any metadata.
     *
     * Portion of code similar in module Oai-Pmh repository.
     * @see \OaiPmhRepository\OaiPmh\Metadata\AbstractMetadata::formatValue()
     *
     * @param AbstractResourceRepresentation $resource
     * @param string $metadata It can be a key of the json-serialized resource,
     * or a specific key used by some formatters.
     * @param array $params Params are merged with options. Managed params are:
     * - only_first: if set, only the first value will be fetched for properties.
     * - format_fields: "name" (default) or "label".
     * - format_generic: "html" or raw value.
     * - format_resource: may be "url_title", "url", "title", "id", "identifier"
     *   (with the property set below), "identifier_id", or id. Default is
     *   "url_title".
     *   property set below), else the id will be used.
     * - format_resource_property: if resource hasn't this term, the id is used.
     * - format_uri: May be "uri", "html" or "uri_label".
     * @return array Always an array, even for single metadata. The caller knows
     * what to do with it.
     */
    protected function stringMetadata(AbstractResourceRepresentation $resource, $metadata, array $params = [])
    {
        if (empty($params)) {
            $params = $this->options;
        } else {
            $params += $this->options;
        }

        switch ($metadata) {
            // All resources.
            case 'o:id':
                return [$resource->id()];
            case 'url':
                // Should be enough, but CleanUrl needs the site slug.
                // return [$resource->url(null, true)];
                return $this->isAdminRequest()
                    ? [$resource->adminUrl(null, true)]
                    // Need site slug, but no background job in public.
                    : [$resource->siteUrl(null, true)];
            case 'resource_type':
                return [$this->labelResource($resource)];
            case 'o:resource_template':
                $resourceTemplate = $resource->resourceTemplate();
                return $resourceTemplate ? [$resourceTemplate->label()] : [''];
            case 'o:resource_class':
                $resourceClass = $resource->resourceClass();
                return $resourceClass ? [$resourceClass->term()] : [''];
            case 'o:is_public':
                return method_exists($resource, 'isPublic')
                    ? $resource->isPublic() ? ['true'] : ['false']
                    : [];
            case 'o:is_open':
                return method_exists($resource, 'isOpen')
                    ? $resource->isOpen() ? ['true'] : ['false']
                    : [];
            case 'o:owner[o:id]':
                $owner = $resource->owner();
                return $owner ? [$owner->id()] : [''];
            case 'o:owner':
            case 'o:owner[o:email]':
                $owner = $resource->owner();
                return $owner ? [$owner->email()] : [''];

            // Item set for item.
            case 'o:item_set[o:id]':
                return $resource->resourceName() === 'items'
                    ? $this->extractResourceIds($resource->itemSets())
                    : [];
            case 'o:item_set[dcterms:identifier]':
            case 'o:item_set[dcterms:title]':
                return $resource->resourceName() === 'items'
                    ? $this->extractFirstValueOfResources($resource->itemSets(), $metadata)
                    : [];

            // Media for item.
            case 'o:media[o:id]':
                return $resource->resourceName() === 'items'
                    ? $this->extractResourceIds($resource->media())
                    : [];
            case 'o:media[file]':
            case 'o:media[url]':
                $result = [];
                if ($resource->resourceName() === 'items') {
                    /** @var \Omeka\Api\Representation\MediaRepresentation $media */
                    foreach ($resource->media() as $media) {
                        $originalUrl = $media->originalUrl();
                        if ($originalUrl) {
                            $result[] = $originalUrl;
                        }
                    }
                } elseif ($resource->resourceName() === 'media') {
                    $originalUrl = $resource->originalUrl();
                    if ($originalUrl) {
                        $result[] = $originalUrl;
                    }
                }
                return $result;
            case 'o:media[source]':
                $result = [];
                if ($resource->resourceName() === 'items') {
                    /** @var \Omeka\Api\Representation\MediaRepresentation $media */
                    foreach ($resource->media() as $media) {
                        $source = $media->source();
                        if ($source) {
                            $result[] = $source;
                        }
                    }
                } elseif ($resource->resourceName() === 'media') {
                    $source = $resource->source();
                    if ($source) {
                        $result[] = $source;
                    }
                }
                return $result;
            case 'o:media[dcterms:identifier]':
            case 'o:media[dcterms:title]':
                return $resource->resourceName() === 'items'
                    ? $this->extractFirstValueOfResources($resource->media(), $metadata)
                    : [];
            case 'o:media[media_type]':
                return $resource->resourceName() === 'media'
                    ? $resource->mediaType()
                    : [];
            case 'o:media[size]':
                return $resource->resourceName() === 'media'
                    ? $resource->size()
                    : [];
            case 'o:media[original_url]':
                return $resource->resourceName() === 'media'
                    ? $resource->originalUrl()
                    : [];

            // Item for media.
            case 'o:item[o:id]':
                return $resource->resourceName() === 'media'
                    ? [$resource->item()->id()]
                    : [];
            case 'o:item[dcterms:identifier]':
            case 'o:item[dcterms:title]':
                return $resource->resourceName() === 'media'
                    ? $this->extractFirstValueOfResources([$resource->item()], $metadata)
                    : [];

            // Resources for annotation (target).
            case 'o:resource[o:id]':
                /** @var \Annotate\Api\Representation\AnnotationRepresentation $resource*/
                $resourceIds = [];
                foreach ($resource->targets() as $target) {
                    foreach ($target->sources() as $targetSource) {
                        $resourceIds[$targetSource->id()] = $targetSource->id();
                    }
                }
                return array_values(array_unique($resourceIds));
            case 'o:resource[dcterms:identifier]':
            case 'o:resource[dcterms:title]':
                /** @var \Annotate\Api\Representation\AnnotationRepresentation $resource*/
                $resourceValues = [];
                foreach ($resource->targets() as $target) {
                    $resourceValues[] = $this->extractFirstValueOfResources($target->sources(), $metadata);
                }
                return array_values(array_unique($resourceValues));

            // Bodies and targets of annotations.
            case strpos($metadata, 'oa:hasBody[') === 0:
                return $resource->resourceName() === 'annotations'
                    ? $this->extractFirstValueOfResources($resource->bodies(), $metadata)
                    : [];
            case strpos($metadata, 'oa:hasTarget[') === 0:
                return $resource->resourceName() === 'annotations'
                    ? $this->extractFirstValueOfResources($resource->targets(), $metadata)
                    : [];

            // All properties for all resources.
            default:
                if (empty($params['only_first'])) {
                    /** @var \Omeka\Api\Representation\ValueRepresentation[] $vv */
                    $vv = $resource->value($metadata, ['all' => true]);
                } else {
                    /** @var \Omeka\Api\Representation\ValueRepresentation[] $vv */
                    $vv = $resource->value($metadata, ['default' => false]);
                    $vv = $vv ? [$vv] : [];
                }
                // Values are converted by reference.
                foreach ($vv as &$v) {
                    $type = $v->type();
                    switch ($type) {
                        case 'resource':
                        case substr($type, 0, 9) === 'resource:':
                            $v = $this->stringifyResource($v->valueResource(), $params);
                            break;
                        case 'uri':
                        case substr($type, 0, 13) === 'valuesuggest:':
                        case substr($type, 0, 16) === 'valuesuggestall:':
                            $v = $this->stringifyUri($v, $params);
                            break;
                        // Module Custom vocab.
                        case substr($type, 0, 12) === 'customvocab:':
                            $vvr = $v->valueResource();
                            $v = $vvr ? $this->stringifyResource($vvr, $params) : (string) $v->value();
                            break;
                        // Module module Numeric data type.
                        case substr($type, 0, 8) === 'numeric:':
                            $v = (string) $v;
                            break;
                        // Module DataTypeRdf.
                        case 'xml':
                        // Module RdfDatatype (deprecated).
                        case 'rdf:XMLLiteral':
                        case 'xsd:date':
                        case 'xsd:dateTime':
                        case 'xsd:decimal':
                        case 'xsd:gDay':
                        case 'xsd:gMonth':
                        case 'xsd:gMonthDay':
                        case 'xsd:gYear':
                        case 'xsd:gYearMonth':
                        case 'xsd:time':
                            $v = (string) $v;
                            break;
                        case 'integer':
                        case 'xsd:integer':
                            $v = (int) $v->value();
                            break;
                        case 'boolean':
                        case 'xsd:boolean':
                            $v = $v->value() ? 'true' : 'false';
                            break;
                        case 'html':
                        case 'rdf:HTML':
                            $v = $v->asHtml();
                            break;
                        case 'literal':
                        default:
                            $v = $params['format_generic'] === 'html'
                                ? $v->asHtml()
                                : (string) $v;
                            break;
                    }
                }
                unset($v);
                return $vv;
        }
    }

    protected function stringifyUri(ValueRepresentation $value, array $params): string
    {
        switch ($params['format_uri']) {
            case 'uri':
                $v = (string) $value->uri();
                break;
            case 'html':
                $v = $value->asHtml();
                break;
            case 'uri_label':
            default:
                $v = trim($value->uri() . ' ' . $value->value());
                break;
        }
        return $v;
    }

    protected function stringifyResource(AbstractResourceEntityRepresentation $resource, array $params): string
    {
        switch ($params['format_resource']) {
            case 'id':
                $v = (string) $resource->id();
                break;
            case 'identifier':
                $v = (string) $resource->value($params['format_resource_property']);
                break;
            case 'identifier_id':
                $v = (string) $resource->value($params['format_resource_property'], ['default' => $resource->id()]);
                break;
            case 'title':
                $v = $resource->displayTitle('[#' . $resource->id() . ']');
                break;
            case 'url':
                $v = $this->isAdminRequest()
                    ? $resource->adminUrl(null, true)
                    : (empty($params['site_slug']) ? $resource->apiUrl() : $resource->siteUrl($params['site_slug'], true));
                break;
            case 'url_title':
            default:
                $vUrl = $this->isAdminRequest()
                    ? $resource->adminUrl(null, true)
                    : (empty($params['site_slug']) ? $resource->apiUrl() : $resource->siteUrl($params['site_slug'], true));
                $vTitle = $resource->displayTitle('');
                $v = $vUrl . (strlen($vTitle) ? ' ' . $vTitle : '');
                break;
        }
        return $v;
    }

    /**
     * Return the id of all resources.
     *
     * @param AbstractResourceRepresentation[] $resources
     * @return array
     */
    protected function extractResourceIds(array $resources)
    {
        return array_map(function ($v) {
            return $v->id();
        }, $resources);
    }

    /**
     * Return the first value of the property of all resources.
     *
     * @param \Omeka\Api\Representation\AbstractResourceEntityRepresentation[] $resources
     * @param string $metadata The full metadata, with a term.
     * @return array
     */
    protected function extractFirstValueOfResources(array $resources, $metadata)
    {
        $result = [];
        $term = trim(substr($metadata, strpos($metadata, '[') + 1), '[] ');
        foreach ($resources as $resource) {
            $value = $resource->value($term);
            if ($value) {
                // The value should be a string.
                $v = $value->uri();
                if (!is_string($v)) {
                    $v = $value->valueResource();
                    $v = $v ? $v->id() : $value->value();
                }
                $result[] = $v;
            }
        }
        return $result;
    }

    protected function labelResource(AbstractRepresentation $representation)
    {
        $class = get_class($representation);
        $mapping = [
            // Core.
            \Omeka\Api\Representation\UserRepresentation::class => 'User',
            \Omeka\Api\Representation\VocabularyRepresentation::class => 'Vocabulary',
            \Omeka\Api\Representation\ResourceClassRepresentation::class => 'Resource class',
            \Omeka\Api\Representation\ResourceTemplateRepresentation::class => 'Resource template',
            \Omeka\Api\Representation\PropertyRepresentation::class => 'Property',
            \Omeka\Api\Representation\ItemRepresentation::class => 'Item',
            \Omeka\Api\Representation\MediaRepresentation::class => 'Media',
            \Omeka\Api\Representation\ItemSetRepresentation::class => 'Item set',
            \Omeka\Api\Representation\ModuleRepresentation::class => 'Module',
            \Omeka\Api\Representation\SiteRepresentation::class => 'Site',
            \Omeka\Api\Representation\SitePageRepresentation::class => 'Site page',
            \Omeka\Api\Representation\JobRepresentation::class => 'Job',
            \Omeka\Api\Representation\ResourceReference::class => 'Resource',
            \Omeka\Api\Representation\AssetRepresentation::class => 'Asset',
            \Omeka\Api\Representation\ApiResourceRepresentation::class => 'Api resource',
            // Modules.
            \Annotate\Api\Representation\AnnotationRepresentation::class => 'Annotation',
        ];
        return isset($mapping[$class])
            ? $mapping[$class]
            : str_replace('Representation', '', substr($class, strrpos($class, '\\') + 1));
    }

    protected function isAdminRequest(): bool
    {
        static $status;
        if (is_null($status)) {
            $status = $this->getServiceLocator()->get('Omeka\Status')->isAdminRequest();
        }
        return $status;
    }
}

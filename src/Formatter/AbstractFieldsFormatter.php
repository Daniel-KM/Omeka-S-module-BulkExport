<?php declare(strict_types=1);

namespace BulkExport\Formatter;

use BulkExport\Traits\ListTermsTrait;
use BulkExport\Traits\MetadataToStringTrait;
use BulkExport\Traits\ResourceFieldsTrait;
use BulkExport\Traits\ShaperTrait;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

abstract class AbstractFieldsFormatter extends AbstractFormatter
{
    use ListTermsTrait;
    use MetadataToStringTrait;
    use ResourceFieldsTrait;
    use ShaperTrait;

    protected $defaultOptionsFields = [
        'format_fields' => 'name',
        'format_fields_labels' => [],
        'format_generic' => 'raw',
        'format_resource' => 'url_title',
        'format_resource_property' => 'dcterms:identifier',
        'format_uri' => 'uri_label',
        'only_first' => false,
        'empty_fields' => false,
        // Optional Mapper mapping name applied to each value as a post-format
        // step. Each field is matched against a map whose from.path equals the
        // field name; the value is passed through serializeValue. Requires
        // module Mapper. Without this option the formatter keeps its legacy
        // behavior unchanged.
        'mapper' => null,
    ];

    /**
     * Useful only for spreadsheet header.
     *
     * @var bool
     */
    protected $prependFieldNames = false;

    public function format($resources, $output = null, array $options = []): self
    {
        return parent::format($resources, $output, $options + $this->defaultOptionsFields);
    }

    protected function process(): self
    {
        $metadata = (array) ($this->options['metadata'] ?? []);
        $metadataSize = (string) ($this->options['metadata_size'] ?? '');
        if ($metadataSize !== '') {
            $metadata[] = $metadataSize;
        }
        $metadataExclude = (array) ($this->options['metadata_exclude'] ?? []);
        $metadataExcludeSize = (string) ($this->options['metadata_exclude_size'] ?? '');
        if ($metadataExcludeSize !== '') {
            $metadataExclude[] = $metadataExcludeSize;
        }
        $this
            ->prepareFieldNames($metadata, $metadataExclude);

        if (!count($this->fieldNames)) {
            $this->logger->warn('No metadata are used in any resources.'); // @translate
            return $this;
        }

        $this->initializeOutput();
        if ($this->hasError) {
            return $this;
        }

        if ($this->prependFieldNames) {
            $formatFields = $this->options['format_fields'] ?? 'name';
            $this->labelFormatFields = $formatFields;
            if ($formatFields === 'label' || $formatFields === 'template') {
                $this
                    ->prepareFieldLabels($formatFields === 'template')
                    ->writeFields($this->fieldLabels);
            } else {
                $this
                    ->writeFields($this->fieldNames);
            }
        }

        // Initialize stats for tracking.
        $this->stats['total'] = $this->isId ? count($this->resourceIds) : count($this->resources);
        $this->stats['processed'] = 0;
        $this->stats['succeeded'] = 0;
        $this->stats['skipped'] = 0;

        if ($this->isId) {
            foreach ($this->resourceIds as $resourceId) {
                try {
                    /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
                    $resource = $this->api->read($this->resourceType, ['id' => $resourceId])->getContent();
                } catch (NotFoundException $e) {
                    $this->stats['skipped']++;
                    $this->stats['processed']++;
                    continue;
                }
                $dataResource = $this->getDataResource($resource);
                if (count($dataResource)) {
                    $this
                        ->writeFields($dataResource);
                    $this->stats['succeeded']++;
                }
                $this->stats['processed']++;
            }
        } else {
            foreach ($this->resources as $resource) {
                $dataResource = $this->getDataResource($resource);
                if (count($dataResource)) {
                    $this
                        ->writeFields($dataResource);
                    $this->stats['succeeded']++;
                }
                $this->stats['processed']++;
            }
        }

        $this->finalizeOutput();
        return $this;
    }

    protected function getDataResource(AbstractResourceEntityRepresentation $resource): array
    {
        $dataResource = [];
        $removeEmptyFields = !$this->options['empty_fields'];
        foreach ($this->fieldNames as $fieldName) {
            $shaper = $this->options['metadata_shapers'][$fieldName] ?? null;
            $shaperParams = $this->shaperSettings($shaper);
            $values = $this->stringMetadata($resource, $fieldName, $shaperParams);
            $values = $this->shapeValues($values, $shaperParams);
            $values = $this->serializeViaMapper($fieldName, $values);
            if ($removeEmptyFields) {
                $values = array_filter($values, 'strlen');
                if (!count($values)) {
                    continue;
                }
            }
            if (isset($dataResource[$fieldName])) {
                $dataResource[$fieldName] = is_array($dataResource[$fieldName])
                    ? array_merge($dataResource[$fieldName], $values)
                    : array_merge([$dataResource[$fieldName]], $values);
            } else {
                $dataResource[$fieldName] = $values;
            }
        }
        return $dataResource;
    }

    /**
     * Apply Mapper::serializeValue() to each value when an export mapping is
     * configured. The mapping's [maps] section is queried for an entry whose
     * from.path equals the field name. Without an option or without the Mapper
     * service the values are returned unchanged.
     */
    protected function serializeViaMapper(string $fieldName, array $values): array
    {
        $mappingName = $this->options['mapper'] ?? null;
        if (!$mappingName || !$this->services->has('Mapper\Mapper')) {
            return $values;
        }

        /** @var \Mapper\Stdlib\Mapper $mapper */
        $mapper = $this->services->get('Mapper\Mapper');
        $mapper->setMappingName($mappingName);

        $map = $mapper->getMapperConfig()->getSectionSetting('maps', $fieldName);
        if (!is_array($map)) {
            return $values;
        }

        $result = [];
        foreach ($values as $value) {
            $result[] = $mapper->serializeValue($value, $map);
        }
        return $result;
    }

    /**
     * @param array $fields If fields contains arrays, this method should manage
     * them.
     */
    abstract protected function writeFields(array $fields): self;
}

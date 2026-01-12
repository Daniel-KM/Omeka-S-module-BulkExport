<?php declare(strict_types=1);

namespace BulkExport\Formatter;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

abstract class AbstractSpreadsheetFormatter extends AbstractFieldsFormatter
{
    protected $defaultOptionsSpreadsheet = [
        'separator' => ' | ',
        'has_separator' => true,
        'empty_fields' => true,
        // Advanced features.
        'value_per_column' => false,
        'column_metadata' => [],
        'format_fields_labels' => [],
        'metadata_shapers' => [],
    ];

    protected $prependFieldNames = true;

    /**
     * @var \OpenSpout\Writer\WriterInterface
     */
    protected $spreadsheetWriter;

    /**
     * Type of spreadsheet (default to csv).
     *
     * @var \OpenSpout\Common\Type
     */
    protected $spreadsheetType;

    /**
     * @var string
     */
    protected $filepath;

    public function format($resources, $output = null, array $options = []): self
    {
        $options += $this->defaultOptionsSpreadsheet;
        $separator = $options['separator'];
        $options['separator'] = $separator;
        $options['has_separator'] = mb_strlen($separator) > 0;
        $options['only_first'] = !$options['has_separator'];
        return parent::format($resources, $output, $options);
    }

    protected function process(): self
    {
        $this
            ->parseFormatFieldsLabels($this->options['format_fields_labels'] ?? [])
            ->parseMetadataShapers()
            ->prepareFieldNames($this->options['metadata'], $this->options['metadata_exclude']);

        if (!count($this->fieldNames)) {
            $this->logger->warn('No metadata are used in any resources.'); // @translate
            return $this;
        }

        // Pre-scan for column expansion modes (value_per_column and/or column_metadata).
        if ($this->hasColumnExpansionMode()) {
            // Get flat list of IDs.
            $flatIds = $this->resourceIds ?: array_map(fn($r) => $r->id(), $this->resources);
            // Format as grouped by resource type for prescan.
            $resourceType = $this->options['resource_types'][0] ?? 'o:Item';
            $this->prescanResourcesForColumns([$resourceType => $flatIds]);
            $this->expandFieldNamesForColumns();
        }

        $this->initializeOutput();
        if ($this->hasError) {
            return $this;
        }

        if ($this->prependFieldNames) {
            // Use expanded field names for headers in column expansion mode.
            $headerFields = $this->hasColumnExpansionMode()
                ? $this->getExpandedFieldNames()
                : $this->fieldNames;

            $formatFields = $this->options['format_fields'] ?? 'name';
            $this->labelFormatFields = $formatFields;
            if ($formatFields === 'label' || $formatFields === 'template') {
                $this
                    ->prepareFieldLabels($formatFields === 'template')
                    ->writeFields($this->hasColumnExpansionMode() ? $this->getExpandedFieldNames() : $this->fieldLabels);
            } else {
                $this
                    ->writeFields($headerFields);
            }
        }

        // Process resources with batch memory management in batch mode.
        $batchSize = $this->batchSize ?? self::SQL_LIMIT;

        // Initialize stats for tracking.
        $this->stats['total'] = $this->isId ? count($this->resourceIds) : count($this->resources);
        $this->stats['processed'] = 0;
        $this->stats['succeeded'] = 0;
        $this->stats['skipped'] = 0;

        if ($this->isId) {
            foreach ($this->resourceIds as $resourceId) {
                if ($this->shouldStop()) {
                    break;
                }
                try {
                    $resource = $this->api->read($this->resourceType, ['id' => $resourceId])->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    $this->stats['skipped']++;
                    $this->stats['processed']++;
                    continue;
                }
                $dataResource = $this->getDataResource($resource);
                if (count($dataResource)) {
                    $this->writeFields($dataResource);
                    $this->stats['succeeded']++;
                }
                $this->stats['processed']++;
                // Clear entity manager periodically to avoid memory issues in batch mode.
                if ($this->isBatchMode() && $this->stats['processed'] % $batchSize === 0) {
                    $this->clearEntityManager();
                    $this->reportProgress();
                }
            }
        } else {
            foreach ($this->resources as $resource) {
                if ($this->shouldStop()) {
                    break;
                }
                $dataResource = $this->getDataResource($resource);
                if (count($dataResource)) {
                    $this->writeFields($dataResource);
                    $this->stats['succeeded']++;
                }
                $this->stats['processed']++;
                // Clear entity manager periodically to avoid memory issues in batch mode.
                if ($this->isBatchMode() && $this->stats['processed'] % $batchSize === 0) {
                    $this->clearEntityManager();
                    $this->reportProgress();
                }
            }
        }

        $this->finalizeOutput();
        return $this;
    }

    /**
     * Clear the entity manager to free memory.
     */
    protected function clearEntityManager(): void
    {
        if (isset($this->services)) {
            $this->services->get('Omeka\EntityManager')->clear();
        }
    }

    protected function getDataResource(AbstractResourceEntityRepresentation $resource): array
    {
        // Handle column expansion modes (value_per_column and/or column_metadata).
        if ($this->hasColumnExpansionMode()) {
            return $this->getDataResourcePerColumn($resource);
        }

        $dataResource = [];
        $separator = $this->options['separator'];

        foreach ($this->fieldNames as $fieldName) {
            // Get all source fields for this output field (handles merged fields).
            $sourceFields = $this->getSourceFieldsForOutput($fieldName);
            // Get the shaper for this output field (supports multiple shapers per metadata).
            $outputShaper = $this->getShaperForField($fieldName);
            $allValues = [];

            foreach ($sourceFields as $sourceField) {
                // Use output field's shaper if set, otherwise check source field's shaper.
                $shaper = $outputShaper ?? $this->getShaperForField($sourceField);
                $shaperParams = $this->shaperSettings($shaper);
                $values = $this->stringMetadata($resource, $sourceField, $shaperParams);
                $values = $this->shapeValues($values, $shaperParams);
                $allValues = array_merge($allValues, $values);
            }

            if ($this->options['has_separator']) {
                // Check if any value contains the separator (data integrity issue).
                $valuesWithSeparator = array_filter($allValues, fn($v) => strpos((string) $v, $separator) !== false);
                if ($valuesWithSeparator) {
                    $this->logger->warn(
                        'Skipped resource #{resource_id}: a value in field "{field}" contains the separator "{separator}".', // @translate
                        ['resource_id' => $resource->id(), 'field' => $fieldName, 'separator' => $separator]
                    );
                    return [];
                }
                $dataResource[] = implode($separator, $allValues);
            } else {
                $dataResource[] = (string) reset($allValues);
            }
        }

        return $dataResource;
    }

    /**
     * Get resource data with one value per column.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return array
     */
    protected function getDataResourcePerColumn(AbstractResourceEntityRepresentation $resource): array
    {
        $dataResource = [];
        $separator = $this->options['separator'] ?? ' | ';

        foreach ($this->fieldNames as $fieldName) {
            // Check if this is a property field with expanded columns.
            if (isset($this->fieldColumnsInfo[$fieldName])) {
                // Get values organized by column position.
                $columnValues = $this->getValuesForColumnOutput($resource, $fieldName, $separator);
                foreach ($columnValues as $value) {
                    $dataResource[] = $value;
                }
            } else {
                // Non-property field or single field - use standard processing.
                $sourceFields = $this->getSourceFieldsForOutput($fieldName);
                $outputShaper = $this->getShaperForField($fieldName);
                $allValues = [];
                foreach ($sourceFields as $sourceField) {
                    $shaper = $outputShaper ?? $this->getShaperForField($sourceField);
                    $shaperParams = $this->shaperSettings($shaper);
                    $values = $this->stringMetadata($resource, $sourceField, $shaperParams);
                    $values = $this->shapeValues($values, $shaperParams);
                    $allValues = array_merge($allValues, $values);
                }
                // For non-expanded fields, join with separator or take first value.
                if ($this->options['only_first'] || !$this->options['has_separator']) {
                    $dataResource[] = (string) reset($allValues);
                } else {
                    $dataResource[] = implode($this->options['separator'], $allValues);
                }
            }
        }

        return $dataResource;
    }
}

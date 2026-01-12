<?php declare(strict_types=1);

namespace BulkExport\Writer;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use OpenSpout\Common\Type;
use OpenSpout\Writer\Common\Creator\WriterEntityFactory;

abstract class AbstractSpreadsheetWriter extends AbstractFieldsWriter
{
    protected $configKeys = [
        'separator',
    ];

    protected $paramsKeys = [
        'separator',
    ];

    protected $spreadsheetOptions = [
        'separator' => ' | ',
        'has_separator' => true,
        'empty_fields' => true,
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

    protected function initializeParams(): self
    {
        $this->options = $this->spreadsheetOptions + $this->options;
        $separator = $this->getParam('separator', '');
        $this->options['separator'] = $separator;
        $this->options['has_separator'] = mb_strlen($separator) > 0;
        $this->options['only_first'] = !$this->options['has_separator'];
        if ($this->options['only_first']) {
            $this->logger->warn(
                'No separator selected: only the first value of each property of each resource will be output.' // @translate
            );
        }
        return parent::initializeParams();
    }

    protected function initializeOutput(): self
    {
        switch ($this->spreadsheetType) {
            case Type::CSV:
                $this->spreadsheetWriter = WriterEntityFactory::createCSVWriter();
                break;
            case Type::ODS:
                $this->spreadsheetWriter = WriterEntityFactory::createODSWriter();
                break;
            default:
                $this->logger->err(
                    'Unsupported format {format} for spreadsheet.', // @translate
                    ['format' => $this->spreadsheet]
                );
                $this->hasError = true;
                return $this;
        }
        $this->spreadsheetWriter
            ->openToFile($this->filepath);
        return $this;
    }

    protected function writeFields(array $fields): self
    {
        $row = WriterEntityFactory::createRowFromArray($fields);
        $this->spreadsheetWriter
            ->addRow($row);
        return $this;
    }

    protected function finalizeOutput(): self
    {
        $this->spreadsheetWriter->close();
        return $this;
    }

    protected function getDataResource(AbstractResourceEntityRepresentation $resource): array
    {
        // Handle value_per_column mode.
        if ($this->isValuePerColumnMode()) {
            return $this->getDataResourcePerColumn($resource);
        }

        $dataResource = [];

        if ($this->options['only_first']) {
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
                $dataResource[] = (string) reset($allValues);
            }
            return $dataResource;
        }

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
            // Check if one of the values has the separator.
            $check = array_filter($allValues, function ($v) use ($separator) {
                return strpos((string) $v, $separator) !== false;
            });
            if ($check) {
                $this->logger->warn(
                    'Skipped {resource_type} #{resource_id}: it contains the separator "{separator}".', // @translate
                    ['resource_type' => $this->mapRepresentationToResourceTypeText($resource), 'resource_id' => $resource->id(), 'separator' => $separator]
                );
                $dataResource = [];
                break;
            }
            $dataResource[] = implode($separator, $allValues);
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

        foreach ($this->fieldNames as $fieldName) {
            // Check if this is a property field with expanded columns.
            if (isset($this->fieldColumnsInfo[$fieldName])) {
                // Get values organized by column position.
                $columnValues = $this->getValuesForColumnOutput($resource, $fieldName);
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

<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Form\Writer\CsvWriterConfigForm;

/**
 * CSV Writer - thin wrapper around Csv Formatter.
 *
 * This writer delegates CSV formatting to the Csv Formatter while handling:
 * - Configuration forms for admin interface
 * - Incremental export support
 * - HistoryLog integration (include_deleted)
 * - File path management
 * - Job integration
 *
 * @see \BulkExport\Formatter\Csv for the actual CSV formatting logic
 */
class CsvWriter extends AbstractFormatterWriter
{
    const DEFAULT_DELIMITER = ',';
    const DEFAULT_ENCLOSURE = '"';
    const DEFAULT_ESCAPE = '\\';

    protected $label = 'CSV'; // @translate
    protected $extension = 'csv';
    protected $mediaType = 'text/csv';
    protected $configFormClass = CsvWriterConfigForm::class;
    protected $paramsFormClass = CsvWriterConfigForm::class;

    /**
     * The formatter to delegate to.
     */
    protected $formatterName = 'csv';

    /**
     * CSV supports appending deleted resources.
     */
    protected $supportsAppendDeleted = true;

    protected $configKeys = [
        'delimiter',
        'enclosure',
        'escape',
        'separator',
        'dirpath',
        'filebase',
        'format_fields',
        'format_fields_labels',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'language',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'metadata_shapers',
        'query',
        'zip_files',
        'incremental',
        'include_deleted',
        'value_per_column',
        'column_metadata',
    ];

    protected $paramsKeys = [
        'delimiter',
        'enclosure',
        'escape',
        'separator',
        'dirpath',
        'filebase',
        'format_fields',
        'format_fields_labels',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'language',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'metadata_shapers',
        'query',
        'zip_files',
        'incremental',
        'include_deleted',
        'value_per_column',
        'column_metadata',
    ];

    protected function getFormatterOptions(): array
    {
        $options = parent::getFormatterOptions();

        // Add CSV-specific options.
        $options['delimiter'] = $this->getParam('delimiter', self::DEFAULT_DELIMITER);
        $options['enclosure'] = $this->getParam('enclosure', self::DEFAULT_ENCLOSURE);
        $options['escape'] = $this->getParam('escape', self::DEFAULT_ESCAPE);

        return $options;
    }

    /**
     * Append deleted resources to the CSV file.
     *
     * Deleted resources don't exist in the database, so we write minimal data
     * (o:id and operation=delete) directly to the file.
     */
    protected function appendDeletedResources(array $deletedIdsByType): void
    {
        // Prepare field names for deleted resources.
        $this->prepareFieldNames($this->options['metadata'] ?? [], $this->options['metadata_exclude'] ?? []);

        if (!in_array('o:id', $this->fieldNames)) {
            $this->logger->warn(
                'The deleted resources cannot be output when the internal id is not included in the list of fields.' // @translate
            );
            return;
        }

        // Open file in append mode.
        $handle = fopen($this->filepath, 'a');
        if (!$handle) {
            $this->logger->err(
                'Unable to append deleted resources to output file.' // @translate
            );
            return;
        }

        $delimiter = $this->getParam('delimiter', self::DEFAULT_DELIMITER);
        $enclosure = $this->getParam('enclosure', self::DEFAULT_ENCLOSURE);
        $escape = $this->getParam('escape', self::DEFAULT_ESCAPE);

        $deleted = 0;

        foreach ($deletedIdsByType as $resourceType => $deletedIds) {
            $resourceName = $this->mapResourceTypeToApiResource($resourceType);
            $resourceText = $this->mapResourceTypeToText($resourceType);

            if (!count($deletedIds)) {
                continue;
            }

            $this->logger->info(
                'Appending {count} deleted {resource_type}.', // @translate
                ['count' => count($deletedIds), 'resource_type' => $resourceText]
            );

            foreach ($deletedIds as $resourceId) {
                // Build minimal data for deleted resource.
                $dataResource = [];
                foreach ($this->fieldNames as $fieldName) {
                    if ($fieldName === 'o:id') {
                        $dataResource[] = (string) $resourceId;
                    } elseif ($fieldName === 'operation') {
                        $dataResource[] = 'delete';
                    } else {
                        $dataResource[] = '';
                    }
                }

                fputcsv($handle, $dataResource, $delimiter, $enclosure, $escape);
                ++$deleted;

                // Update stats.
                $this->stats['process'][$resourceType]['processed']++;
                $this->stats['process'][$resourceType]['succeed']++;
            }
        }

        fclose($handle);

        if ($deleted) {
            $this->logger->notice(
                'Appended {count} deleted resources to export.', // @translate
                ['count' => $deleted]
            );
        }
    }
}

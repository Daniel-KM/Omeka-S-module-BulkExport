<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Form\Writer\TextWriterConfigForm;

/**
 * Text Writer - thin wrapper around Txt Formatter.
 *
 * @see \BulkExport\Formatter\Txt for the actual text formatting logic
 */
class TextWriter extends AbstractFormatterWriter
{
    protected $label = 'Text'; // @translate
    protected $extension = 'txt';
    protected $mediaType = 'text/plain';
    protected $configFormClass = TextWriterConfigForm::class;
    protected $paramsFormClass = TextWriterConfigForm::class;

    /**
     * The formatter to delegate to.
     */
    protected $formatterName = 'txt';

    /**
     * Text supports appending deleted resources.
     */
    protected $supportsAppendDeleted = true;

    protected $configKeys = [
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
    ];

    protected $paramsKeys = [
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
    ];

    /**
     * Append deleted resources to the text file.
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

        $deleted = 0;

        foreach ($deletedIdsByType as $resourceType => $deletedIds) {
            $resourceText = $this->mapResourceTypeToText($resourceType);

            if (!count($deletedIds)) {
                continue;
            }

            $this->logger->info(
                'Appending {count} deleted {resource_type}.', // @translate
                ['count' => count($deletedIds), 'resource_type' => $resourceText]
            );

            foreach ($deletedIds as $resourceId) {
                // Write minimal data for deleted resource in text format.
                foreach ($this->fieldNames as $fieldName) {
                    $fieldLabel = $this->options['format_fields'] === 'label'
                        ? $this->getFieldLabel($fieldName)
                        : $fieldName;

                    if ($fieldName === 'o:id') {
                        fwrite($handle, $fieldLabel . "\n");
                        fwrite($handle, "\t" . $resourceId . "\n");
                    } elseif ($fieldName === 'operation') {
                        fwrite($handle, $fieldLabel . "\n");
                        fwrite($handle, "\tdelete\n");
                    }
                    // Other fields are empty for deleted resources.
                }
                fwrite($handle, "\n--\n\n");
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
